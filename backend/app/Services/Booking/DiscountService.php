<?php

namespace App\Services\Booking;

use App\Models\Coupon;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DiscountService
{
    public function preview(User $user, int $subtotal, ?string $code = null): array
    {
        $coupon = $this->findCoupon($code);
        $membership = $this->activeMembership($user->id);

        return $this->calculate($subtotal, $coupon, $membership);
    }

    public function reserve(int $userId, int $subtotal, ?string $code = null): array
    {
        $coupon = null;
        if (filled($code)) {
            $coupon = Coupon::query()->where('code', strtoupper(trim($code)))->lockForUpdate()->first();
            $this->assertCoupon($coupon, $subtotal);
            if ($coupon->usage_limit !== null && $coupon->usage_count >= $coupon->usage_limit) {
                throw ValidationException::withMessages(['coupon_code' => 'Mã giảm giá đã hết lượt sử dụng.']);
            }
            $coupon->increment('usage_count');
        }

        $membership = $this->activeMembership($userId);

        return $this->calculate($subtotal, $coupon, $membership);
    }

    public function release(?Coupon $coupon): void
    {
        if (! $coupon) {
            return;
        }

        $locked = Coupon::query()->whereKey($coupon->id)->lockForUpdate()->first();
        if ($locked && $locked->usage_count > 0) {
            $locked->decrement('usage_count');
        }
    }

    private function findCoupon(?string $code): ?Coupon
    {
        if (! filled($code)) {
            return null;
        }

        $coupon = Coupon::query()->where('code', strtoupper(trim($code)))->first();
        $this->assertCoupon($coupon, 0, false);

        return $coupon;
    }

    private function assertCoupon(?Coupon $coupon, int $subtotal, bool $checkSpend = true): void
    {
        if (! $coupon || ! $coupon->is_active) {
            throw ValidationException::withMessages(['coupon_code' => 'Mã giảm giá không hợp lệ.']);
        }
        if ($coupon->starts_at?->isFuture() || $coupon->ends_at?->isPast()) {
            throw ValidationException::withMessages(['coupon_code' => 'Mã giảm giá chưa có hiệu lực hoặc đã hết hạn.']);
        }
        if ($coupon->usage_limit !== null && $coupon->usage_count >= $coupon->usage_limit) {
            throw ValidationException::withMessages(['coupon_code' => 'Mã giảm giá đã hết lượt sử dụng.']);
        }
        if ($checkSpend && $subtotal < $coupon->min_spend) {
            throw ValidationException::withMessages(['coupon_code' => 'Đơn tối thiểu để dùng mã là '.number_format($coupon->min_spend, 0, ',', '.').'₫.']);
        }
    }

    private function activeMembership(int $userId): ?Membership
    {
        return Membership::query()->with('plan')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->whereHas('plan', fn ($query) => $query->where('is_active', true))
            ->latest('ends_at')
            ->first();
    }

    private function calculate(int $subtotal, ?Coupon $coupon, ?Membership $membership): array
    {
        $couponDiscount = 0;
        if ($coupon && $subtotal >= $coupon->min_spend) {
            $couponDiscount = $coupon->discount_type === 'percent'
                ? (int) floor($subtotal * min(100, $coupon->discount_value) / 100)
                : min($subtotal, $coupon->discount_value);
            if ($coupon->max_discount !== null) {
                $couponDiscount = min($couponDiscount, $coupon->max_discount);
            }
        }

        $membershipDiscount = $membership?->plan
            ? (int) floor($subtotal * min(100, $membership->plan->discount_percent) / 100)
            : 0;
        $discount = min($subtotal, $couponDiscount + $membershipDiscount);

        return [
            'coupon' => $coupon,
            'membership' => $membership,
            'coupon_discount' => $couponDiscount,
            'membership_discount' => $membershipDiscount,
            'discount' => $discount,
            'total' => max(0, $subtotal - $discount),
        ];
    }
}
