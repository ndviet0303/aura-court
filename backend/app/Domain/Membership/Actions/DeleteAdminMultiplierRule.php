<?php

namespace App\Domain\Membership\Actions;

use App\Contracts\Repositories\LoyaltyRuleRepositoryInterface;
use App\Models\LoyaltyRule;

class DeleteAdminMultiplierRule
{
    public function __construct(
        private readonly LoyaltyRuleRepositoryInterface $loyaltyRepository,
    ) {}

    public function execute(LoyaltyRule $rule): bool
    {
        return $this->loyaltyRepository->deleteMultiplierRule($rule);
    }
}
