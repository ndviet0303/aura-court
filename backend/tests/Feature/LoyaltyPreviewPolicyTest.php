<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Court;
use App\Models\LoyaltyRule;
use App\Models\MembershipEntitlement;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PointLedger;
use App\Models\PriceQuote;
use App\Models\PriceRule;
use App\Models\TenantMembership;
use App\Models\TenantMembershipPlan;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoyaltyPreviewPolicyTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Organization, 1: User, 2: Court} */
    private function setupTenant(int $userPoints = 2000): array
    {
        $org = Organization::create([
            'name' => 'Loyalty Tenant '.uniqid(),
            'slug' => 'loyalty-tenant-'.uniqid(),
        ]);

        $user = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Loyalty Venue',
            'slug' => 'loyalty-venue-'.uniqid(),
            'is_active' => true,
        ]);

        $court = Court::create([
            'venue_id' => $venue->id,
            'sport_type' => 'badminton',
            'name' => 'Court 1',
            'is_active' => true,
        ]);

        // Base price rule for 200,000 VND
        PriceRule::create([
            'organization_id' => $org->id,
            'court_id' => $court->id,
            'type' => 'base',
            'price' => 200000,
            'is_active' => true,
        ]);

        if ($userPoints > 0) {
            PointLedger::create([
                'organization_id' => $org->id,
                'user_id' => $user->id,
                'loyalty_rule_id' => null,
                'delta' => $userPoints,
                'balance_after' => $userPoints,
                'source_type' => 'initial_seed',
                'source_id' => 1,
            ]);
        }

        return [$org, $user, $court];
    }

    /**
     * Helper to create a base redemption rule.
     */
    private function createBaseRedemptionRule(Organization $org, int $pointsRedeemed = 100, int $rewardValueVnd = 5000, int $version = 1): LoyaltyRule
    {
        return LoyaltyRule::create([
            'organization_id' => $org->id,
            'name' => 'Standard Redemption Policy',
            'version' => $version,
            'earn_points_per_100_vnd' => 10,
            'redeem_points_per_100_vnd' => 0,
            'status' => 'published',
            'metadata' => [
                'points_redeemed' => $pointsRedeemed,
                'reward_value_vnd' => $rewardValueVnd,
            ],
            'published_at' => now(),
        ]);
    }

    public function test_100_points_for_5000_vnd_example_on_200000_payable(): void
    {
        [$org, $user, $court] = $this->setupTenant(2000);
        $this->createBaseRedemptionRule($org, 100, 5000, 1);

        $futureTime = now()->addDay()->setTime(10, 0)->toIso8601String();

        $res = $this->actingAs($user)->postJson('/api/loyalty/preview', [
            'court_id' => $court->id,
            'slots' => [$futureTime],
            'points' => 100,
        ]);

        $res->assertOk();
        $res->assertJsonPath('organization_id', $org->id);
        $res->assertJsonPath('balance', 2000);
        $res->assertJsonPath('policy.version', 1);
        $res->assertJsonPath('policy.points_redeemed', 100);
        $res->assertJsonPath('policy.reward_value_vnd', 5000);
        $res->assertJsonPath('points_selected', 100);
        $res->assertJsonPath('discount_vnd', 5000);
        $res->assertJsonPath('remaining_payable_vnd', 195000);
        // Max points for 200,000 VND payable: 200,000 * 100 / 5,000 = 4,000 points. Bounded by user balance (2,000)
        $res->assertJsonPath('max_usable_points', 2000);
    }

    public function test_non_divisible_ratios_and_whole_vnd_floor_rounding(): void
    {
        [$org, $user, $court] = $this->setupTenant(2000);
        // Ratio: 3 points -> 100 VND
        $this->createBaseRedemptionRule($org, 3, 100, 1);

        $futureTime = now()->addDay()->setTime(10, 0)->toIso8601String();

        // Requested: 10 points -> 10 * 100 / 3 = 333.333... -> floor 333 VND
        $res = $this->actingAs($user)->postJson('/api/loyalty/preview', [
            'court_id' => $court->id,
            'slots' => [$futureTime],
            'points' => 10,
        ]);

        $res->assertOk();
        $res->assertJsonPath('points_selected', 10);
        $res->assertJsonPath('discount_vnd', 333);
        $res->assertJsonPath('remaining_payable_vnd', 200000 - 333);
    }

    public function test_zero_negative_and_fractional_points_are_rejected(): void
    {
        [$org, $user, $court] = $this->setupTenant(2000);
        $this->createBaseRedemptionRule($org, 100, 5000, 1);

        $futureTime = now()->addDay()->setTime(10, 0)->toIso8601String();

        // 1. Zero points
        $resZero = $this->actingAs($user)->postJson('/api/loyalty/preview', [
            'court_id' => $court->id,
            'slots' => [$futureTime],
            'points' => 0,
        ]);
        $resZero->assertStatus(422);

        // 2. Negative points
        $resNeg = $this->actingAs($user)->postJson('/api/loyalty/preview', [
            'court_id' => $court->id,
            'slots' => [$futureTime],
            'points' => -50,
        ]);
        $resNeg->assertStatus(422);

        // 3. Fractional points
        $resFrac = $this->actingAs($user)->postJson('/api/loyalty/preview', [
            'court_id' => $court->id,
            'slots' => [$futureTime],
            'points' => 12.5,
        ]);
        $resFrac->assertStatus(422);
    }

    public function test_insufficient_points_rejected(): void
    {
        [$org, $user, $court] = $this->setupTenant(50); // only 50 points
        $this->createBaseRedemptionRule($org, 100, 5000, 1);

        $futureTime = now()->addDay()->setTime(10, 0)->toIso8601String();

        $res = $this->actingAs($user)->postJson('/api/loyalty/preview', [
            'court_id' => $court->id,
            'slots' => [$futureTime],
            'points' => 100, // exceeds balance of 50
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['points']);
    }

    public function test_zero_benefit_points_rejected(): void
    {
        [$org, $user, $court] = $this->setupTenant(2000);
        // Ratio: 100 points -> 50 VND (1 point = 0.5 VND -> floor(1 * 50 / 100) = 0 VND)
        $this->createBaseRedemptionRule($org, 100, 50, 1);

        $futureTime = now()->addDay()->setTime(10, 0)->toIso8601String();

        $res = $this->actingAs($user)->postJson('/api/loyalty/preview', [
            'court_id' => $court->id,
            'slots' => [$futureTime],
            'points' => 1,
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['points']);
    }

    public function test_excessive_discount_rejected(): void
    {
        [$org, $user, $court] = $this->setupTenant(5000);
        // 100 points -> 5,000 VND. So 5000 points -> 250,000 VND discount
        // But payable is 200,000 VND.
        $this->createBaseRedemptionRule($org, 100, 5000, 1);

        $futureTime = now()->addDay()->setTime(10, 0)->toIso8601String();

        // 4500 points -> 225,000 VND discount > 200,000 VND payable
        $res = $this->actingAs($user)->postJson('/api/loyalty/preview', [
            'court_id' => $court->id,
            'slots' => [$futureTime],
            'points' => 4500,
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['points']);
    }

    public function test_missing_or_disabled_redemption_policy(): void
    {
        [$org, $user, $court] = $this->setupTenant(2000);
        // No loyalty rule created

        $futureTime = now()->addDay()->setTime(10, 0)->toIso8601String();

        // 1. When points are requested, returns 422
        $resPoints = $this->actingAs($user)->postJson('/api/loyalty/preview', [
            'court_id' => $court->id,
            'slots' => [$futureTime],
            'points' => 100,
        ]);
        $resPoints->assertStatus(422);
        $resPoints->assertJsonValidationErrors(['loyalty_policy']);

        // 2. Read-only discovery without points succeeds with policy null
        $resDiscovery = $this->actingAs($user)->postJson('/api/loyalty/preview', [
            'court_id' => $court->id,
            'slots' => [$futureTime],
        ]);
        $resDiscovery->assertOk();
        $resDiscovery->assertJsonPath('policy', null);
        $resDiscovery->assertJsonPath('max_usable_points', 0);
        $resDiscovery->assertJsonPath('discount_vnd', 0);
    }

    public function test_earn_multiplier_rule_excluded_even_if_newer_version(): void
    {
        [$org, $user, $court] = $this->setupTenant(2000);

        // Version 1: Base redemption rule
        $this->createBaseRedemptionRule($org, 100, 5000, 1);

        // Version 2: Multiplier rule (newer version, published)
        LoyaltyRule::create([
            'organization_id' => $org->id,
            'name' => '2x Weekend Earn Multiplier',
            'version' => 2,
            'earn_points_per_100_vnd' => 20,
            'redeem_points_per_100_vnd' => 0,
            'status' => 'published',
            'metadata' => [
                'is_multiplier_rule' => true,
                'multiplier' => 2.0,
            ],
            'published_at' => now(),
        ]);

        $futureTime = now()->addDay()->setTime(10, 0)->toIso8601String();

        $res = $this->actingAs($user)->postJson('/api/loyalty/preview', [
            'court_id' => $court->id,
            'slots' => [$futureTime],
            'points' => 100,
        ]);

        $res->assertOk();
        // Resolves Version 1 (Base rule), NOT Version 2 (Multiplier rule)
        $res->assertJsonPath('policy.version', 1);
        $res->assertJsonPath('policy.points_redeemed', 100);
        $res->assertJsonPath('policy.reward_value_vnd', 5000);
    }

    public function test_coupon_combination_handling(): void
    {
        [$org, $user, $court] = $this->setupTenant(2000);
        $this->createBaseRedemptionRule($org, 100, 5000, 1);

        // Create coupon for 50,000 VND off
        $coupon = Coupon::create([
            'organization_id' => $org->id,
            'code' => 'SAVE50K',
            'discount_type' => 'fixed',
            'discount_value' => 50000,
            'min_spend' => 100000,
            'is_active' => true,
            'usage_count' => 0,
        ]);

        $futureTime = now()->addDay()->setTime(10, 0)->toIso8601String();

        // Subtotal: 200,000 VND
        // Coupon discount: 50,000 VND -> net payable before points: 150,000 VND
        // Points: 100 points -> 5,000 VND discount
        // Final remaining: 145,000 VND
        $res = $this->actingAs($user)->postJson('/api/loyalty/preview', [
            'court_id' => $court->id,
            'slots' => [$futureTime],
            'coupon_code' => 'SAVE50K',
            'points' => 100,
        ]);

        $res->assertOk();
        $res->assertJsonPath('discount_vnd', 5000);
        $res->assertJsonPath('remaining_payable_vnd', 145000);

        // Max usable points is bounded by 150,000 VND, not 200,000 VND:
        // 150,000 * 100 / 5,000 = 3,000 -> bounded by balance 2,000
        $res->assertJsonPath('max_usable_points', 2000);

        // Coupon is NOT consumed
        $this->assertSame(0, $coupon->fresh()->usage_count);

        $plan = TenantMembershipPlan::create([
            'organization_id' => $org->id,
            'name' => 'Loyalty member '.uniqid(),
            'version' => 1,
            'price' => 100000,
            'benefits' => ['discount_percent' => 10],
            'status' => 'published',
            'published_at' => now(),
        ]);
        $membership = TenantMembership::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan_version' => $plan->version,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);
        MembershipEntitlement::create([
            'organization_id' => $org->id,
            'membership_id' => $membership->id,
            'benefit_code' => 'discount_percent',
            'benefit_value' => ['value' => 10, 'plan_version' => $plan->version],
            'starts_at' => $membership->starts_at,
            'ends_at' => $membership->ends_at,
        ]);
        $coupon->update(['can_combine_with_membership' => false]);

        $combined = $this->actingAs($user)->postJson('/api/loyalty/preview', [
            'court_id' => $court->id,
            'slots' => [$futureTime],
            'coupon_code' => 'SAVE50K',
            'points' => 100,
        ]);
        $combined->assertUnprocessable()->assertJsonValidationErrors(['coupon_code']);
        $this->assertSame(0, $coupon->fresh()->usage_count);
    }

    public function test_policy_version_mismatch(): void
    {
        [$org, $user, $court] = $this->setupTenant(2000);
        $this->createBaseRedemptionRule($org, 100, 5000, 2); // active version is 2

        $futureTime = now()->addDay()->setTime(10, 0)->toIso8601String();

        $res = $this->actingAs($user)->postJson('/api/loyalty/preview', [
            'court_id' => $court->id,
            'slots' => [$futureTime],
            'expected_policy_version' => 1, // client has stale version 1
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['expected_policy_version']);
    }

    public function test_quote_token_validation_foreign_and_expired(): void
    {
        [$org, $user, $court] = $this->setupTenant(2000);
        $this->createBaseRedemptionRule($org, 100, 5000, 1);

        $otherUser = User::factory()->create();

        // 1. Foreign quote (owned by otherUser)
        $foreignQuote = PriceQuote::create([
            'organization_id' => $org->id,
            'user_id' => $otherUser->id,
            'court_id' => $court->id,
            'quote_token' => (string) Str::uuid(),
            'status' => 'open',
            'subtotal' => 200000,
            'total_amount' => 200000,
            'rule_version' => 1,
            'slot_snapshot' => [],
            'expires_at' => now()->addMinutes(15),
        ]);

        $resForeign = $this->actingAs($user)->postJson('/api/loyalty/preview', [
            'quote_token' => $foreignQuote->quote_token,
            'points' => 100,
        ]);
        $resForeign->assertStatus(403);

        // 2. Expired quote
        $expiredQuote = PriceQuote::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'court_id' => $court->id,
            'quote_token' => (string) Str::uuid(),
            'status' => 'open',
            'subtotal' => 200000,
            'total_amount' => 200000,
            'rule_version' => 1,
            'slot_snapshot' => [],
            'expires_at' => now()->subMinutes(10), // expired!
        ]);

        $resExpired = $this->actingAs($user)->postJson('/api/loyalty/preview', [
            'quote_token' => $expiredQuote->quote_token,
            'points' => 100,
        ]);
        $resExpired->assertStatus(422);
        $resExpired->assertJsonValidationErrors(['quote_token']);

        // 3. Valid quote owned by user
        $validQuote = PriceQuote::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'court_id' => $court->id,
            'quote_token' => (string) Str::uuid(),
            'status' => 'open',
            'subtotal' => 200000,
            'total_amount' => 200000,
            'rule_version' => 1,
            'slot_snapshot' => [],
            'expires_at' => now()->addMinutes(15),
        ]);

        $resValid = $this->actingAs($user)->postJson('/api/loyalty/preview', [
            'quote_token' => $validQuote->quote_token,
            'points' => 100,
        ]);
        $resValid->assertOk();
        $resValid->assertJsonPath('discount_vnd', 5000);
        $resValid->assertJsonPath('remaining_payable_vnd', 195000);
    }

    public function test_debit_only_redeem_endpoint_rejected_with_422_and_zero_mutation(): void
    {
        [$org, $user, $court] = $this->setupTenant(2000);
        $this->createBaseRedemptionRule($org, 100, 5000, 1);

        $initialLedgerCount = PointLedger::count();

        $res = $this->actingAs($user)->postJson('/api/loyalty/redeem', [
            'points' => 100,
            'source_type' => 'custom',
            'source_id' => 999,
        ]);

        $res->assertStatus(422);
        $res->assertJsonPath('error_code', 'DIRECT_REDEMPTION_RETIRED');

        // Assert ZERO mutation to PointLedger
        $this->assertSame($initialLedgerCount, PointLedger::count());
        $userBalance = PointLedger::where('organization_id', $org->id)->where('user_id', $user->id)->sum('delta');
        $this->assertSame(2000, (int) $userBalance);
    }
}
