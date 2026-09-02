<?php

namespace App\Domain\Membership\Actions;

use App\Contracts\Repositories\LoyaltyRuleRepositoryInterface;

class UpdateAdminLoyaltyConfig
{
    public function __construct(
        private readonly LoyaltyRuleRepositoryInterface $loyaltyRepository,
    ) {}

    public function execute(int $organizationId, array $data): array
    {
        $rule = $this->loyaltyRepository->updateOrCreateConfig($organizationId, $data);
        $meta = $rule->metadata ?? [];

        return [
            'spendAmountVnd' => (int) ($meta['spend_amount_vnd'] ?? 1000),
            'pointsEarned' => (int) ($meta['points_earned'] ?? 1),
            'pointsRedeemed' => (int) ($meta['points_redeemed'] ?? 100),
            'rewardValueVnd' => (int) ($meta['reward_value_vnd'] ?? 5000),
            'updatedAt' => $rule->updated_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }
}
