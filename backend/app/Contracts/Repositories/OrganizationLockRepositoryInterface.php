<?php

namespace App\Contracts\Repositories;

use App\Models\Organization;
use App\Models\User;

interface OrganizationLockRepositoryInterface
{
    public function lockUserForUpdate(int $userId): ?User;

    public function lockOrganizationForUpdate(int $organizationId): ?Organization;

    public function lockOrganizationWithMembersForUpdate(int $organizationId): ?Organization;

    public function createOrganization(array $attributes): Organization;

    public function addMember(Organization $org, int $userId, string $role, string $status = 'active'): \App\Models\OrganizationMember;

    public function findBySourceKycId(int $sourceKycId): ?Organization;

    public function updatePartnerStatus(Organization $org, string $status, ?string $reason = null): Organization;
}
