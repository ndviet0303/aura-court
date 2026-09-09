<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Services\Booking\WaitlistService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelBookingSlots
{
    public function __construct(
        private readonly CancelBooking $cancelBookingAction,
        private readonly WaitlistService $waitlists,
        private readonly \App\Services\Payment\WalletPaymentService $walletPayments,
        private readonly \App\Services\Finance\FinancialPostingService $financials,
    ) {}

    public function execute(Booking $booking, array $slotIds, ?string $reason = null): Booking
    {
        $slots = $booking->slots()->whereIn('id', $slotIds)->whereIn('status', ['held', 'confirmed'])->get();
        if ($slots->isEmpty()) {
            throw ValidationException::withMessages(['slots' => 'Không có slot hợp lệ để hủy.']);
        }
        if ($slots->count() === $booking->slots()->whereIn('status', ['held', 'confirmed'])->count()) {
            return $this->cancelBookingAction->execute($booking, $reason);
        }
        if ($booking->status !== 'confirmed') {
            throw ValidationException::withMessages(['booking' => 'Chỉ booking đã xác nhận mới được hủy từng slot.']);
        }
        foreach ($slots as $slot) {
            if (now()->greaterThan($slot->starts_at->copy()->subHours(2))) {
                abort(409, 'Slot chỉ được hủy trước giờ chơi ít nhất 2 giờ.');
            }
        }

        $slotRefs = $slots->map(fn (BookingSlot $slot) => [$slot->court_id, $slot->starts_at])->all();
        $result = DB::transaction(function () use ($booking, $slots, $reason) {
            $amount = (int) $slots->sum('unit_price');
            $slots->each->update(['status' => 'cancelled']);

            // Sync cancelled slot mappings to V2
            $slotIds = $slots->pluck('id')->all();
            \App\Models\LegacyBookingSlotMapping::query()
                ->whereIn('legacy_booking_slot_id', $slotIds)
                ->with(['reservationSlot'])
                ->get()
                ->each(function ($mapping) {
                    $mapping->reservationSlot?->update(['status' => 'cancelled']);
                    \App\Models\ReservationEntitlement::query()
                        ->where('reservation_slot_id', $mapping->reservation_slot_id)
                        ->update(['status' => 'cancelled']);
                });
            if ($amount > 0) {
                $booking->loadMissing('court.venue');
                $orgId = $booking->court?->venue?->organization_id;
                $wallet = $this->walletPayments->getOrCreateWallet($booking->user_id, $orgId);
                $this->walletPayments->credit(
                    $wallet,
                    $amount,
                    'booking_partial_refund',
                    $booking->id,
                    "Partial refund for {$booking->code}"
                );
                $this->financials->postWalletBookingRefund($booking->fresh(['court.venue']), $amount, $wallet->id);
            }

            $booking->decrement('total_amount', $amount);
            $booking->refunds()->create(['amount' => $amount, 'status' => 'completed', 'note' => $reason]);

            $remainingActive = $booking->slots()->whereIn('status', ['held', 'confirmed'])->count();
            if ($remainingActive === 0) {
                $booking->update(['status' => 'refunded', 'cancelled_at' => now()]);
            }

            return $booking->fresh(['court', 'slots', 'refunds']);
        });

        foreach ($slotRefs as [$courtId, $startsAt]) {
            $this->waitlists->notifyForSlot($courtId, $startsAt);
        }

        return $result;
    }
}
