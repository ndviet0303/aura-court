<?php

namespace Tests\Feature\Subscription;

use App\Domain\Subscription\Actions\EnforcePlanQuota;
use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PlatformQuotaAndFeatureEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private EnforcePlanQuota $enforceQuota;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enforceQuota = app(EnforcePlanQuota::class);
    }

    public function test_venue_quota_enforcement(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'org', 'status' => 'active', 'partner_status' => 'approved']);
        $plan = PlatformPlan::create([
            'code' => 'STARTER',
            'name' => 'Gói Starter',
            'price' => 200000,
            'max_venues' => 1, // Limit: 1 venue
            'max_courts' => 4,
            'max_staff' => 2,
            'features' => ['online_booking'],
            'is_active' => true,
        ]);

        PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'plan_version' => 1,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        // First venue: within quota
        $this->enforceQuota->execute($org->id, 'venues', 1);

        // Create 1 venue
        Venue::create([
            'organization_id' => $org->id,
            'name' => 'Venue 1',
            'slug' => 'venue-1',
            'is_active' => true,
        ]);

        // Attempting to add second venue MUST fail with ValidationException
        $this->expectException(ValidationException::class);
        $this->enforceQuota->execute($org->id, 'venues', 1);
    }

    public function test_court_quota_enforcement(): void
    {
        $org = Organization::create(['name' => 'Org 2', 'slug' => 'org-2', 'status' => 'active', 'partner_status' => 'approved']);
        $plan = PlatformPlan::create([
            'code' => 'STARTER_2',
            'name' => 'Gói Starter',
            'price' => 200000,
            'max_venues' => 2,
            'max_courts' => 2, // Limit: 2 courts
            'max_staff' => 2,
            'is_active' => true,
        ]);

        PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'plan_version' => 1,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Venue',
            'slug' => 'venue',
            'is_active' => true,
        ]);

        Court::create(['venue_id' => $venue->id, 'name' => 'Court 1', 'slug' => 'c1', 'is_active' => true]);
        Court::create(['venue_id' => $venue->id, 'name' => 'Court 2', 'slug' => 'c2', 'is_active' => true]);

        // Attempting to add 3rd court MUST fail
        $this->expectException(ValidationException::class);
        $this->enforceQuota->execute($org->id, 'courts', 1);
    }

    public function test_staff_quota_enforcement(): void
    {
        $org = Organization::create(['name' => 'Org 3', 'slug' => 'org-3', 'status' => 'active', 'partner_status' => 'approved']);
        $plan = PlatformPlan::create([
            'code' => 'BASIC',
            'name' => 'Gói Basic',
            'price' => 200000,
            'max_staff' => 1, // Limit: 1 staff
            'is_active' => true,
        ]);

        PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'plan_version' => 1,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $staffUser = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $staffUser->id,
            'role' => 'staff',
            'status' => 'active',
        ]);

        // Attempting to add second staff MUST fail
        $this->expectException(ValidationException::class);
        $this->enforceQuota->execute($org->id, 'staff', 1);
    }

    public function test_feature_gate_enforcement(): void
    {
        $org = Organization::create(['name' => 'Org 4', 'slug' => 'org-4', 'status' => 'active', 'partner_status' => 'approved']);
        $plan = PlatformPlan::create([
            'code' => 'TIER_1',
            'name' => 'Gói Tier 1',
            'price' => 200000,
            'features' => ['online_booking'], // Only online booking! No pos!
            'is_active' => true,
        ]);

        PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'plan_version' => 1,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        // Included feature succeeds (no exception)
        $this->enforceQuota->execute($org->id, 'online_booking');
        $this->assertTrue(true);

        // Excluded feature fails with 403
        $this->expectException(HttpException::class);
        $this->enforceQuota->execute($org->id, 'pos');
    }
}
