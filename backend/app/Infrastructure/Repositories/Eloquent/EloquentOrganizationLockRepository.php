<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\OrganizationLockRepositoryInterface;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;

class EloquentOrganizationLockRepository implements OrganizationLockRepositoryInterface
{
    public function lockUserForUpdate(int $userId): ?User
    {
        return User::query()->lockForUpdate()->find($userId);
    }

    public function lockOrganizationForUpdate(int $organizationId): ?Organization
    {
        return Organization::query()->lockForUpdate()->find($organizationId);
    }

    public function lockOrganizationWithMembersForUpdate(int $organizationId): ?Organization
    {
        return Organization::query()
            ->lockForUpdate()
            ->with(['members', 'venues'])
            ->find($organizationId);
    }

    public function createOrganization(array $attributes): Organization
    {
        return Organization::create($attributes);
    }

    public function addMember(Organization $org, int $userId, string $role, string $status = 'active'): OrganizationMember
    {
        return $org->members()->create([
            'user_id' => $userId,
            'role' => $role,
            'status' => $status,
        ]);
    }

    public function findBySourceKycId(int $sourceKycId): ?Organization
    {
        return Organization::query()->where('source_kyc_id', $sourceKycId)->first();
    }

    public function findByTaxCode(string $taxCode): ?Organization
    {
        return Organization::query()->where('tax_code', trim($taxCode))->first();
    }

    public function updatePartnerStatus(Organization $org, string $status, ?string $reason = null): Organization
    {
        $org->update([
            'partner_status' => $status,
            'partner_status_reason' => $reason,
            'partner_status_changed_at' => now(),
        ]);

        return $org->fresh();
    }
}
