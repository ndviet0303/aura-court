<?php

namespace App\Services\Reservation;

use App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility;
use App\Models\Court;
use App\Models\PriceQuote;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\Booking\QuoteCouponService;
use App\Services\Finance\FinancialPostingService;
use App\Services\Infrastructure\FeatureFlagService;
use App\Services\Infrastructure\ObservabilityService;
use App\Services\Payment\ReservationPaymentService;
use App\Services\Payment\WalletAvailabilityService;
use App\Services\Payment\WalletPaymentService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReservationService
{
    public function __construct(
        private readonly PriceQuoteService $quotes,
        private readonly VenueOperatingCalendar $operatingCalendar,
        private readonly SlotSelectionPolicy $slotPolicy,
        private readonly QuoteCouponService $quoteCoupons,
        private readonly ReservationIntake $intake,
    ) {}

    public function hold(User $user, int $organizationId, array $data, ?User $actor = null): Reservation
    {
        if ($user->isRestricted()) {
            abort(403, 'Tài khoản của bạn đã bị khóa hoặc hạn chế quyền truy cập.');
        }
        if ($actor === null && ! $user->hasVerifiedEmail()) {
            abort(403, 'Vui lòng xác thực email của bạn trước khi thực hiện đặt sân. Yêu cầu gửi lại email xác thực tại /api/auth/email/verification-notification.');
        }
        $this->intake->assertBookerPhone($user);

        return $this->transactionWithConflictHandling(function () use ($user, $organizationId, $data, $actor): Reservation {
            $quote = $this->findQuote($user->id, $organizationId, $data);
            if (! $quote) {
                $quote = $this->quotes->create(
                    $user->id,
                    $organizationId,
                    (int) ($data['court_id'] ?? 0),
                    (array) ($data['slots'] ?? []),
                    $data['coupon_code'] ?? null,
                );
                $quote = PriceQuote::query()->withoutGlobalScope('tenant_isolation')->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            }

            $organizationId = $quote->organization_id;

            if ($quote->status !== 'open') {
                abort(409, 'Price quote has already been accepted.');
            }
            if ($quote->expires_at?->isPast()) {
                abort(409, 'Price quote has expired.');
            }
            // Step-14: re-check the quote on the locked rows; lock the subscription before the court, as quote creation does, so bookings cannot deadlock.
            $verifier = app(VerifyEffectiveBookingEligibility::class);
            $verifier->getEntitlement((int) $quote->organization_id);
            $lockedCourt = Court::query()->whereKey($quote->court_id)->lockForUpdate()->first();
            abort_unless($lockedCourt && $lockedCourt->status === Court::STATUS_ACTIVE, 409, 'Sân không tồn tại hoặc đã ngừng hoạt động.');
            $lockedVenue = Venue::query()->whereKey($lockedCourt->venue_id)->lockForUpdate()->firstOrFail();
            // BR-20/21: staff book for a customer only at a venue assigned to them.
            abort_if($actor !== null && ! $actor->canOperateVenue($lockedVenue), 403, 'Bạn không được giao cơ sở này.');
            $eligibility = $verifier->execute($lockedCourt->setRelation('venue', $lockedVenue));
            abort_unless($eligibility['eligible'], 409, $eligibility['message'] ?? '');
            $intervals = fn (): array => collect($quote->slot_snapshot)->map(fn (array $slotSnapshot): array => [
                'start' => $slotSnapshot['starts_at'],
                'end' => $slotSnapshot['ends_at'],
            ])->all();
            $this->intake->releaseOverdueHolds($lockedCourt, $intervals());
            // The client always sends the amount it showed; recurring periods price their own fresh quote.
            if (isset($data['expected_total'])) {
                $this->quotes->reprice($quote, $lockedCourt, $user);
                $this->intake->assertAgreedAmount((int) $data['expected_total'], (int) $quote->subtotal, (int) $quote->total_amount);
            }
            $couponUsage = $this->quoteCoupons->reserve($quote, $user, $lockedCourt);
            $directOwnerPayments = app(FeatureFlagService::class)
                ->enabled('direct_owner_payments', (int) $lockedVenue->organization_id);

            $this->slotPolicy->assertAllowed($lockedCourt, $intervals());

            $bookingPolicySnapshot = null;
            $holdExpiresAt = $quote->expires_at;
            if ($directOwnerPayments) {
                $bookingPolicySnapshot = $this->intake->bookingPolicySnapshot($lockedVenue);
                // Recurring periods hold until their own period deadline (design D3 leaves them out).
                if (! empty($data['hold_expires_at_override'])) {
                    $holdExpiresAt = Carbon::parse($data['hold_expires_at_override']);
                } elseif ((int) $quote->total_amount > 0) {
                    $holdExpiresAt = $this->intake->paymentDeadline($quote);
                }
            }

            $metadata = [...(array) ($data['metadata'] ?? []), 'booker' => $this->intake->booker($user)];
            if ($quote->benefit_snapshot) {
                $metadata['benefit_snapshot'] = $quote->benefit_snapshot;
            }
            if ($couponUsage !== null) {
                $metadata['coupon_usage'] = $couponUsage;
            }
            if ($directOwnerPayments) {
                $metadata['booking_policy_snapshot'] = $bookingPolicySnapshot;
                if ((int) $quote->total_amount === 0) {
                    $metadata['zero_payable'] = true;
                }
            }

            $reservation = Reservation::create([
                'organization_id' => $organizationId,
                'user_id' => $user->id,
                'court_id' => $quote->court_id,
                'price_quote_id' => $quote->id,
                'code' => $this->uniqueCode(),
                'status' => $directOwnerPayments && (int) $quote->total_amount === 0 ? 'confirmed' : 'held',
                'currency' => $quote->currency,
                'total_amount' => $quote->total_amount,
                'hold_expires_at' => $directOwnerPayments && (int) $quote->total_amount === 0 ? null : $holdExpiresAt,
                'confirmed_at' => $directOwnerPayments && (int) $quote->total_amount === 0 ? now() : null,
                'metadata' => $metadata,
                'payment_model' => $directOwnerPayments ? 'direct_owner_v1' : 'legacy_wallet_v1',
            ]);

            foreach ($quote->slot_snapshot as $slotSnapshot) {
                $slot = ReservationSlot::create([
                    'organization_id' => $organizationId,
                    'reservation_id' => $reservation->id,
                    'court_id' => $quote->court_id,
                    'starts_at' => $slotSnapshot['starts_at'],
                    'ends_at' => $slotSnapshot['ends_at'],
                    'unit_price' => $slotSnapshot['unit_price'],
                    'status' => $directOwnerPayments && (int) $quote->total_amount === 0 ? 'confirmed' : 'held',
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
                    'status' => $directOwnerPayments && (int) $quote->total_amount === 0 ? 'active' : 'held',
                ]);
            }

            $quote->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'accepted_snapshot' => [
                    'quote_token' => $quote->quote_token,
                    'rule_version' => $quote->rule_version,
                    'slot_snapshot' => $quote->slot_snapshot,
                    'subtotal' => $quote->subtotal,
                    'total_amount' => $quote->total_amount,
                    'benefit_snapshot' => $quote->benefit_snapshot,
                ],
            ]);

            if ($directOwnerPayments && (int) $quote->total_amount > 0 && empty($data['defer_payment_initiation'])) {
                app(ReservationPaymentService::class)->initiate($reservation, $user);
            }

            return $reservation->load(['court.venue', 'quote', 'slots', 'entitlements']);
        });
    }

    public function confirm(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation): Reservation {
            $reservation = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if ($reservation->status === 'confirmed') {
                return $reservation->load(['court.venue', 'quote', 'slots', 'entitlements']);
            }
            if ($reservation->status !== 'held') {
                abort(409, 'Reservation is not awaiting confirmation.');
            }
            abort_if($reservation->payment_model === 'direct_owner_v1', 409, 'Direct-owner reservations can only be confirmed by a verified receiving event.');
            if ($reservation->hold_expires_at?->isPast()) {
                $this->release($reservation, 'expired');
                abort(409, 'Reservation hold has expired.');
            }
            $totalAmount = (int) $reservation->total_amount;
            if ($totalAmount > 0) {
                $isSpecialized = ($reservation->metadata['type'] ?? null) === 'recurring_series'
                    || ($reservation->splitGroup && in_array($reservation->splitGroup->status, ['confirmed', 'completed', 'settled'], true))
                    || ($reservation->splitGroup && (int) $reservation->splitGroup->paid_amount >= (int) $reservation->splitGroup->expected_amount)
                    || $reservation->recurringOccurrence()->exists();
                if (! $isSpecialized) {
                    app(WalletAvailabilityService::class)->assertAvailable((int) $reservation->organization_id);
                    $walletService = app(WalletPaymentService::class);
                    $wallet = $walletService->getOrCreateWallet($reservation->user_id, $reservation->organization_id);
                    if ((int) $wallet->balance < $totalAmount) {
                        abort(409, 'Cannot confirm unpaid reservation: insufficient wallet balance.');
                    }

                    $walletTx = $walletService->debit(
                        $wallet,
                        $totalAmount,
                        'reservation_payment',
                        $reservation->id,
                        $reservation->code
                    );

                    app(FinancialPostingService::class)->postWalletReservationPayment(
                        $reservation,
                        $totalAmount,
                        $wallet->id,
                        $walletTx->id
                    );
                }
            }

            $reservation->update(['status' => 'confirmed', 'confirmed_at' => now()]);
            $reservation->slots()->where('status', 'held')->update(['status' => 'confirmed']);
            $reservation->entitlements()->where('status', 'held')->update(['status' => 'active']);

            return $reservation->fresh(['court.venue', 'quote', 'slots', 'entitlements']);
        });
    }

    public function release(Reservation $reservation, string $status): void
    {
        if ($reservation->status === 'held') {
            $this->quoteCoupons->release($reservation);
        }
        $reservation->update([
            'status' => $status,
            'cancelled_at' => $status === 'cancelled' ? now() : $reservation->cancelled_at,
        ]);
        $reservation->slots()->whereIn('status', ['held', 'confirmed'])->update(['status' => $status]);
        $reservation->entitlements()->whereIn('status', ['held', 'active'])->update(['status' => $status]);
    }

    private function findQuote(int $userId, int $organizationId, array $data): ?PriceQuote
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
            return null;
        }

        return $query->lockForUpdate()->firstOrFail();
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'RSV'.strtoupper(Str::random(13));
        } while (Reservation::query()->where('code', $code)->exists());

        return $code;
    }

    private function transactionWithConflictHandling(callable $operation): Reservation
    {
        try {
            return DB::transaction($operation);
        } catch (QueryException $exception) {
            $message = strtolower($exception->getMessage());
            if (Str::contains($message, ['reservation_entitlements_active_unique', 'reservation_slots_no_overlap'])
                || (str_contains($message, 'reservation_entitlements') && str_contains($message, 'unique'))) {
                app(ObservabilityService::class)->metric('reservation.overbooking_conflict');
                abort(409, 'The selected court-time is no longer available.');
            }
            throw $exception;
        }
    }
}
