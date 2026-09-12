<?php

namespace App\Domain\Membership\Actions;

use App\Contracts\Repositories\TenantMembershipPlanRepositoryInterface;
use Illuminate\Support\Collection;

class GetAdminMembershipPlans
{
    public function __construct(
        private readonly TenantMembershipPlanRepositoryInterface $planRepository,
    ) {}

    public function execute(int $organizationId): Collection
    {
        $plans = $this->planRepository->getForOrganization($organizationId);

        return $plans->map(function ($plan) {
            $benefits = $plan->benefits ?? [];

            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => str($plan->name)->slug()->toString(),
                'code' => $benefits['code'] ?? strtoupper(substr(str_replace(' ', '', $plan->name), 0, 4)),
                'description' => $benefits['description'] ?? '',
                'price' => (int) $plan->price,
                'billingCycle' => $benefits['billing_cycle'] ?? $benefits['billingCycle'] ?? 'monthly',
                'version' => 'v' . $plan->version . '.0',
                'status' => $plan->status === 'published' ? 'active' : ($plan->status === 'archived' ? 'archived' : 'draft'),
                'perks' => $benefits['perks'] ?? [
                    'Giảm ' . ($benefits['discount_percent'] ?? $benefits['discount'] ?? 10) . '% đặt sân giờ vàng',
                    'Tích lũy x' . ($benefits['point_multiplier'] ?? 1.5) . ' điểm thưởng',
                    'Đặt sân trước ' . ($benefits['advance_booking_days'] ?? 7) . ' ngày',
                ],
                'courtDiscountPercent' => (int) ($benefits['discount_percent'] ?? $benefits['discount'] ?? 10),
                'pointMultiplier' => (float) ($benefits['point_multiplier'] ?? 1.5),
                'advanceBookingDays' => (int) ($benefits['advance_booking_days'] ?? 7),
                'freeWeekendHours' => (int) ($benefits['free_weekend_hours'] ?? 0),
                'cancellationFeeWaived' => false,
                'activeSubscribers' => (int) ($plan->active_subscribers ?? 0),
                'isPopular' => (bool) ($benefits['is_popular'] ?? $benefits['isPopular'] ?? false),
                'highlightColor' => $benefits['highlight_color'] ?? ($benefits['is_popular'] ?? false ? 'gold' : null),
                'updatedAt' => $plan->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            ];
        });
    }
}
