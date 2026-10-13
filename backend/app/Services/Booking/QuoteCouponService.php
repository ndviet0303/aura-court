<?php

namespace App\Services\Booking;

use App\Models\Coupon;
use App\Models\Court;
use App\Models\PriceQuote;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Takes one use of a quote's coupon when its reservation is created and gives it back once if the hold ends
 * unpaid. A confirmed reservation keeps the use, so released_at stays null on a consumed coupon.
 */
class QuoteCouponService
{
    public function __construct(private readonly DiscountService $discounts) {}

    /** @return array{coupon_id: int, promotion_campaign_id: ?int, reserved_at: string, released_at: null}|null */
    public function reserve(PriceQuote $quote, User $user, Court $court): ?array
    {
        $benefit = (array) ($quote->benefit_snapshot ?? []);
        if (! filled($benefit['coupon_code'] ?? null)) {
            return null;
        }

        $coupon = $this->discounts->reserveCouponUse($benefit['coupon_code'], (int) $quote->organization_id, (int) $quote->subtotal, $user, (int) $court->venue_id, (int) $court->id);
        if ((int) $coupon->id !== (int) ($benefit['coupon_id'] ?? 0)) {
            throw ValidationException::withMessages(['coupon_code' => 'Mã giảm giá không hợp lệ.']);
        }

        return [
            'coupon_id' => (int) $coupon->id,
            'promotion_campaign_id' => $coupon->promotion_campaign_id ? (int) $coupon->promotion_campaign_id : null,
            'reserved_at' => now()->toIso8601String(),
            'released_at' => null,
        ];
    }

    /** Call while the reservation is still held; the row lock makes a retried expiry release nothing twice. */
    public function release(Reservation $reservation): void
    {
        $locked = Reservation::query()->withoutGlobalScope('tenant_isolation')->whereKey($reservation->id)->lockForUpdate()->first();
        $usage = $locked?->metadata['coupon_usage'] ?? null;
        if (! is_array($usage) || ! empty($usage['released_at'])) {
            return;
        }

        $this->discounts->release(Coupon::query()->find((int) $usage['coupon_id']));
        $metadata = [...$locked->metadata, 'coupon_usage' => [...$usage, 'released_at' => now()->toIso8601String()]];
        $locked->forceFill(['metadata' => $metadata])->save();
        $reservation->setAttribute('metadata', $metadata);
        $reservation->syncOriginalAttribute('metadata');
    }
}
