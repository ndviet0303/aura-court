<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminMembershipTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'admin']);
        $this->org = Organization::create([
            'name' => 'Aura Badminton Club',
            'slug' => 'aura-badminton-club',
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->owner->id,
            'role' => 'organization_owner',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->owner);
    }

    public function test_can_manage_membership_plans(): void
    {
        // 1. Create Plan
        $createRes = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->postJson('/api/v2/admin/memberships/plans', [
                'name' => 'Gold Elite Pass',
                'code' => 'GOLD',
                'description' => 'Gói VIP All-Access',
                'price' => 599000,
                'billingCycle' => 'monthly',
                'courtDiscountPercent' => 20,
                'pointMultiplier' => 2.0,
                'advanceBookingDays' => 14,
                'freeWeekendHours' => 4,
                'cancellationFeeWaived' => true,
                'isPopular' => true,
                'perks' => ['Giảm 20% đặt sân', 'Tích x2 điểm', 'Miễn phí hủy sân'],
            ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('name', 'Gold Elite Pass')
            ->assertJsonPath('price', 599000);

        $planId = $createRes->json('id');

        // 2. List Plans
        $listRes = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->getJson('/api/v2/admin/memberships/plans');

        $listRes->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Gold Elite Pass');

        // 3. Update Plan
        $updateRes = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->patchJson("/api/v2/admin/memberships/plans/{$planId}", [
                'name' => 'Gold Elite Pass Pro',
                'price' => 699000,
            ]);

        $updateRes->assertOk()
            ->assertJsonPath('name', 'Gold Elite Pass Pro')
            ->assertJsonPath('price', 699000);

        // 4. Archive Plan
        $archiveRes = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->postJson("/api/v2/admin/memberships/plans/{$planId}/archive");

        $archiveRes->assertOk();
    }

    public function test_can_manage_loyalty_config_and_multiplier_rules(): void
    {
        // 1. Get Loyalty Config
        $configRes = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->getJson('/api/v2/admin/memberships/loyalty-config');

        $configRes->assertOk()
            ->assertJsonStructure(['spendAmountVnd', 'pointsEarned', 'pointsRedeemed', 'rewardValueVnd']);

        // 2. Update Loyalty Config
        $updateConfigRes = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->patchJson('/api/v2/admin/memberships/loyalty-config', [
                'spendAmountVnd' => 2000,
                'pointsEarned' => 2,
                'pointsRedeemed' => 200,
                'rewardValueVnd' => 10000,
            ]);

        $updateConfigRes->assertOk()
            ->assertJsonPath('spendAmountVnd', 2000)
            ->assertJsonPath('rewardValueVnd', 10000);

        // 3. Create Multiplier Rule
        $createRuleRes = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->postJson('/api/v2/admin/memberships/multiplier-rules', [
                'name' => 'Happy Hours',
                'multiplier' => 2.5,
                'tag' => 'Off-peak',
                'schedule' => 'T2 - T6, 08:00 - 15:00',
                'description' => 'Giờ vàng giảm giá',
                'isActive' => true,
            ]);

        $createRuleRes->assertStatus(201)
            ->assertJsonPath('name', 'Happy Hours')
            ->assertJsonPath('multiplier', 2.5);

        $ruleId = $createRuleRes->json('id');

        // 4. List Multiplier Rules
        $rulesRes = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->getJson('/api/v2/admin/memberships/multiplier-rules');

        $rulesRes->assertOk()
            ->assertJsonCount(1);

        // 5. Update Multiplier Rule
        $updateRuleRes = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->patchJson("/api/v2/admin/memberships/multiplier-rules/{$ruleId}", [
                'isActive' => false,
            ]);

        $updateRuleRes->assertOk()
            ->assertJsonPath('isActive', false);

        // 6. Delete Multiplier Rule
        $deleteRuleRes = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->deleteJson("/api/v2/admin/memberships/multiplier-rules/{$ruleId}");

        $deleteRuleRes->assertOk();
    }

    public function test_can_fetch_version_history_and_stats(): void
    {
        $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->postJson('/api/v2/admin/memberships/plans', [
                'name' => 'VIP Member Tier',
                'price' => 500000,
            ]);

        $historyRes = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->getJson('/api/v2/admin/memberships/version-history');

        $historyRes->assertOk()
            ->assertJsonStructure([['id', 'planName', 'version', 'status']]);

        $statsRes = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->getJson('/api/v2/admin/memberships/stats');

        $statsRes->assertOk()
            ->assertJsonStructure(['totalVipMembers', 'monthlyRecurringRevenue', 'totalPointsIssued', 'redemptionRatePercent']);

    }
}
