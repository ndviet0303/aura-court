<?php

namespace App\Domain\Membership\Actions;

use App\Contracts\Repositories\TenantMembershipPlanRepositoryInterface;
use App\Models\TenantMembershipPlan;

class ArchiveAdminMembershipPlan
{
    public function __construct(
        private readonly TenantMembershipPlanRepositoryInterface $planRepository,
    ) {}

    public function execute(TenantMembershipPlan $plan): bool
    {
        return $this->planRepository->archive($plan);
    }
}
