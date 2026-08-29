<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use App\Notifications\AuraBookingNotification;
use App\Services\Booking\DiscountService;
use App\Services\Booking\WaitlistService;
use Illuminate\Support\Facades\DB;

class ExpirePendingBookings
{
    public function __construct(
        private readonly DiscountService $discounts,
        private readonly WaitlistService $waitlists,
    ) {}

    public function execute(): int
    {
        $result = DB::transaction(function () {
            $bookings = Booking::query()->where('status', 'pending_payment')
                ->where('payment_expires_at', '<', now())->lockForUpdate()->get();
            $slots = [];
            foreach ($bookings as $booking) {
                $booking->loadMissing(['user', 'coupon', 'slots']);
                foreach ($booking->slots->where('status', 'held') as $slot) {
                    $slots[] = [$slot->court_id, $slot->starts_at];
                }
                $booking->update(['status' => 'expired']);
                $booking->slots()->where('status', 'held')->update(['status' => 'expired']);
                if ($booking->coupon_id && ! $booking->coupon_usage_released_at) {
                    $this->discounts->release($booking->coupon);
                    $booking->update(['coupon_usage_released_at' => now()]);
                }
            }

            return ['count' => $bookings->count(), 'bookings' => $bookings, 'slots' => $slots];
        });

        foreach ($result['bookings'] as $booking) {
            $booking->user->notify(new AuraBookingNotification(
                'booking_expired',
                'Phiên giữ sân đã hết hạn',
                "Booking {$booking->code} đã hết thời gian thanh toán và được giải phóng.",
                '/booking',
            ));
        }

        foreach ($result['slots'] as [$courtId, $startsAt]) {
            $this->waitlists->notifyForSlot($courtId, $startsAt);
        }

        return $result['count'];
    }
}
