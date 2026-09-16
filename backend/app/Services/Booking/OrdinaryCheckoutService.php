<?php

namespace App\Services\Booking;
use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\BookingRepositoryInterface;
use App\Contracts\Repositories\CourtBlockRepositoryInterface;
use App\Contracts\Repositories\CourtPriceSlotRepositoryInterface;
use App\Contracts\Repositories\CourtRepositoryInterface;
use App\Domain\Booking\DTOs\CreateBookingData;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\PriceQuote;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Notifications\AuraBookingNotification;
use App\Services\Finance\FinancialPostingService;
use App\Services\Infrastructure\ObservabilityService;
use App\Services\Payment\WalletPaymentService;
use App\Services\Reservation\LegacyBookingBackfillService;
use App\Services\Reservation\PriceQuoteService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrdinaryCheckoutService
{
    public function __construct(
        private readonly CourtRepositoryInterface $courts,
        private readonly CourtPriceSlotRepositoryInterface $priceSlots,
        private readonly CourtBlockRepositoryInterface $courtBlocks,
        private readonly BookingRepositoryInterface $bookings,
        private readonly DiscountService $discounts,
        private readonly WaitlistService $waitlists,
        private readonly WalletPaymentService $walletPayments,
        private readonly FinancialPostingService $financials,
        private readonly LegacyBookingBackfillService $backfill,
        private readonly PriceQuoteService $quotes,
        private readonly DurableNotificationServiceInterface $durableNotification,
    ) {}

    /**
     * Shared Ordinary V1 Wallet-Funded Checkout.
     * Preserves exact V1 response projection contracts while enforcing
     * atomic inventory, wallet balance verification, debit, and balanced financial posting.
     */
    public function checkoutV1(CreateBookingData $data): Booking
    {
        $startsAt = $data->slots;
        if (count($startsAt) < 1 || count($startsAt) > 12) {
            throw ValidationException::withMessages(['slots' => 'Chọn từ 1 đến 12 slot.']);
        }

        $zone = config('app.timezone');
        $local = collect($startsAt)->map(function (string $value) use ($zone) {
            try {
                $time = Carbon::parse($value)->setTimezone($zone);
            } catch (\Throwable) {
                throw ValidationException::withMessages(['slots' => 'Thời gian slot không hợp lệ.']);
            }

            if ($time->minute !== 0 || $time->second !== 0) {
                throw ValidationException::withMessages(['slots' => 'Slot phải bắt đầu đúng giờ.']);
            }
            if ($time->isPast()) {
                throw ValidationException::withMessages(['slots' => 'Không thể đặt slot trong quá khứ.']);
            }

            return $time;
        })->sort()->values();

        if ($local->unique(fn (Carbon $time) => $time->toIso8601String())->count() !== $local->count()) {
            throw ValidationException::withMessages(['slots' => 'Không được chọn slot trùng nhau.']);
        }

        for ($i = 1; $i < $local->count(); $i++) {
            if (! $local[$i]->equalTo($local[$i - 1]->copy()->addHour())) {
                throw ValidationException::withMessages(['slots' => 'Các slot phải liên tiếp trên cùng sân.']);
            }
        }

        $eligibility = app(\App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility::class);
        $court = $eligibility->assertEligible($data->courtId, publicFacing: true);

        $userId = $data->userId;
        $couponCode = $data->couponCode;

        try {
            $booking = DB::transaction(function () use ($userId, $court, $local, $couponCode) {
                $lockedVenue = \App\Models\Venue::query()
                    ->whereKey($court->venue_id)
                    ->lockForUpdate()
                    ->first();
                $rollout = app(\App\Domain\Partner\Services\PartnerRolloutService::class);
                $enforcePublication = $rollout->shouldEnforce($lockedVenue->organization_id, 'publication');

                abort_unless($lockedVenue && $lockedVenue->is_active && ($enforcePublication ? $lockedVenue->isPublished() : true), 409, 'Cơ sở thể thao vừa thay đổi trạng thái hoặc ngừng hoạt động.');

                $lockedOrg = \App\Models\Organization::query()
                    ->whereKey($lockedVenue->organization_id)
                    ->lockForUpdate()
                    ->first();
                abort_unless($lockedOrg && $lockedOrg->status === 'active' && ($enforcePublication ? $lockedOrg->isApprovedPartner() : true), 409, 'Câu lạc bộ vừa bị tạm ngừng hoạt động.');

                $prices = $this->priceSlots->getActiveForCourt($court);
                $hasBlock = $this->courtBlocks->hasOverlappingBlock(
                    $court,
                    $local->first()->copy()->utc(),
                    $local->last()->copy()->addHour()->utc()
                );
                abort_if($hasBlock, 409, 'Sân đang bị khóa trong khoảng thời gian đã chọn.');

                // Cross-check overlapping inventory in both V1 booking_slots and V2 reservation_entitlements
                $startUtcList = $local->map(fn (Carbon $t) => $t->copy()->utc())->all();
                $v1Conflict = BookingSlot::query()
                    ->where('court_id', $court->id)
                    ->whereIn('starts_at', $startUtcList)
                    ->whereIn('status', ['held', 'confirmed'])
                    ->lockForUpdate()
                    ->exists();
                if ($v1Conflict) {
                    abort(409, 'Một hoặc nhiều slot vừa được người khác đặt.');
                }

                $v2Conflict = ReservationEntitlement::query()
                    ->where('court_id', $court->id)
                    ->whereIn('starts_at', $startUtcList)
                    ->whereIn('status', ['held', 'active'])
                    ->lockForUpdate()
                    ->exists();
                if ($v2Conflict) {
                    abort(409, 'Một hoặc nhiều slot vừa được người khác đặt.');
                }

                $resolved = $local->map(function (Carbon $time) use ($prices) {
                    $price = $prices->first(fn ($slot) => $slot->weekday === $time->dayOfWeekIso
                        && substr($slot->starts_at, 0, 5) === $time->format('H:i')
                        && substr($slot->ends_at, 0, 5) === $time->copy()->addHour()->format('H:i'));
                    if (! $price) {
                        throw ValidationException::withMessages(['slots' => "Slot {$time->format('H:i')} chưa mở bán."]);
                    }

                    return [$time, (int) $price->price];
                });

                $subtotal = $resolved->sum(fn ($row) => $row[1]);
                $discount = $this->discounts->reserve($userId, $subtotal, $couponCode);
                $totalAmount = (int) $discount['total'];

                // Get and validate wallet
                $orgId = $court->venue?->organization_id;
                $wallet = $this->walletPayments->getOrCreateWallet($userId, $orgId);

                if ((int) $wallet->balance < $totalAmount) {
                    throw ValidationException::withMessages([
                        'wallet' => 'Số dư ví không đủ để thanh toán đặt sân. Vui lòng nạp thêm tiền vào ví.',
                        'balance' => 'Số dư hiện tại: ' . number_format((int) $wallet->balance, 0, ',', '.') . '₫',
                        'shortfall' => 'Còn thiếu: ' . number_format($totalAmount - (int) $wallet->balance, 0, ',', '.') . '₫',
                    ]);
                }

                $priceSnapshot = $resolved->map(fn ($item) => [
                    'starts_at' => $item[0]->toIso8601String(),
                    'ends_at' => $item[0]->copy()->addHour()->toIso8601String(),
                    'unit_price' => (int) $item[1],
                ])->all();

                $policySnapshot = [
                    'policy_version' => 'v1',
                    'captured_at' => now()->toIso8601String(),
                ];

                $booking = $this->bookings->create([
                    'code' => 'BK'.strtoupper(Str::random(12)),
                    'user_id' => $userId,
                    'court_id' => $court->id,
                    'coupon_id' => $discount['coupon']?->id,
                    'total_amount' => $totalAmount,
                    'discount_amount' => $discount['discount'],
                    'membership_discount_amount' => $discount['membership_discount'],
                    'status' => 'confirmed',
                    'payment_expires_at' => null,
                    'policy_snapshot' => $policySnapshot,
                    'price_snapshot' => $priceSnapshot,
                    'metadata' => [
                        'venue_id' => $court->venue_id,
                        'organization_id' => $orgId,
                        'venue_name' => $lockedVenue->name,
                    ],
                ]);

                // Debit wallet atomically
                $walletTx = $this->walletPayments->debit(
                    $wallet,
                    $totalAmount,
                    'booking_payment',
                    $booking->id,
                    $booking->code
                );

                // Create payment record
                $payment = $booking->payments()->create([
                    'provider' => 'wallet',
                    'external_transaction_id' => "wallet-txn-{$walletTx->id}",
                    'amount' => $totalAmount,
                    'transfer_code' => $booking->code,
                    'status' => 'paid',
                    'payload' => [
                        'wallet_account_id' => $wallet->id,
                        'wallet_transaction_id' => $walletTx->id,
                    ],
                    'received_at' => now(),
                ]);

                $slotsData = [];
                foreach ($resolved as [$time, $price]) {
                    $slotsData[] = [
                        'court_id' => $court->id,
                        'starts_at' => $time->copy()->utc(),
                        'ends_at' => $time->copy()->addHour()->utc(),
                        'unit_price' => $price,
                        'status' => 'confirmed',
                    ];
                }
                $this->bookings->createSlots($booking, $slotsData);

                // Post ledger journal
                $this->financials->postWalletBookingPayment($booking->fresh(['court.venue']), $payment, $wallet->id);

                // Ensure canonical V2 reservation identity mapping is synced
                $this->backfill->backfillBooking($booking);

                return $booking->load(['user', 'court.venue', 'slots', 'coupon', 'payments']);
            });
        } catch (QueryException $exception) {
            $msg = strtolower($exception->getMessage());
            if (str_contains($msg, 'booking_slots_active_unique')
                || (str_contains($msg, 'unique') && str_contains($msg, 'booking_slots'))
                || str_contains($msg, 'reservation_entitlements_active_unique')
            ) {
                abort(409, 'Một hoặc nhiều slot vừa được người khác đặt.');
            }
            throw $exception;
        }

        try {
            $this->durableNotification->notifyBooking(
                $booking,
                'payment_confirmed',
                'Đặt sân thành công',
                "Booking {$booking->code} tại {$booking->court->name} đã được thanh toán thành công qua số dư ví.",
                '/booking',
            );
        } catch (\Throwable $secondaryException) {
            Log::warning("Secondary booking notification dispatch encountered failure: {$secondaryException->getMessage()}", [
                'booking_code' => $booking->code,
                'exception' => $secondaryException,
            ]);
        }

        return $booking;
    }

    /**
     * Shared Ordinary V2 Wallet-Funded Checkout.
     * Directly creates and confirms a reservation, verifies wallet balance,
     * debits the wallet atomically, and creates balanced financial postings.
     */
    public function checkoutV2(User $user, int $organizationId, array $data, ?User $actor = null): Reservation
    {
        try {
            return DB::transaction(function () use ($user, $organizationId, $data, $actor): Reservation {
                $quote = $this->resolveQuote($user->id, $organizationId, $data);
                if ($quote->status !== 'open') {
                    abort(409, 'Price quote has already been accepted.');
                }
                if ($quote->expires_at?->isPast()) {
                    abort(409, 'Price quote has expired.');
                }

                $court = Court::query()->withoutGlobalScope('tenant_isolation')->findOrFail($quote->court_id);
                $totalAmount = (int) $quote->total_amount;

                // Validate wallet balance
                $wallet = $this->walletPayments->getOrCreateWallet($user->id, $organizationId);
                if ((int) $wallet->balance < $totalAmount) {
                    abort(422, 'Số dư ví không đủ để thanh toán lịch đặt. Vui lòng nạp thêm tiền vào ví.');
                }

                // Check court blocks
                $slotSnapshots = (array) $quote->slot_snapshot;
                if (! empty($slotSnapshots)) {
                    $firstStart = Carbon::parse($slotSnapshots[0]['starts_at']);
                    $lastEnd = Carbon::parse(end($slotSnapshots)['ends_at']);
                    $hasBlock = $this->courtBlocks->hasOverlappingBlock($court, $firstStart, $lastEnd);
                    abort_if($hasBlock, 409, 'Sân đang bị khóa trong khoảng thời gian đã chọn.');

                    // Cross-check conflicting slots
                    $startUtcList = array_map(fn ($s) => Carbon::parse($s['starts_at'])->toIso8601String(), $slotSnapshots);
                    $v1Conflict = BookingSlot::query()
                        ->where('court_id', $court->id)
                        ->whereIn('starts_at', $startUtcList)
                        ->whereIn('status', ['held', 'confirmed'])
                        ->lockForUpdate()
                        ->exists();
                    if ($v1Conflict) {
                        abort(409, 'The selected court-time is no longer available.');
                    }

                    $v2Conflict = ReservationSlot::query()
                        ->where('court_id', $court->id)
                        ->whereIn('starts_at', $startUtcList)
                        ->whereIn('status', ['held', 'confirmed'])
                        ->whereHas('reservation', fn ($r) => $r->whereIn('status', ['held', 'confirmed']))
                        ->lockForUpdate()
                        ->exists();
                    if ($v2Conflict) {
                        abort(409, 'The selected court-time is no longer available.');
                    }
                }

                $metadata = (array) ($data['metadata'] ?? []);
                if ($actor !== null && $actor->id !== $user->id) {
                    $metadata['walk_in'] = true;
                    $metadata['booked_by_staff_id'] = $actor->id;
                    $metadata['booked_by_staff_name'] = $actor->name;
                    $metadata['staff_actor_role'] = $actor->roleInOrganization($organizationId) ?? 'staff';
                }

                $reservation = Reservation::create([
                    'organization_id' => $organizationId,
                    'user_id' => $user->id,
                    'court_id' => $quote->court_id,
                    'price_quote_id' => $quote->id,
                    'code' => $this->uniqueReservationCode(),
                    'status' => 'confirmed',
                    'currency' => $quote->currency,
                    'total_amount' => $totalAmount,
                    'hold_expires_at' => null,
                    'confirmed_at' => now(),
                    'metadata' => $metadata,
                ]);

                foreach ($slotSnapshots as $slotSnapshot) {
                    $slot = ReservationSlot::create([
                        'organization_id' => $organizationId,
                        'reservation_id' => $reservation->id,
                        'court_id' => $quote->court_id,
                        'starts_at' => $slotSnapshot['starts_at'],
                        'ends_at' => $slotSnapshot['ends_at'],
                        'unit_price' => $slotSnapshot['unit_price'],
                        'status' => 'confirmed',
                        'snapshot' => $slotSnapshot,
                    ]);

                    ReservationEntitlement::create([
                        'organization_id' => $organizationId,
                        'reservation_id' => $reservation->id,
                        'reservation_slot_id' => $slot->id,
                        'user_id' => $user->id,
                        'court_id' => $quote->court_id,
                        'starts_at' => $slot->starts_at,
                        'ends_at' => $slot->ends_at,
                        'amount' => $slot->unit_price,
                        'status' => 'active',
                    ]);
                }

                // Debit wallet
                $walletTx = $this->walletPayments->debit(
                    $wallet,
                    $totalAmount,
                    'reservation_payment',
                    $reservation->id,
                    $reservation->code
                );

                // Update quote to accepted
                $quote->update([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                    'accepted_snapshot' => [
                        'quote_token' => $quote->quote_token,
                        'rule_version' => $quote->rule_version,
                        'slot_snapshot' => $quote->slot_snapshot,
                        'subtotal' => $quote->subtotal,
                        'total_amount' => $quote->total_amount,
                    ],
                ]);

                // Post balanced financial posting
                $this->financials->postWalletReservationPayment($reservation, $totalAmount, $wallet->id, $walletTx->id);

                return $reservation->load(['court.venue', 'quote', 'slots', 'entitlements']);
            });
        } catch (QueryException $exception) {
            $msg = strtolower($exception->getMessage());
            if (str_contains($msg, 'reservation_entitlements_active_unique')
                || (str_contains($msg, 'reservation_entitlements') && str_contains($msg, 'unique'))
                || str_contains($msg, 'booking_slots_active_unique')
            ) {
                app(ObservabilityService::class)->metric('reservation.overbooking_conflict');
                abort(409, 'The selected court-time is no longer available.');
            }
            throw $exception;
        }
    }

    private function resolveQuote(int $userId, int $organizationId, array $data): PriceQuote
    {
        $query = PriceQuote::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('user_id', $userId)
            ->where('status', 'open');

        if (! empty($data['quote_token'])) {
            $query->where('quote_token', $data['quote_token']);
        } elseif (! empty($data['quote_id'])) {
            $query->whereKey($data['quote_id']);
        } else {
            $quote = $this->quotes->create(
                $userId,
                $organizationId,
                (int) ($data['court_id'] ?? 0),
                (array) ($data['slots'] ?? []),
            );

            return PriceQuote::query()->withoutGlobalScope('tenant_isolation')->whereKey($quote->id)->lockForUpdate()->firstOrFail();
        }

        return $query->lockForUpdate()->firstOrFail();
    }

    private function uniqueReservationCode(): string
    {
        do {
            $code = 'RSV'.strtoupper(Str::random(13));
        } while (Reservation::query()->where('code', $code)->exists());

        return $code;
    }
}
