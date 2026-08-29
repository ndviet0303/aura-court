<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use App\Services\Booking\DiscountService;
use Illuminate\Support\Facades\DB;

class ApplyBookingCoupon
{
    public function __construct(
        private readonly DiscountService $discounts,
    ) {}

    public function execute(Booking $booking, ?string $couponCode): Booking
    {
        abort_unless($booking->status === 'pending_payment', 422, 'Booking không ở trạng thái chờ thanh toán.');
        abort_if($booking->payment_expires_at?->isPast(), 422, 'Phiên thanh toán đã hết hạn.');

        return DB::transaction(function () use ($booking, $couponCode) {
            $booking->loadMissing(['slots', 'coupon']);
            $subtotal = (int) $booking->slots->sum('unit_price');

            if ($booking->coupon_id) {
                $this->discounts->release($booking->coupon);
            }

            $discount = $this->discounts->reserve($booking->user_id, $subtotal, $couponCode);

            $booking->update([
                'coupon_id' => $discount['coupon']?->id,
                'total_amount' => $discount['total'],
                'discount_amount' => $discount['discount'],
                'membership_discount_amount' => $discount['membership_discount'],
            ]);

            return $booking->fresh(['user', 'court.venue', 'slots', 'coupon']);
        });
    }
}
