<?php

namespace App\Domain\Membership\Actions;

use App\Contracts\Repositories\TenantMembershipPlanRepositoryInterface;
use App\Models\TenantMembershipPlan;

class CreateAdminMembershipPlan
{
    public function __construct(
        private readonly TenantMembershipPlanRepositoryInterface $planRepository,
    ) {}

    public function execute(int $organizationId, array $data): TenantMembershipPlan
    {
        if (array_key_exists('cancellationFeeWaived', $data) || array_key_exists('cancellation_fee_waived', $data)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'cancellationFeeWaived' => 'Membership cancellation fee waiver is no longer supported.',
            ]);
        }

        $benefits = [
            'code' => $data['code'] ?? strtoupper(substr(str_replace(' ', '', $data['name']), 0, 4)),
            'description' => $data['description'] ?? '',
            'billing_cycle' => $data['billingCycle'] ?? 'monthly',
            'discount_percent' => (int) ($data['courtDiscountPercent'] ?? 10),
            'point_multiplier' => (float) ($data['pointMultiplier'] ?? 1.5),
            'advance_booking_days' => (int) ($data['advanceBookingDays'] ?? 7),
            'free_weekend_hours' => (int) ($data['freeWeekendHours'] ?? 0),
            'is_popular' => (bool) ($data['isPopular'] ?? false),
            'perks' => $data['perks'] ?? [],
        ];

        return $this->planRepository->createForOrganization($organizationId, [
            'name' => $data['name'],
            'price' => (int) $data['price'],
            'benefits' => $benefits,
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
