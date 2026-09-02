<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\TenantMembershipPlanRepositoryInterface;
use App\Models\TenantMembership;
use App\Models\TenantMembershipPlan;
use Illuminate\Database\Eloquent\Collection;

class EloquentTenantMembershipPlanRepository implements TenantMembershipPlanRepositoryInterface
{
    public function getForOrganization(int $organizationId, ?string $status = null): Collection
    {
        $query = TenantMembershipPlan::where('organization_id', $organizationId)
            ->withCount(['memberships as active_subscribers' => function ($q) {
                $q->where('status', 'active')->where('ends_at', '>', now());
            }]);

        if ($status !== null && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query->latest('published_at')->latest('id')->get();
    }

    public function findByIdAndOrganization(int $id, int $organizationId): ?TenantMembershipPlan
    {
        return TenantMembershipPlan::where('organization_id', $organizationId)
            ->withCount(['memberships as active_subscribers' => function ($q) {
                $q->where('status', 'active')->where('ends_at', '>', now());
            }])
            ->find($id);
    }

    public function createForOrganization(int $organizationId, array $data): TenantMembershipPlan
    {
        $version = (int) TenantMembershipPlan::where('organization_id', $organizationId)
            ->where('name', $data['name'])
            ->max('version') + 1;

        return TenantMembershipPlan::create([
            'organization_id' => $organizationId,
            'name' => $data['name'],
            'version' => $version,
            'price' => $data['price'],
            'benefits' => $data['benefits'] ?? [],
            'status' => $data['status'] ?? 'published',
            'published_at' => $data['published_at'] ?? now(),
        ]);
    }

    public function update(TenantMembershipPlan $plan, array $data): TenantMembershipPlan
    {
        $plan->update($data);
        return $plan->fresh();
    }

    public function archive(TenantMembershipPlan $plan): bool
    {
        return $plan->update(['status' => 'archived']);
    }

    public function getActiveSubscribersCount(TenantMembershipPlan $plan): int
    {
        return TenantMembership::where('organization_id', $plan->organization_id)
            ->where('plan_id', $plan->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->count();
    }
}
