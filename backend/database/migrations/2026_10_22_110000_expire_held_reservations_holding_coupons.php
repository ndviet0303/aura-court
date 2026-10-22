<?php

use App\Models\Reservation;
use App\Services\Reservation\HeldReservationRelease;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Court bookings no longer take coupons (design D28) and the coupon tables go in the next migration (task 4.6). A held
 * reservation that still holds a coupon use, held before checkout stopped reserving coupons or with no payment deadline
 * for the sweep to find, is expired through HeldReservationRelease (the sweep's path) whatever its deadline. Its use is
 * then given back as QuoteCouponService::release did: the snapshot gets released_at and the coupon and its campaign
 * lose one use, never below 0. No notification is sent: the customer did not miss a payment deadline.
 */
return new class extends Migration
{
    private const REASON = 'coupon_retired';

    public function up(): void
    {
        $candidates = Reservation::query()->withoutGlobalScope('tenant_isolation')->where('status', 'held')->orderBy('id')
            ->get(['id', 'code', 'metadata'])
            ->filter(fn (Reservation $reservation) => $this->heldCoupon($reservation) !== null);
        if ($candidates->isEmpty()) {
            return;
        }

        // Resolved only when there is work, so a fresh database never depends on the service.
        $release = app(HeldReservationRelease::class);
        $couponIds = [];
        foreach ($candidates as $candidate) {
            $locked = $release->lock($candidate);
            $usage = $locked ? $this->heldCoupon($locked) : null;
            if ($usage === null || ! $release->releaseHeld($locked, 'expired', self::REASON)) {
                continue;
            }

            $usage['released_at'] = now()->toIso8601String();
            $locked->forceFill(['metadata' => [...(array) $locked->metadata, 'coupon_usage' => $usage]])->save();
            // A snapshot without a coupon id still expires the hold; there is just no use to give back.
            $couponIds[] = (int) ($usage['coupon_id'] ?? 0);
        }

        // Coupons after every reservation, in id order: the release locked the reservation before the coupon.
        sort($couponIds);
        foreach ($couponIds as $couponId) {
            $coupon = DB::table('coupons')->where('id', $couponId)->lockForUpdate()->first();
            if ($coupon === null || (int) $coupon->usage_count <= 0) {
                continue;
            }
            DB::table('coupons')->where('id', $couponId)->decrement('usage_count', 1, ['updated_at' => now()]);
            if ($coupon->promotion_campaign_id !== null) {
                DB::table('promotion_campaigns')->where('id', $coupon->promotion_campaign_id)->where('usage_count', '>', 0)
                    ->decrement('usage_count', 1, ['updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Irreversible on purpose: a restored hold would keep a coupon use that no longer exists.
    }

    private function heldCoupon(Reservation $reservation): ?array
    {
        $usage = $reservation->metadata['coupon_usage'] ?? null;

        return is_array($usage) && empty($usage['released_at']) ? $usage : null;
    }
};
