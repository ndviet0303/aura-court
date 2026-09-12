<?php

namespace App\Domain\Membership\Actions;

use App\Contracts\Repositories\TenantMembershipPlanRepositoryInterface;
use App\Models\TenantMembershipPlan;

class UpdateAdminMembershipPlan
{
    public function __construct(
        private readonly TenantMembershipPlanRepositoryInterface $planRepository,
    ) {}

    public function execute(TenantMembershipPlan $plan, array $data): TenantMembershipPlan
    {
        if (array_key_exists('cancellationFeeWaived', $data) || array_key_exists('cancellation_fee_waived', $data)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'cancellationFeeWaived' => 'Membership cancellation fee waiver is no longer supported.',
            ]);
        }

        $currentBenefits = $plan->benefits ?? [];

        if (isset($data['code'])) $currentBenefits['code'] = $data['code'];
        if (isset($data['description'])) $currentBenefits['description'] = $data['description'];
        if (isset($data['billingCycle'])) $currentBenefits['billing_cycle'] = $data['billingCycle'];
        if (isset($data['courtDiscountPercent'])) $currentBenefits['discount_percent'] = (int) $data['courtDiscountPercent'];
        if (isset($data['pointMultiplier'])) $currentBenefits['point_multiplier'] = (float) $data['pointMultiplier'];
        if (isset($data['advanceBookingDays'])) $currentBenefits['advance_booking_days'] = (int) $data['advanceBookingDays'];
        if (isset($data['freeWeekendHours'])) $currentBenefits['free_weekend_hours'] = (int) $data['freeWeekendHours'];
        if (array_key_exists('isPopular', $data)) $currentBenefits['is_popular'] = (bool) $data['isPopular'];
        if (isset($data['perks'])) $currentBenefits['perks'] = $data['perks'];

        $updateData = ['benefits' => $currentBenefits];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['price'])) {
            $updateData['price'] = (int) $data['price'];
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'] === 'active' ? 'published' : $data['status'];
        }
        if (isset($data['version'])) {
            $rawVer = preg_replace('/[^0-9]/', '', $data['version']);
            if ($rawVer) {
                $updateData['version'] = (int) $rawVer;
            }
        }

        return $this->planRepository->update($plan, $updateData);
    }
}
