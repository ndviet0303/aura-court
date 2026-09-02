<?php

namespace App\Contracts\Repositories;

use App\Models\TenantMembershipPlan;
use Illuminate\Database\Eloquent\Collection;

interface TenantMembershipPlanRepositoryInterface
{
    public function getForOrganization(int $organizationId, ?string $status = null): Collection;

    public function findByIdAndOrganization(int $id, int $organizationId): ?TenantMembershipPlan;

    public function createForOrganization(int $organizationId, array $data): TenantMembershipPlan;

    public function update(TenantMembershipPlan $plan, array $data): TenantMembershipPlan;

    public function archive(TenantMembershipPlan $plan): bool;

    public function getActiveSubscribersCount(TenantMembershipPlan $plan): int;
}
