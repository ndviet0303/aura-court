<?php

namespace Tests\Feature;

use App\Models\LoyaltyRule;
use App\Models\MembershipEntitlement;
use App\Models\Organization;
use App\Models\PointLedger;
use App\Models\TenantMembership;
use App\Models\TenantMembershipPlan;
use App\Models\User;
use App\Services\Loyalty\LoyaltyPointService;
use App\Services\POS\PosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D28: book-ahead expiry, earn multiplier applied once, and append-only
 * expiry reversals. Verifies spendable balance excludes expired lots and that
 * earning follows the disclosed base rule version, not an unrelated newest
 * multiplier rule.
 */
class LoyaltyPointExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_earn_uses_base_rule_and_membership_multiplier_once_not_newest_multiplier_rule(): void
    {
        [$organization, $customer] = $this->tenant();

        $base = LoyaltyRule::create([
            'organization_id' => $organization->id,
            'name' => 'Base Earn',
            'version' => 1,
            'earn_points_per_100_vnd' => 1,
            'redeem_points_per_100_vnd' => 100,
            'status' => 'published',
            'published_at' => now(),
        ]);

        // A newer, higher-earn rule that is a multiplier boost: it must NOT be
        // used as the earn base even though it is the latest version.
        LoyaltyRule::create([
            'organization_id' => $organization->id,
            'name' => 'Weekend Multiplier',
            'version' => 2,
            'earn_points_per_100_vnd' => 5,
            'redeem_points_per_100_vnd' => 100,
            'status' => 'published',
            'published_at' => now(),
            'metadata' => ['is_multiplier_rule' => true],
        ]);

        $this->giveMembershipMultiplier($organization, $customer, 2);

        // Base 1pt/100 on 10,000 VND = 100 base points; disclosed multiplier x2 = 200.
        $entry = app(PosService::class)->awardPoints($organization->id, $customer->id, 10000, 'pos_order', 201);

        $this->assertSame(200, $entry->delta);
        $this->assertSame($base->id, (int) $entry->loyalty_rule_id);
        $this->assertSame(1, (int) ($entry->metadata['rule_version'] ?? null));
        $this->assertSame(100, (int) ($entry->metadata['base_points'] ?? null));
        $this->assertEqualsWithDelta(2.0, (float) ($entry->metadata['earn_multiplier'] ?? 0), 0.001);
    }

    public function test_available_balance_excludes_expired_lots_and_blocks_overdraw(): void
    {
        [$organization, $customer] = $this->tenant();
        $this->baseRule($organization);

        // Expired lot (300) plus a still-valid lot (200).
        PointLedger::create(['organization_id' => $organization->id, 'user_id' => $customer->id, 'delta' => 300, 'balance_after' => 300, 'source_type' => 'seed', 'source_id' => 1, 'expires_at' => now()->subDay()]);
        PointLedger::create(['organization_id' => $organization->id, 'user_id' => $customer->id, 'delta' => 200, 'balance_after' => 500, 'source_type' => 'seed', 'source_id' => 2, 'expires_at' => now()->addYear()]);

        $svc = app(LoyaltyPointService::class);
        $this->assertSame(200, $svc->availableBalance($organization->id, $customer->id));

        // Cannot spend the expired 300; only 200 is spendable.
        try {
            app(PosService::class)->redeemPoints($organization->id, $customer->id, 300, 'pos_discount', 9);
            $this->fail('Expected 409 for overdrawing expired points.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertStringContainsString('Insufficient loyalty points', $e->getMessage());
        }

        $entry = app(PosService::class)->redeemPoints($organization->id, $customer->id, 200, 'pos_discount', 10);
        $this->assertSame(-200, $entry->delta);
        $this->assertSame(0, $svc->availableBalance($organization->id, $customer->id));
    }

    public function test_award_sets_book_ahead_expiry_from_rule_metadata(): void
    {
        [$organization, $customer] = $this->tenant();

        LoyaltyRule::create([
            'organization_id' => $organization->id,
            'name' => 'Base Earn',
            'version' => 1,
            'earn_points_per_100_vnd' => 1,
            'redeem_points_per_100_vnd' => 100,
            'status' => 'published',
            'published_at' => now(),
            'metadata' => ['expiry_days' => 30],
        ]);

        // No membership multiplier: 1pt/100 on 10,000 VND = 100 points, expiring in 30 days.
        $entry = app(PosService::class)->awardPoints($organization->id, $customer->id, 10000, 'pos_order', 301);

        $this->assertSame(100, $entry->delta);
        $this->assertNotNull($entry->expires_at);
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $entry->expires_at->timestamp, 120);
    }

    public function test_expiry_sweep_is_idempotent_append_only_reversal(): void
    {
        [$organization, $customer] = $this->tenant();
        $this->baseRule($organization);

        $lot = PointLedger::create([
            'organization_id' => $organization->id,
            'user_id' => $customer->id,
            'delta' => 300,
            'balance_after' => 300,
            'source_type' => 'seed',
            'source_id' => 1,
            'expires_at' => now()->subDay(),
        ]);

        $svc = app(LoyaltyPointService::class);
        $this->assertSame(0, $svc->availableBalance($organization->id, $customer->id));

        // First sweep writes exactly one append-only reversal keyed to the lot.
        $this->assertSame(1, $svc->expireDuePoints($organization->id));
        $this->assertDatabaseHas('point_ledgers', [
            'organization_id' => $organization->id,
            'user_id' => $customer->id,
            'source_type' => LoyaltyPointService::SOURCE_EXPIRY,
            'source_id' => $lot->id,
            'delta' => -300,
        ]);
        $this->assertSame(0, (int) PointLedger::where('organization_id', $organization->id)->where('user_id', $customer->id)->sum('delta'));

        // Idempotent: a second sweep writes nothing and leaves exactly one reversal.
        $this->assertSame(0, $svc->expireDuePoints($organization->id));
        $this->assertSame(1, PointLedger::where('source_type', LoyaltyPointService::SOURCE_EXPIRY)->where('source_id', $lot->id)->count());

        // The original lot is immutable and untouched.
        $this->assertSame(300, (int) $lot->fresh()->delta);
    }

    private function tenant(): array
    {
        $organization = Organization::create(['name' => 'Loyalty Tenant', 'slug' => 'loyalty-'.uniqid()]);
        $customer = User::factory()->create();
        config(['features.pilot_organizations' => [(string) $organization->id], 'features.flags.tenant_pos_and_wallet' => true]);

        return [$organization, $customer];
    }

    private function baseRule(Organization $organization): LoyaltyRule
    {
        return LoyaltyRule::create([
            'organization_id' => $organization->id,
            'name' => 'Base Earn',
            'version' => 1,
            'earn_points_per_100_vnd' => 1,
            'redeem_points_per_100_vnd' => 100,
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    private function giveMembershipMultiplier(Organization $organization, User $customer, float $multiplier): void
    {
        $plan = TenantMembershipPlan::create([
            'organization_id' => $organization->id,
            'name' => 'Points Booster',
            'price' => 300000,
            'version' => 1,
            'billing_cycle' => 'monthly',
            'benefits' => ['point_multiplier' => $multiplier],
            'status' => 'active',
        ]);
        $membership = TenantMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $customer->id,
            'plan_id' => $plan->id,
            'plan_version' => $plan->version,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);
        MembershipEntitlement::create([
            'organization_id' => $organization->id,
            'membership_id' => $membership->id,
            'benefit_code' => 'point_multiplier',
            'benefit_value' => ['value' => $multiplier],
            'starts_at' => $membership->starts_at,
            'ends_at' => $membership->ends_at,
        ]);
    }
}
