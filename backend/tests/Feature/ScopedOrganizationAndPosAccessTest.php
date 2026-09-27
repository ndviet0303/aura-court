<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ScopedOrganizationAndPosAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('features.flags.tenant_pos_and_wallet', true);
        Config::set('features.pilot_organizations', []);
    }
    public function test_owner_can_access_scoped_organization_data_without_platform_admin(): void
    {
        $org = Organization::create(['name' => 'Aura Scoped Org', 'slug' => 'aura-scoped-'.uniqid()]);
        $owner = User::factory()->create([
            'role' => 'customer', // Preserved global role!
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
            'permissions' => ['*'],
        ]);

        $this->assertFalse($owner->isPlatformAdmin());

        // 1. Owner can access their organization details
        $this->actingAs($owner)
            ->getJson("/api/v1/organizations/{$org->id}")
            ->assertOk()
            ->assertJsonPath('organization.id', $org->id)
            ->assertJsonPath('organization.name', 'Aura Scoped Org');

        // 2. Owner can view members
        $this->actingAs($owner)
            ->getJson("/api/v1/organizations/{$org->id}/members")
            ->assertOk();

        // 3. Owner can access their scoped venue routes, but cannot access platform-admin routes
        $this->actingAs($owner)
            ->getJson('/api/v1/admin/venues')
            ->assertOk();

        $this->actingAs($owner)
            ->getJson('/api/v1/admin/kyc')
            ->assertStatus(403);
    }

    public function test_cashier_can_access_pos_but_is_denied_from_owner_and_platform_admin(): void
    {
        $org = Organization::create(['name' => 'POS Org', 'slug' => 'pos-org-'.uniqid()]);
        Config::set('features.pilot_organizations', [(string) $org->id]);
        $cashier = User::factory()->create(['role' => 'customer']);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $cashier->id,
            'role' => OrganizationMember::ROLE_CASHIER,
            'status' => 'active',
            'permissions' => ['pos.sell', 'pos.shift'],
        ]);

        // 1. Cashier can read POS products and shifts under their organization header
        $this->actingAs($cashier)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->getJson('/api/v2/pos/products')
            ->assertOk();

        // 2. Cashier cannot perform owner administration (e.g. member modifications)
        $targetUser = User::factory()->create();
        $this->actingAs($cashier)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->postJson("/api/v2/organizations/{$org->id}/members", [
                'email' => $targetUser->email,
                'role' => 'staff',
            ])
            ->assertStatus(403);
        // 3. Cashier cannot perform shift reconciliation reviews (requires owner/manager)
        $shift = \App\Models\CashShift::create([
            'organization_id' => $org->id,
            'cashier_id' => $cashier->id,
            'status' => 'closed',
            'opened_at' => now()->subHours(8),
            'closed_at' => now(),
            'opening_cash' => 100000,
            'declared_cash' => 100000,
        ]);
        $this->actingAs($cashier)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->postJson("/api/v2/pos/shifts/{$shift->id}/review", [
                'status' => 'approved',
            ])
            ->assertStatus(403);
        // 4. Cashier cannot access platform-only routes
        $this->actingAs($cashier)
            ->getJson('/api/v1/admin/venues')
            ->assertStatus(403);
    }

    public function test_customer_and_foreign_organization_denials(): void
    {
        $org1 = Organization::create(['name' => 'Org Alpha', 'slug' => 'alpha-'.uniqid()]);
        $org2 = Organization::create(['name' => 'Org Beta', 'slug' => 'beta-'.uniqid()]);

        $pureCustomer = User::factory()->create(['role' => 'customer']);
        $owner2 = User::factory()->create(['role' => 'customer']);

        OrganizationMember::create([
            'organization_id' => $org2->id,
            'user_id' => $owner2->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        // 1. Pure customer cannot read org1
        $this->actingAs($pureCustomer)
            ->getJson("/api/v1/organizations/{$org1->id}")
            ->assertStatus(403);

        // 2. Owner of Org2 cannot read Org1
        $this->actingAs($owner2)
            ->getJson("/api/v1/organizations/{$org1->id}")
            ->assertStatus(403);

        // 3. Owner of Org2 cannot modify Org1
        $this->actingAs($owner2)
            ->patchJson("/api/v1/organizations/{$org1->id}", ['name' => 'Unauthorized'])
            ->assertStatus(403);

        // 4. Owner of Org2 cannot fetch Org1 capabilities (v1 and v2 return 403)
        $this->actingAs($owner2)
            ->getJson("/api/v1/organizations/{$org1->id}/capabilities")
            ->assertStatus(403);

        $this->actingAs($owner2)
            ->getJson("/api/v2/organizations/{$org1->id}/capabilities")
            ->assertStatus(403);
    }

    public function test_disabled_pos_capability_blocks_pos_routes_truthfully(): void
    {
        $org = Organization::create(['name' => 'Non Pilot Org', 'slug' => 'non-pilot-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'customer']);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        // Disable POS or omit org from pilot cohort
        Config::set('features.flags.tenant_pos_and_wallet', true);
        Config::set('features.pilot_organizations', ['99999']); // Org not included

        // 1. POS route returns 404 (capability disabled)
        $this->actingAs($owner)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->getJson('/api/v2/pos/products')
            ->assertStatus(404);

        // 2. Capabilities endpoint accurately reflects tenant_pos_and_wallet = false
        $this->actingAs($owner)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->getJson('/api/v2/capabilities')
            ->assertOk()
            ->assertJsonPath('organization_id', $org->id)
            ->assertJsonPath('capabilities.tenant_pos_and_wallet', false);
    }

    public function test_provision_pos_command_validates_required_targets(): void
    {
        // 1. Missing target information
        $this->artisan('org:provision-pos')
            ->assertExitCode(1)
            ->expectsOutputToContain('Target information is incomplete');

        // 2. Missing organization target
        $this->artisan('org:provision-pos --target-env=local --email=test@aura.test')
            ->assertExitCode(1)
            ->expectsOutputToContain('Target information is incomplete');

        // 3. Invalid environment
        $this->artisan('org:provision-pos --target-env=unknown --email=test@aura.test --org=1')
            ->assertExitCode(1)
            ->expectsOutputToContain("Invalid environment 'unknown'");

        // 4. Non-existent organization
        $this->artisan('org:provision-pos --target-env=local --email=test@aura.test --org=999999')
            ->assertExitCode(1)
            ->expectsOutputToContain('Organization with ID #999999 does not exist');
    }

    public function test_provision_pos_command_gates_production_environment(): void
    {
        $org = Organization::create(['name' => 'Prod Org', 'slug' => 'prod-'.uniqid()]);
        $user = User::factory()->create(['email' => 'prod-user@aura.test']);

        // Running against production without --confirm-production is halted
        $this->artisan("org:provision-pos --target-env=production --email={$user->email} --org={$org->id}")
            ->assertExitCode(1)
            ->expectsOutputToContain('EXECUTION GATED: Production target detected');

        // Database was NOT modified
        $this->assertDatabaseMissing('organization_members', [
            'organization_id' => $org->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_provision_pos_command_preserves_credentials_and_assigns_idempotently(): void
    {
        $org = Organization::create(['name' => 'Target Org', 'slug' => 'target-'.uniqid()]);
        $knownPasswordHash = Hash::make('MySuperSecretPassword@123');
        $user = User::factory()->create([
            'email' => 'existing-user@aura.test',
            'password' => $knownPasswordHash,
            'role' => 'customer', // Global role customer
            'onboarded' => false,
        ]);

        // 1. Dry run does not commit changes
        $this->artisan("org:provision-pos --target-env=local --email={$user->email} --org={$org->id} --role=organization_owner --dry-run")
            ->assertExitCode(0)
            ->expectsOutputToContain('DRY-RUN COMPLETE');

        $this->assertDatabaseMissing('organization_members', [
            'organization_id' => $org->id,
            'user_id' => $user->id,
        ]);

        // 2. Real execution provisions role and preserves credentials
        $this->artisan("org:provision-pos --target-env=local --email={$user->email} --org={$org->id} --role=organization_owner")
            ->assertExitCode(0)
            ->expectsOutputToContain('SUCCESS');

        $user->refresh();
        $this->assertEquals($knownPasswordHash, $user->password, 'Password hash MUST NOT be overwritten or modified!');
        $this->assertEquals('customer', $user->role, 'Global role MUST remain customer!');
        $this->assertFalse($user->onboarded, 'Existing user onboarded status MUST be preserved as-is!');
        $member = OrganizationMember::where('organization_id', $org->id)->where('user_id', $user->id)->first();
        $this->assertNotNull($member);
        $this->assertEquals(OrganizationMember::ROLE_ORGANIZATION_OWNER, $member->role);
        $this->assertEquals('active', $member->status);

        // 3. Idempotent re-run does not fail or alter credentials
        $this->artisan("org:provision-pos --target-env=local --email={$user->email} --org={$org->id} --role=organization_owner")
            ->assertExitCode(0)
            ->expectsOutputToContain('SUCCESS');

        $user->refresh();
        $this->assertEquals($knownPasswordHash, $user->password);
    }

    public function test_provision_pos_command_provisions_cashier_and_reports_cohort_status(): void
    {
        $org = Organization::create(['name' => 'Cashier Org', 'slug' => 'cashier-'.uniqid()]);
        $user = User::factory()->create(['email' => 'cashier@aura.test']);

        // Configure org not in pilot cohort
        Config::set('features.flags.tenant_pos_and_wallet', true);
        Config::set('features.pilot_organizations', []);

        $this->artisan("org:provision-pos --target-env=staging --email={$user->email} --org={$org->id} --role=cashier")
            ->assertExitCode(0)
            ->expectsOutputToContain("NOTICE: POS capability ('tenant_pos_and_wallet') is DISABLED for Organization #{$org->id}")
            ->expectsOutputToContain('Verification will report capability-disabled separately from role assignment');

        $member = OrganizationMember::where('organization_id', $org->id)->where('user_id', $user->id)->first();
        $this->assertNotNull($member);
        $this->assertEquals(OrganizationMember::ROLE_CASHIER, $member->role);
    }
}
