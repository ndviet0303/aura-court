<?php

namespace App\Domain\Booking\Actions;

use App\Contracts\Repositories\BookingRepositoryInterface;
use App\Contracts\Repositories\CourtBlockRepositoryInterface;
use App\Contracts\Repositories\CourtPriceSlotRepositoryInterface;
use App\Contracts\Repositories\CourtRepositoryInterface;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\ReservationEntitlement;
use App\Notifications\AuraBookingNotification;
use App\Services\Booking\WaitlistService;
use App\Services\Finance\FinancialPostingService;
use App\Services\Payment\WalletPaymentService;
use App\Services\Reservation\LegacyBookingBackfillService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RescheduleBooking
{
    public function __construct(
        private readonly CourtRepositoryInterface $courts,
        private readonly CourtPriceSlotRepositoryInterface $priceSlots,
        private readonly CourtBlockRepositoryInterface $courtBlocks,
        private readonly BookingRepositoryInterface $bookings,
        private readonly WaitlistService $waitlists,
        private readonly WalletPaymentService $walletPayments,
        private readonly FinancialPostingService $financials,
        private readonly LegacyBookingBackfillService $backfill,
    ) {}

    public function execute(Booking $booking, int $courtId, array $startsAt): Booking
    {
        if (! in_array($booking->status, ['pending_payment', 'confirmed'], true)) {
            throw ValidationException::withMessages(['booking' => 'Booking không thể đổi lịch.']);
        }
        $firstSlot = $booking->slots()->orderBy('starts_at')->first();
        if ($booking->status === 'confirmed' && $firstSlot && now()->greaterThan($firstSlot->starts_at->copy()->subHours(2))) {
            abort(409, 'Booking chỉ được đổi trước giờ chơi ít nhất 2 giờ.');
        }

        // Validate new slot inputs
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

        $court = $this->courts->findActiveWithActiveVenue($courtId);
        abort_unless($court, 404, 'Sân không tồn tại hoặc đã ngừng hoạt động.');

        try {
            $replacement = DB::transaction(function () use ($booking, $court, $local) {
                $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
                $oldSlots = $booking->slots()->whereIn('status', ['held', 'confirmed'])->lockForUpdate()->get();
                $oldSlotRefs = $oldSlots->map(fn (BookingSlot $s) => [$s->court_id, $s->starts_at])->all();
                $oldPaid = (int) $booking->total_amount;

                // Check blocks for new court/time
                $hasBlock = $this->courtBlocks->hasOverlappingBlock(
                    $court,
                    $local->first()->copy()->utc(),
                    $local->last()->copy()->addHour()->utc()
                );
                abort_if($hasBlock, 409, 'Sân đang bị khóa trong khoảng thời gian đã chọn.');

                // Check conflict with other bookings (excluding current booking's slots)
                $startUtcList = $local->map(fn (Carbon $t) => $t->copy()->utc())->all();
                $v1Conflict = BookingSlot::query()
                    ->where('court_id', $court->id)
                    ->whereIn('starts_at', $startUtcList)
                    ->whereIn('status', ['held', 'confirmed'])
                    ->where('booking_id', '!=', $booking->id)
                    ->lockForUpdate()
                    ->exists();
                if ($v1Conflict) {
                    abort(409, 'Một hoặc nhiều slot vừa được người khác đặt.');
                }

                // Check conflict with V2 entitlements (excluding ones mapped to current booking)
                $v2ConflictQuery = ReservationEntitlement::query()
                    ->where('court_id', $court->id)
                    ->whereIn('starts_at', $startUtcList)
                    ->whereIn('status', ['held', 'active'])
                    ->lockForUpdate();
                $mappedReservationId = $booking->legacyMapping()->value('reservation_id');
                if ($mappedReservationId) {
                    $v2ConflictQuery->where('reservation_id', '!=', $mappedReservationId);
                }
                if ($v2ConflictQuery->exists()) {
                    abort(409, 'Một hoặc nhiều slot vừa được người khác đặt.');
                }

                // Calculate price of new slots
                $prices = $this->priceSlots->getActiveForCourt($court);
                $resolved = $local->map(function (Carbon $time) use ($prices) {
                    $price = $prices->first(fn ($slot) => $slot->weekday === $time->dayOfWeekIso
                        && substr($slot->starts_at, 0, 5) === $time->format('H:i')
                        && substr($slot->ends_at, 0, 5) === $time->copy()->addHour()->format('H:i'));
                    if (! $price) {
                        throw ValidationException::withMessages(['slots' => "Slot {$time->format('H:i')} chưa mở bán."]);
                    }

                    return [$time, (int) $price->price];
                });

                $newTotal = $resolved->sum(fn ($row) => $row[1]);
                $priceDifference = $newTotal - $oldPaid;

                $venue = $court->venue;
                $orgId = $venue?->organization_id;
                $wallet = $this->walletPayments->getOrCreateWallet($booking->user_id, $orgId);

                // Handle wallet price difference
                if ($priceDifference > 0) {
                    if ((int) $wallet->balance < $priceDifference) {
                        throw ValidationException::withMessages([
                            'wallet' => "Số dư ví không đủ để thanh toán phần chênh lệch đổi lịch (cần thêm " . number_format($priceDifference) . " VND).",
                        ]);
                    }

                    $this->walletPayments->debit(
                        $wallet,
                        $priceDifference,
                        'booking_reschedule_payment',
                        $booking->id,
                        $booking->code
                    );
                } elseif ($priceDifference < 0) {
                    $refundAmount = abs($priceDifference);
                    $this->walletPayments->credit(
                        $wallet,
                        $refundAmount,
                        'booking_reschedule_refund',
                        $booking->id,
                        "Hoàn chênh lệch đổi lịch {$booking->code}"
                    );
                }

                // Release old booking and slots
                $oldSlots->each(fn (BookingSlot $s) => $s->update(['status' => 'cancelled']));
                $replacementCode = 'BK'.strtoupper(Str::random(12));
                $booking->update([
                    'status' => 'refunded',
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'Đổi lịch sang booking '.$replacementCode,
                ]);

                // Create replacement booking
                $replacement = $this->bookings->create([
                    'code' => $replacementCode,
                    'user_id' => $booking->user_id,
                    'court_id' => $court->id,
                    'coupon_id' => null,
                    'total_amount' => $newTotal,
                    'discount_amount' => 0,
                    'membership_discount_amount' => 0,
                    'status' => 'confirmed',
                    'payment_expires_at' => null,
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
                $this->bookings->createSlots($replacement, $slotsData);

                // Create payment record for replacement
                $payment = $replacement->payments()->create([
                    'provider' => 'wallet',
                    'external_transaction_id' => 'wallet-reschedule-'.$booking->id.'-'.uniqid(),
                    'amount' => $newTotal,
                    'transfer_code' => $replacement->code,
                    'status' => 'paid',
                    'payload' => [
                        'wallet_account_id' => $wallet->id,
                        'rescheduled_from_booking_id' => $booking->id,
                        'rescheduled_from_code' => $booking->code,
                        'price_difference' => $priceDifference,
                    ],
                    'received_at' => now(),
                ]);

                // Financial posting for the delta
                if ($priceDifference > 0) {
                    $this->financials->postWalletBookingPayment($replacement->fresh(['court.venue']), $payment, $wallet->id);
                } elseif ($priceDifference < 0) {
                    $this->financials->postWalletBookingRefund($booking->fresh(['court.venue']), abs($priceDifference), $wallet->id);
                }

                // Sync canonical mapping
                $this->backfill->backfillBooking($replacement);

                // Notify waitlists for released old slots
                foreach ($oldSlotRefs as [$cId, $sAt]) {
                    $this->waitlists->notifyForSlot($cId, $sAt);
                }

                return $replacement->load(['court.venue', 'slots', 'payments']);
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

        $booking->user?->notify(new AuraBookingNotification(
            'booking_rescheduled',
            'Đổi lịch sân thành công',
            "Booking {$booking->code} đã được đổi sang {$replacement->code} tại {$court->name}.",
            '/booking',
        ));

        return $replacement;
    }
}
