<?php

namespace App\Domain\Membership\Actions;

use App\Contracts\Repositories\LoyaltyRuleRepositoryInterface;

class CreateAdminMultiplierRule
{
    public function __construct(
        private readonly LoyaltyRuleRepositoryInterface $loyaltyRepository,
    ) {}

    public function execute(int $organizationId, array $data): array
    {
        $rule = $this->loyaltyRepository->createMultiplierRule($organizationId, $data);
        $meta = $rule->metadata ?? [];

        return [
            'id' => (string) $rule->id,
            'name' => $rule->name,
            'description' => $meta['description'] ?? ($meta['schedule'] ?? ''),
            'multiplier' => (float) ($meta['multiplier'] ?? 1.5),
            'tag' => $meta['tag'] ?? 'Special',
            'schedule' => $meta['schedule'] ?? '',
            'isActive' => (bool) ($meta['is_active'] ?? true),
        ];
    }
}
