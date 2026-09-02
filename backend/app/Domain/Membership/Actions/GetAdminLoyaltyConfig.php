<?php

namespace App\Domain\Membership\Actions;

use App\Contracts\Repositories\LoyaltyRuleRepositoryInterface;

class GetAdminLoyaltyConfig
{
    public function __construct(
        private readonly LoyaltyRuleRepositoryInterface $loyaltyRepository,
    ) {}

    public function execute(int $organizationId): array
    {
        $config = $this->loyaltyRepository->getActiveConfigForOrganization($organizationId);
        $meta = $config?->metadata ?? [];

        $spendAmountVnd = (int) ($meta['spend_amount_vnd'] ?? ($config ? ($config->earn_points_per_100_vnd > 0 ? (int) round(100 / $config->earn_points_per_100_vnd) * 1000 : 1000) : 1000));
        $pointsEarned = (int) ($meta['points_earned'] ?? ($config ? $config->earn_points_per_100_vnd : 1));
        $pointsRedeemed = (int) ($meta['points_redeemed'] ?? ($config ? $config->redeem_points_per_100_vnd : 100));
        $rewardValueVnd = (int) ($meta['reward_value_vnd'] ?? 5000);

        return [
            'spendAmountVnd' => $spendAmountVnd,
            'pointsEarned' => $pointsEarned,
            'pointsRedeemed' => $pointsRedeemed,
            'rewardValueVnd' => $rewardValueVnd,
            'updatedAt' => $config?->updated_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }
}
