<?php

namespace App\Contracts\Repositories;

use App\Models\LoyaltyRule;
use Illuminate\Database\Eloquent\Collection;

interface LoyaltyRuleRepositoryInterface
{
    public function getActiveConfigForOrganization(int $organizationId): ?LoyaltyRule;

    public function getMultiplierRulesForOrganization(int $organizationId): Collection;

    public function findByIdAndOrganization(int $id, int $organizationId): ?LoyaltyRule;

    public function updateOrCreateConfig(int $organizationId, array $data): LoyaltyRule;

    public function createMultiplierRule(int $organizationId, array $data): LoyaltyRule;

    public function updateMultiplierRule(LoyaltyRule $rule, array $data): LoyaltyRule;

    public function deleteMultiplierRule(LoyaltyRule $rule): bool;
}
