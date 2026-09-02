<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\LoyaltyRuleRepositoryInterface;
use App\Models\LoyaltyRule;
use Illuminate\Database\Eloquent\Collection;

class EloquentLoyaltyRuleRepository implements LoyaltyRuleRepositoryInterface
{
    public function getActiveConfigForOrganization(int $organizationId): ?LoyaltyRule
    {
        return LoyaltyRule::where('organization_id', $organizationId)
            ->where('name', 'Default Loyalty Config')
            ->latest('version')
            ->first()
            ?: LoyaltyRule::where('organization_id', $organizationId)
                ->whereNull('metadata->is_multiplier_rule')
                ->latest('id')
                ->first();
    }

    public function getMultiplierRulesForOrganization(int $organizationId): Collection
    {
        return LoyaltyRule::where('organization_id', $organizationId)
            ->where('metadata->is_multiplier_rule', true)
            ->latest('id')
            ->get();
    }

    public function findByIdAndOrganization(int $id, int $organizationId): ?LoyaltyRule
    {
        return LoyaltyRule::where('organization_id', $organizationId)->find($id);
    }

    public function updateOrCreateConfig(int $organizationId, array $data): LoyaltyRule
    {
        $existing = $this->getActiveConfigForOrganization($organizationId);
        $version = $existing ? $existing->version + 1 : 1;

        $spendAmountVnd = (int) ($data['spendAmountVnd'] ?? $data['spend_amount_vnd'] ?? 1000);
        $pointsEarned = (int) ($data['pointsEarned'] ?? $data['points_earned'] ?? 1);
        $pointsRedeemed = (int) ($data['pointsRedeemed'] ?? $data['points_redeemed'] ?? 100);
        $rewardValueVnd = (int) ($data['rewardValueVnd'] ?? $data['reward_value_vnd'] ?? 5000);

        // Calculate rates per 100 VND
        $earnPer100Vnd = max(1, (int) round(($pointsEarned / max(1, $spendAmountVnd)) * 100));
        $redeemPer100Vnd = max(1, (int) round(($pointsRedeemed / max(1, $rewardValueVnd)) * 100));

        return LoyaltyRule::create([
            'organization_id' => $organizationId,
            'name' => 'Default Loyalty Config',
            'version' => $version,
            'earn_points_per_100_vnd' => $earnPer100Vnd,
            'redeem_points_per_100_vnd' => $redeemPer100Vnd,
            'status' => 'published',
            'metadata' => [
                'spend_amount_vnd' => $spendAmountVnd,
                'points_earned' => $pointsEarned,
                'points_redeemed' => $pointsRedeemed,
                'reward_value_vnd' => $rewardValueVnd,
            ],
            'published_at' => now(),
        ]);
    }

    public function createMultiplierRule(int $organizationId, array $data): LoyaltyRule
    {
        $version = (int) LoyaltyRule::where('organization_id', $organizationId)
            ->where('name', $data['name'])
            ->max('version') + 1;

        $multiplier = (float) ($data['multiplier'] ?? 1.5);

        return LoyaltyRule::create([
            'organization_id' => $organizationId,
            'name' => $data['name'],
            'version' => $version,
            'earn_points_per_100_vnd' => max(1, (int) round($multiplier)),
            'redeem_points_per_100_vnd' => 100,
            'status' => ($data['isActive'] ?? true) ? 'published' : 'draft',
            'metadata' => [
                'is_multiplier_rule' => true,
                'multiplier' => $multiplier,
                'tag' => $data['tag'] ?? 'Special',
                'schedule' => $data['schedule'] ?? '',
                'description' => $data['description'] ?? '',
                'is_active' => (bool) ($data['isActive'] ?? true),
            ],
            'published_at' => now(),
        ]);
    }

    public function updateMultiplierRule(LoyaltyRule $rule, array $data): LoyaltyRule
    {
        $currentMetadata = $rule->metadata ?? [];

        if (isset($data['multiplier'])) {
            $currentMetadata['multiplier'] = (float) $data['multiplier'];
            $rule->earn_points_per_100_vnd = max(1, (int) round((float) $data['multiplier']));
        }
        if (isset($data['tag'])) {
            $currentMetadata['tag'] = $data['tag'];
        }
        if (isset($data['schedule'])) {
            $currentMetadata['schedule'] = $data['schedule'];
        }
        if (isset($data['description'])) {
            $currentMetadata['description'] = $data['description'];
        }
        if (array_key_exists('isActive', $data)) {
            $currentMetadata['is_active'] = (bool) $data['isActive'];
            $rule->status = $data['isActive'] ? 'published' : 'draft';
        }

        if (isset($data['name'])) {
            $rule->name = $data['name'];
        }

        $rule->metadata = $currentMetadata;
        $rule->save();

        return $rule->fresh();
    }

    public function deleteMultiplierRule(LoyaltyRule $rule): bool
    {
        return $rule->delete();
    }
}
