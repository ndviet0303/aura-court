<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Services\WaitlistService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelBookingSlots
{
    public function __construct(
        private readonly CancelBooking $cancelBookingAction,
        private readonly WaitlistService $waitlists,
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
            $amount = $slots->sum('unit_price');
            $slots->each->update(['status' => 'cancelled']);
            $booking->refunds()->create(['amount' => $amount, 'status' => 'pending', 'note' => $reason]);

            return $booking->fresh(['court', 'slots', 'refunds']);
        });

        foreach ($slotRefs as [$courtId, $startsAt]) {
            $this->waitlists->notifyForSlot($courtId, $startsAt);
        }

        return $result;
    }
}
