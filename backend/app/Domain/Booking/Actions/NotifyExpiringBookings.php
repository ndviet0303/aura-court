<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use App\Notifications\AuraBookingNotification;
use Illuminate\Support\Facades\DB;

class NotifyExpiringBookings
{
    public function execute(): int
    {
        return DB::transaction(function () {
            $bookings = Booking::query()->with('user')->where('status', 'pending_payment')
                ->whereNull('payment_expiring_notified_at')
                ->whereBetween('payment_expires_at', [now(), now()->addMinutes(5)])
                ->lockForUpdate()->get();

            foreach ($bookings as $booking) {
                $booking->update(['payment_expiring_notified_at' => now()]);
                $booking->user->notify(new AuraBookingNotification(
                    'booking_expiring',
                    'Sắp hết thời gian giữ sân',
                    "Booking {$booking->code} sẽ hết hạn thanh toán trong khoảng 5 phút.",
                    '/payment/'.$booking->code,
                ));
            }

            return $bookings->count();
        });
    }
}
