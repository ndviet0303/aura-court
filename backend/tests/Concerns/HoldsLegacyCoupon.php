<?php

namespace Tests\Concerns;

use App\Models\Coupon;
use App\Models\Reservation;
use Carbon\CarbonInterface;

/**
 * A coupon use held by a booking made before court bookings stopped taking coupons (design D28). Checkout no
 * longer holds one, but releasing an unpaid hold still gives it back until coupons are removed (task 4.6).
 */
trait HoldsLegacyCoupon
{
    protected function holdLegacyCoupon(Reservation $reservation, ?CarbonInterface $endsAt = null): Coupon
    {
        $coupon = Coupon::create([
            'organization_id' => $reservation->organization_id,
            'code' => 'SAVE20K',
            'discount_type' => 'fixed',
            'discount_value' => 20000,
            'is_active' => true,
            'status' => Coupon::STATUS_ACTIVE,
            'usage_count' => 1,
            'ends_at' => $endsAt,
        ]);
        $reservation->forceFill(['metadata' => [...(array) $reservation->metadata, 'coupon_usage' => [
            'coupon_id' => $coupon->id,
            'promotion_campaign_id' => null,
            'reserved_at' => now()->toIso8601String(),
            'released_at' => null,
        ]]])->save();

        return $coupon;
    }
}
