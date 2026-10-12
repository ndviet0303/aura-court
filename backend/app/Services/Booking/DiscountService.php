<?php

namespace App\Services\Booking;

use App\Models\Coupon;
use App\Models\Court;
use App\Models\MembershipEntitlement;
use App\Models\PromotionCampaign;
use App\Models\Reservation;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiscountService
{
    public function preview(User $user, int $subtotal, ?string $code = null, ?int $organizationId = null, ?int $venueId = null, ?int $courtId = null): array
    {
        $coupon = $this->findCoupon($code, $organizationId);
        if (filled($code)) {
            $this->assertCoupon($coupon, $subtotal, false, $user, $organizationId, $venueId, $courtId);
        }
        $membership = $this->activeMembership($user->id, $organizationId);

        return $this->calculate($subtotal, $coupon, $membership);
    }

    public function reserve(int $userId, int $subtotal, ?string $code = null, ?int $organizationId = null, ?int $venueId = null, ?int $courtId = null): array
    {
        return DB::transaction(function () use ($userId, $subtotal, $code, $organizationId, $venueId, $courtId): array {
            $coupon = $this->reserveCouponUse($code, $organizationId, $subtotal, User::find($userId), $venueId, $courtId);
            $membership = $this->activeMembership($userId, $organizationId);

            return $this->calculate($subtotal, $coupon, $membership);
        });
    }

    public function resolveEligibleCoupon(?string $code, int $organizationId, int $subtotal, ?User $user = null, ?int $venueId = null, ?int $courtId = null): ?Coupon
    {
        if (! filled($code)) {
            return null;
        }

        $coupon = $this->findCoupon($code, $organizationId);
        $this->assertCoupon($coupon, $subtotal, true, $user, $organizationId, $venueId, $courtId);

        return $coupon;
    }

    public function reserveCouponUse(?string $code, ?int $organizationId, int $subtotal, ?User $user = null, ?int $venueId = null, ?int $courtId = null): ?Coupon
    {
        if (! filled($code)) {
            return null;
        }

        $coupon = $this->couponQuery($code, $organizationId)->lockForUpdate()->first();
        $campaign = $coupon?->promotion_campaign_id
            ? PromotionCampaign::query()->where('organization_id', $organizationId)->whereKey($coupon->promotion_campaign_id)->lockForUpdate()->first()
            : null;
        $coupon?->setRelation('campaign', $campaign);
        $this->assertCoupon($coupon, $subtotal, true, $user, $organizationId, $venueId, $courtId);

        $coupon->increment('usage_count');
        $campaign?->increment('usage_count');

        return $coupon->fresh('campaign');
    }

    public function release(?Coupon $coupon): void
    {
        if (! $coupon) {
            return;
        }

        $locked = Coupon::query()->with('campaign')->whereKey($coupon->id)->lockForUpdate()->first();
        if ($locked && $locked->usage_count > 0) {
            $locked->decrement('usage_count');
            if ($locked->promotion_campaign_id) {
                $campaign = PromotionCampaign::query()->whereKey($locked->promotion_campaign_id)->lockForUpdate()->first();
                if ($campaign && $campaign->usage_count > 0) {
                    $campaign->decrement('usage_count');
                }
            }
        }
    }

    public function assertCombinationAllowed(?Coupon $coupon, int $couponDiscount, int $membershipDiscount): void
    {
        if ($coupon && $couponDiscount > 0 && $membershipDiscount > 0 && ! $this->canCombineWithMembership($coupon)) {
            throw ValidationException::withMessages(['coupon_code' => 'Mã giảm giá không thể kết hợp cùng ưu đãi hội viên trên cùng một đơn hàng.']);
        }
    }

    private function findCoupon(?string $code, ?int $organizationId): ?Coupon
    {
        if (! filled($code)) {
            return null;
        }

        return $this->couponQuery($code, $organizationId)->first();
    }

    private function couponQuery(string $code, ?int $organizationId)
    {
        return Coupon::query()
            ->with('campaign')
            ->where('code', strtoupper(trim($code)))
            ->whereNotNull('organization_id')
            ->where('organization_id', $organizationId);
    }

    private function assertCoupon(?Coupon $coupon, int $subtotal, bool $checkSpend = true, ?User $user = null, ?int $organizationId = null, ?int $venueId = null, ?int $courtId = null): void
    {
        if (! $coupon || ! $coupon->is_active || ($coupon->status !== null && $coupon->status !== Coupon::STATUS_ACTIVE)) {
            throw ValidationException::withMessages(['coupon_code' => 'Mã giảm giá không hợp lệ.']);
        }
        if ($organizationId === null || $coupon->organization_id === null || (int) $coupon->organization_id !== $organizationId) {
            throw ValidationException::withMessages(['coupon_code' => 'Mã giảm giá không hợp lệ cho tổ chức này.']);
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

        $campaign = $coupon->campaign;
        if ($coupon->promotion_campaign_id !== null) {
            if (! $campaign
                || $campaign->status !== PromotionCampaign::STATUS_ACTIVE
                || (int) $campaign->organization_id !== $organizationId
                || $campaign->starts_at?->isFuture()
                || $campaign->ends_at?->isPast()
                || ($campaign->usage_limit !== null && $campaign->usage_count >= $campaign->usage_limit)
                || ($checkSpend && $subtotal < (int) $campaign->min_spend)) {
                throw ValidationException::withMessages(['coupon_code' => 'Chiến dịch chưa có hiệu lực hoặc không còn áp dụng.']);
            }
        }

        $this->assertCouponLocation($coupon, $campaign, $organizationId, $venueId, $courtId);

        $group = (string) ($campaign?->eligibility_group ?? $coupon->eligibility_group ?? Coupon::GROUP_ALL);
        $eligibilityVenueId = $coupon->venue_id ?? $campaign?->venue_id;
        $eligibilityCourtId = $coupon->court_id ?? $campaign?->court_id;
        if ($group !== Coupon::GROUP_ALL && (! $user || ! $this->matchesCustomerGroup($user, $organizationId, $group, $eligibilityVenueId, $eligibilityCourtId))) {
            throw ValidationException::withMessages(['coupon_code' => 'Tài khoản của bạn không thuộc nhóm khách hàng áp dụng mã này.']);
        }
    }

    private function assertCouponLocation(Coupon $coupon, ?PromotionCampaign $campaign, int $organizationId, ?int $venueId, ?int $courtId): void
    {
        $venue = null;
        $court = null;
        if ($courtId !== null) {
            $court = Court::query()->with('venue')->whereKey($courtId)->first();
            if (! $court || ! $court->venue || (int) $court->venue->organization_id !== $organizationId
                || ($venueId !== null && (int) $court->venue_id !== $venueId)) {
                throw ValidationException::withMessages(['coupon_code' => 'Mã giảm giá không áp dụng cho cơ sở hoặc sân này.']);
            }
            $venueId = (int) $court->venue_id;
        }

        if ($venueId !== null) {
            $venue = Venue::query()->where('organization_id', $organizationId)->whereKey($venueId)->first();
            if (! $venue) {
                throw ValidationException::withMessages(['coupon_code' => 'Mã giảm giá không áp dụng cho cơ sở hoặc sân này.']);
            }
        }

        foreach ([$campaign, $coupon] as $benefit) {
            if (($benefit?->venue_id !== null && (int) $benefit->venue_id !== $venueId)
                || ($benefit?->court_id !== null && (int) $benefit->court_id !== $courtId)) {
                throw ValidationException::withMessages(['coupon_code' => 'Mã giảm giá không áp dụng cho cơ sở hoặc sân này.']);
            }
        }
    }

    private function matchesCustomerGroup(User $user, int $organizationId, string $group, ?int $venueId, ?int $courtId): bool
    {
        if (! in_array($group, [Coupon::GROUP_FIRST_TIME, Coupon::GROUP_RETURNING], true)) {
            return false;
        }

        $successful = ['confirmed', 'in_use', 'completed'];
        $allTenants = fn ($mapping) => $mapping->withoutGlobalScope('tenant_isolation');
        // A 'legacy' mirror counts by its V1 status, any other reservation by its own (same rule as the report).
        $hasSuccessfulBooking = Reservation::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->where(fn ($query) => $query
                ->whereIn('status', $successful)
                ->orWhere(fn ($legacy) => $legacy->where('status', 'legacy')->whereHas('legacyMapping', fn ($mapping) => $allTenants($mapping)->whereIn('legacy_status', $successful))))
            ->when($courtId !== null, fn ($query) => $query->where('court_id', $courtId))
            ->when($venueId !== null, fn ($query) => $query->whereHas('court', fn ($scope) => $scope->where('venue_id', $venueId)))
            ->exists();

        return $group === Coupon::GROUP_RETURNING ? $hasSuccessfulBooking : ! $hasSuccessfulBooking;
    }

    private function activeMembership(int $userId, ?int $organizationId): ?TenantMembership
    {
        if ($organizationId === null) {
            return null;
        }

        $now = now();

        return TenantMembership::query()->with([
            'plan',
            'entitlements' => fn ($query) => $query
                ->where('organization_id', $organizationId)
                ->where('starts_at', '<=', $now)
                ->where('ends_at', '>=', $now),
        ])
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->whereHas('plan', fn ($query) => $query->where('organization_id', $organizationId))
            ->latest('ends_at')
            ->first();
    }

    private function calculate(int $subtotal, ?Coupon $coupon, ?TenantMembership $membership): array
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

        [$membershipDiscount, $membershipSnapshot] = $this->membershipDiscount($membership, $subtotal);
        $this->assertCombinationAllowed($coupon, $couponDiscount, $membershipDiscount);
        $discount = min($subtotal, $couponDiscount + $membershipDiscount);

        return [
            'coupon' => $coupon,
            'membership' => $membership,
            'membership_snapshot' => $membershipSnapshot,
            'coupon_discount' => $couponDiscount,
            'membership_discount' => $membershipDiscount,
            'discount' => $discount,
            'total' => max(0, $subtotal - $discount),
        ];
    }

    private function canCombineWithMembership(Coupon $coupon): bool
    {
        return (bool) ($coupon->campaign?->can_combine_with_membership
            ?? $coupon->can_combine_with_membership
            ?? ($coupon->metadata['can_combine_with_membership'] ?? true));
    }

    /** @return array{0: int, 1: ?array} */
    private function membershipDiscount(?TenantMembership $membership, int $subtotal): array
    {
        $entitlement = $membership?->entitlements
            ->first(fn (MembershipEntitlement $item) => in_array($item->benefit_code, ['court_discount_percent', 'booking_discount_percent', 'discount_percent'], true));
        if (! $membership || ! $entitlement) {
            return [0, null];
        }

        $value = $entitlement->benefit_value;
        $percent = is_numeric($value)
            ? (int) $value
            : (int) ($value['percent'] ?? $value['discount_percent'] ?? $value['value'] ?? 0);
        $percent = min(100, max(0, $percent));
        $discount = (int) floor($subtotal * $percent / 100);

        if ($discount <= 0) {
            return [0, null];
        }

        return [$discount, [
            'membership_id' => (int) $membership->id,
            'organization_id' => (int) $membership->organization_id,
            'plan_id' => (int) $membership->plan_id,
            'plan_version' => (int) $membership->plan_version,
            'entitlement_id' => (int) $entitlement->id,
            'benefit_code' => $entitlement->benefit_code,
            'benefit_value' => $value,
            'starts_at' => $entitlement->starts_at?->toIso8601String(),
            'ends_at' => $entitlement->ends_at?->toIso8601String(),
            'discount_amount' => $discount,
        ]];
    }
}
