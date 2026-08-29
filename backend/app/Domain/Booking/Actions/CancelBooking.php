<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Services\Booking\DiscountService;
use App\Services\Booking\WaitlistService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelBooking
{
    public function __construct(
        private readonly DiscountService $discounts,
        private readonly WaitlistService $waitlists,
    ) {}

    public function execute(Booking $booking, ?string $reason = null): Booking
    {
        if (in_array($booking->status, ['cancelled', 'expired', 'refunded'], true)) {
            throw ValidationException::withMessages(['booking' => 'Booking đã kết thúc.']);
        }
        $firstSlot = $booking->slots()->orderBy('starts_at')->firstOrFail();
        if ($booking->status === 'confirmed' && now()->greaterThan($firstSlot->starts_at->copy()->subHours(2))) {
            abort(409, 'Booking chỉ được hủy trước giờ chơi ít nhất 2 giờ.');
        }

        $slotRefs = $booking->slots()->whereIn('status', ['held', 'confirmed'])->get()->map(fn (BookingSlot $slot) => [$slot->court_id, $slot->starts_at])->all();
        $wasPending = $booking->status === 'pending_payment';
        $result = DB::transaction(function () use ($booking, $reason, $wasPending) {
            $newStatus = $booking->status === 'confirmed' ? 'refund_pending' : 'cancelled';
            $booking->update(['status' => $newStatus, 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
            $booking->slots()->whereIn('status', ['held', 'confirmed'])->update(['status' => 'cancelled']);
            if ($newStatus === 'refund_pending') {
                $booking->refunds()->firstOrCreate(['status' => 'pending'], ['amount' => $booking->total_amount]);
            }
            if ($wasPending && $booking->coupon_id && ! $booking->coupon_usage_released_at) {
                $this->discounts->release($booking->coupon);
                $booking->update(['coupon_usage_released_at' => now()]);
            }

            return $booking->fresh(['court', 'slots', 'refunds']);
        });

        foreach ($slotRefs as [$courtId, $startsAt]) {
            $this->waitlists->notifyForSlot($courtId, $startsAt);
        }

        return $result;
    }
}
