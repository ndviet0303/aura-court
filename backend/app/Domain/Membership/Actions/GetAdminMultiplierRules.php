<?php

namespace App\Domain\Membership\Actions;

use App\Contracts\Repositories\LoyaltyRuleRepositoryInterface;
use Illuminate\Support\Collection;

class GetAdminMultiplierRules
{
    public function __construct(
        private readonly LoyaltyRuleRepositoryInterface $loyaltyRepository,
    ) {}

    public function execute(int $organizationId): Collection
    {
        $rules = $this->loyaltyRepository->getMultiplierRulesForOrganization($organizationId);

        return $rules->map(function ($rule) {
            $meta = $rule->metadata ?? [];

            return [
                'id' => (string) $rule->id,
                'name' => $rule->name,
                'description' => $meta['description'] ?? ($meta['schedule'] ?? ''),
                'multiplier' => (float) ($meta['multiplier'] ?? ($rule->earn_points_per_100_vnd ?: 1.5)),
                'tag' => $meta['tag'] ?? 'Special',
                'schedule' => $meta['schedule'] ?? 'T2-T6, 08:00 - 15:00',
                'isActive' => (bool) ($meta['is_active'] ?? ($rule->status === 'published')),
            ];
        });
    }
}
