<?php

namespace App\Domain\Membership\Actions;

use App\Contracts\Repositories\LoyaltyRuleRepositoryInterface;
use App\Models\LoyaltyRule;

class UpdateAdminMultiplierRule
{
    public function __construct(
        private readonly LoyaltyRuleRepositoryInterface $loyaltyRepository,
    ) {}

    public function execute(LoyaltyRule $rule, array $data): array
    {
        $updated = $this->loyaltyRepository->updateMultiplierRule($rule, $data);
        $meta = $updated->metadata ?? [];

        return [
            'id' => (string) $updated->id,
            'name' => $updated->name,
            'description' => $meta['description'] ?? ($meta['schedule'] ?? ''),
            'multiplier' => (float) ($meta['multiplier'] ?? 1.5),
            'tag' => $meta['tag'] ?? 'Special',
            'schedule' => $meta['schedule'] ?? '',
            'isActive' => (bool) ($meta['is_active'] ?? true),
        ];
    }
}
