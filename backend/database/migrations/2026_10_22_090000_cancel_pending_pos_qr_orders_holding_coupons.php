<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * POS no longer takes coupons (design D12, task 4.5) and the coupon tables go next (task 4.6). A pending POS QR
 * order that reserved a coupon is expired the way PosService::cancelPendingQr cancels one, and its coupon use is
 * released as DiscountService::release did. The scheduled TTL expiry (pos:release-expired-orders) no longer
 * releases coupons and skips inactive organizations, so every such order is expired here, whatever its TTL.
 */
return new class extends Migration
{
    private const REASON = 'coupon_retired';

    public function up(): void
    {
        $orderIds = DB::table('pos_orders')->where('status', 'pending_payment')->orderBy('id')->pluck('metadata', 'id')
            ->filter(fn (?string $metadata) => (json_decode($metadata ?? '', true)['coupon_usage']['status'] ?? null) === 'reserved')
            ->keys();
        $couponIds = [];

        foreach ($orderIds as $orderId) {
            // Receipt processing locks the attempt before the order; keep that order.
            DB::table('owner_payment_attempts')->where('purpose', 'pos_order')->where('purpose_id', (string) $orderId)->lockForUpdate()->get(['id']);
            $order = DB::table('pos_orders')->where('id', $orderId)->lockForUpdate()->first();
            $meta = json_decode($order->metadata ?? '', true) ?? [];
            if ($order->status !== 'pending_payment' || ($meta['coupon_usage']['status'] ?? null) !== 'reserved') {
                continue;
            }

            $now = now();
            DB::table('owner_payment_attempts')->where('purpose', 'pos_order')->where('purpose_id', (string) $orderId)
                ->where('status', 'pending')->update(['status' => 'expired', 'updated_at' => $now]);
            DB::table('pos_payments')->where('order_id', $orderId)->where('status', 'pending')->update(['status' => 'cancelled', 'updated_at' => $now]);

            if (isset($meta['receipt'])) {
                $meta['receipt'] = [...$meta['receipt'], 'status' => 'cancelled', 'is_paid' => false, 'cancelled_at' => $now->toIso8601String(), 'cancel_reason' => self::REASON];
            }
            $meta['coupon_usage'] = [...$meta['coupon_usage'], 'status' => 'released', 'released_at' => $now->toIso8601String()];
            DB::table('pos_orders')->where('id', $orderId)->update([
                'status' => 'cancelled',
                'metadata' => json_encode([...$meta, 'cancelled_at' => $now->toIso8601String(), 'cancel_reason' => self::REASON]),
                'updated_at' => $now,
            ]);
            $couponIds[] = (int) ($meta['coupon_usage']['coupon_id'] ?? 0);
        }

        // Coupons after every order, in id order, as cancelPendingQr locked the order before the coupon.
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
        // Irreversible on purpose: a restored pending order would hold a coupon that no longer exists.
    }
};
