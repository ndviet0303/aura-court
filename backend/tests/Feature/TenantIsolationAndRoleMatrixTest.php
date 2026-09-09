<?php

namespace Tests\Feature;

use App\Models\ElevatedAuditLog;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\Venue;
use App\Services\Infrastructure\ElevatedAccessService;
use App\Services\Infrastructure\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationAndRoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_or_modify_another_organizations_data(): void
    {
        // Setup Org 1
        $owner1 = User::factory()->create(['email' => 'owner1@aura.test']);
        $org1 = Organization::create(['name' => 'Organization Alpha', 'slug' => 'org-alpha']);
        OrganizationMember::create([
            'organization_id' => $org1->id,
            'user_id' => $owner1->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
        ]);

        // Setup Org 2
        $owner2 = User::factory()->create(['email' => 'owner2@aura.test']);
        $org2 = Organization::create(['name' => 'Organization Beta', 'slug' => 'org-beta']);
        OrganizationMember::create([
            'organization_id' => $org2->id,
            'user_id' => $owner2->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
        ]);

        // Owner 2 attempts to read Org 1 details
        $this->actingAs($owner2)
            ->getJson("/api/v1/organizations/{$org1->id}")
            ->assertStatus(403);

        // Owner 2 attempts to modify Org 1
        $this->actingAs($owner2)
            ->patchJson("/api/v1/organizations/{$org1->id}", ['name' => 'Hacked Name'])
            ->assertStatus(403);

        // Owner 1 can access Org 1
        $this->actingAs($owner1)
            ->getJson("/api/v1/organizations/{$org1->id}")
            ->assertOk()
            ->assertJsonPath('organization.name', 'Organization Alpha');
    }

    public function test_role_matrix_enforcement_for_owner_manager_cashier_staff_customer(): void
    {
        $org = Organization::create(['name' => 'Aura Central', 'slug' => 'aura-central']);
        
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $cashier = User::factory()->create();
        $customer = User::factory()->create();

        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER]);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $manager->id, 'role' => OrganizationMember::ROLE_VENUE_MANAGER]);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $cashier->id, 'role' => OrganizationMember::ROLE_CASHIER]);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $customer->id, 'role' => OrganizationMember::ROLE_CUSTOMER]);

        $newUser = User::factory()->create(['email' => 'newstaff@aura.test']);

        // 1. Customer cannot add member
        $this->actingAs($customer)
            ->postJson("/api/v1/organizations/{$org->id}/members", [
                'email' => $newUser->email,
                'role' => OrganizationMember::ROLE_STAFF,
            ])
            ->assertStatus(403);

        // 2. Cashier cannot add member
        $this->actingAs($cashier)
            ->postJson("/api/v1/organizations/{$org->id}/members", [
                'email' => $newUser->email,
                'role' => OrganizationMember::ROLE_STAFF,
            ])
            ->assertStatus(403);

        // 3. Manager can view members
        $this->actingAs($manager)
            ->getJson("/api/v1/organizations/{$org->id}/members")
            ->assertOk();

        // 4. Owner can add member
        $this->actingAs($owner)
            ->postJson("/api/v1/organizations/{$org->id}/members", [
                'email' => $newUser->email,
                'role' => OrganizationMember::ROLE_STAFF,
            ])
            ->assertCreated()
            ->assertJsonPath('member.role', 'staff');
    }

    public function test_audited_elevated_access_creates_immutable_log(): void
    {
        $platformAdmin = User::factory()->create(['role' => 'admin']);
        $org = Organization::create(['name' => 'Tenant 99', 'slug' => 'tenant-99']);

        $service = app(ElevatedAccessService::class);
        $result = $service->executeElevated(
            $platformAdmin,
            'FINANCE_PAYOUT_OVERRIDE',
            'Resolving bank account reconciliation issue #881',
            $org->id,
            function () use ($org) {
                return 'Resolved successfully';
            }
        );

        $this->assertEquals('Resolved successfully', $result);

        $this->assertDatabaseHas('elevated_audit_logs', [
            'user_id' => $platformAdmin->id,
            'organization_id' => $org->id,
            'action' => 'FINANCE_PAYOUT_OVERRIDE',
            'purpose' => 'Resolving bank account reconciliation issue #881',
            'outcome' => 'success',
        ]);
    }

    public function test_tenant_context_scopes_cache_keys_and_realtime_channels(): void
    {
        $org = Organization::create(['name' => 'Org 42', 'slug' => 'org-42']);
        $context = app(TenantContext::class);

        $context->setOrganization($org);

        $this->assertEquals("tenant:{$org->id}:court_availability", $context->scopedCacheKey('court_availability'));
        $this->assertEquals("private-tenant.{$org->id}.tournament_bracket", $context->scopedChannelName('tournament_bracket'));

        $context->clear();
        $this->assertEquals('tenant:global:court_availability', $context->scopedCacheKey('court_availability'));
    }

    public function test_multitenant_user_memberships_are_independent(): void
    {
        $user = User::factory()->create();
        $orgA = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $orgB = Organization::create(['name' => 'Org B', 'slug' => 'org-b']);

        OrganizationMember::create(['organization_id' => $orgA->id, 'user_id' => $user->id, 'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER]);
        OrganizationMember::create(['organization_id' => $orgB->id, 'user_id' => $user->id, 'role' => OrganizationMember::ROLE_CUSTOMER]);

        $this->assertTrue($user->hasRoleInOrganization($orgA->id, OrganizationMember::ROLE_ORGANIZATION_OWNER));
        $this->assertFalse($user->hasRoleInOrganization($orgB->id, OrganizationMember::ROLE_ORGANIZATION_OWNER));
        $this->assertTrue($user->hasRoleInOrganization($orgB->id, OrganizationMember::ROLE_CUSTOMER));
    }

    public function test_organization_switch_refreshes_role_and_enforces_matrix_across_all_roles(): void
    {
        $orgA = Organization::create(['name' => 'Org A (POS Enabled)', 'slug' => 'org-a-'.uniqid()]);
        $orgB = Organization::create(['name' => 'Org B (POS Enabled)', 'slug' => 'org-b-'.uniqid()]);

        config([
            'features.pilot_organizations' => [(string) $orgA->id, (string) $orgB->id],
            'features.flags.tenant_pos_and_wallet' => true,
        ]);

        // User is cashier in Org A, but customer in Org B
        $user = User::factory()->create();
        OrganizationMember::create(['organization_id' => $orgA->id, 'user_id' => $user->id, 'role' => OrganizationMember::ROLE_CASHIER, 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $orgB->id, 'user_id' => $user->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);

        // 1. In Org A: user has cashier access
        $this->actingAs($user)
            ->withHeader('X-Organization-ID', (string) $orgA->id)
            ->getJson('/api/v2/pos/shifts/active')
            ->assertOk();

        // 2. Scenario: Organization switch
        // When cashier switches to Org B where they are only a customer:
        // Cashier actions MUST be unavailable and direct privileged requests rejected with 403
        $this->actingAs($user)
            ->withHeader('X-Organization-ID', (string) $orgB->id)
            ->getJson('/api/v2/pos/shifts/active')
            ->assertStatus(403);

        $this->actingAs($user)
            ->withHeader('X-Organization-ID', (string) $orgB->id)
            ->postJson('/api/v2/pos/shifts', ['opening_cash' => 100000])
            ->assertStatus(403);

        // 3. Staff role tests: staff can view active shift, but cannot open shift or review reconciliation
        $staffUser = User::factory()->create();
        OrganizationMember::create(['organization_id' => $orgA->id, 'user_id' => $staffUser->id, 'role' => OrganizationMember::ROLE_STAFF, 'status' => 'active']);

        $this->actingAs($staffUser)
            ->withHeader('X-Organization-ID', (string) $orgA->id)
            ->getJson('/api/v2/pos/shifts/active')
            ->assertOk();

        $this->actingAs($staffUser)
            ->withHeader('X-Organization-ID', (string) $orgA->id)
            ->postJson('/api/v2/pos/shifts', ['opening_cash' => 100000])
            ->assertStatus(403);

        // 4. Cashier role tests: cashier can open shift, but cannot review reconciliation
        $openShiftRes = $this->actingAs($user)
            ->withHeader('X-Organization-ID', (string) $orgA->id)
            ->withHeader('Idempotency-Key', 'open-shift-cashier')
            ->postJson('/api/v2/pos/shifts', ['opening_cash' => 100000])
            ->assertCreated();
        $createdShiftId = $openShiftRes->json('shift.id');

        // Attempting to review shift as cashier -> 403 Forbidden
        $this->actingAs($user)
            ->withHeader('X-Organization-ID', (string) $orgA->id)
            ->postJson("/api/v2/pos/shifts/{$createdShiftId}/review", ['status' => 'approved'])
            ->assertStatus(403);
        // 5. Manager role tests: manager can review shift reconciliation (fails with 404 shift not found, not 403 forbidden)
        $managerUser = User::factory()->create();
        OrganizationMember::create(['organization_id' => $orgA->id, 'user_id' => $managerUser->id, 'role' => OrganizationMember::ROLE_VENUE_MANAGER, 'status' => 'active']);

        $this->actingAs($managerUser)
            ->withHeader('X-Organization-ID', (string) $orgA->id)
            ->postJson('/api/v2/pos/shifts/999999/review', ['status' => 'approved'])
            ->assertNotFound();

        // 6. Owner role tests: owner can update organization settings
        $ownerUser = User::factory()->create();
        OrganizationMember::create(['organization_id' => $orgA->id, 'user_id' => $ownerUser->id, 'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER, 'status' => 'active']);

        $this->actingAs($ownerUser)
            ->withHeader('X-Organization-ID', (string) $orgA->id)
            ->patchJson("/api/v2/organizations/{$orgA->id}", ['name' => 'Org A Updated'])
            ->assertOk()
            ->assertJsonPath('organization.name', 'Org A Updated');

        // Non-owner cannot update organization settings
        $this->actingAs($managerUser)
            ->withHeader('X-Organization-ID', (string) $orgA->id)
            ->patchJson("/api/v2/organizations/{$orgA->id}", ['name' => 'Org A Hacked'])
            ->assertStatus(403);

        // 7. Elevated platform admin tests: can access without being an organization member
        $platformAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($platformAdmin)
            ->withHeader('X-Organization-ID', (string) $orgA->id)
            ->getJson('/api/v2/pos/shifts/active')
            ->assertOk();
    }

    public function test_effective_tenant_capabilities_endpoint_exposes_pilot_and_default_flags(): void
    {
        $pilotOrg = Organization::create(['name' => 'Pilot Org', 'slug' => 'pilot-'.uniqid()]);
        $standardOrg = Organization::create(['name' => 'Standard Org', 'slug' => 'standard-'.uniqid()]);

        config([
            'features.pilot_organizations' => [(string) $pilotOrg->id],
            'features.flags.tenant_pos_and_wallet' => true,
            'features.flags.marketplace_payouts' => true,
        ]);

        $user = User::factory()->create();
        OrganizationMember::create(['organization_id' => $pilotOrg->id, 'user_id' => $user->id, 'role' => 'cashier', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $standardOrg->id, 'user_id' => $user->id, 'role' => 'cashier', 'status' => 'active']);

        // 1. Pilot organization has POS enabled
        $pilotRes = $this->actingAs($user)
            ->withHeader('X-Organization-ID', (string) $pilotOrg->id)
            ->getJson('/api/v2/capabilities');

        $pilotRes->assertOk()
            ->assertJsonPath('organization_id', $pilotOrg->id)
            ->assertJsonPath('role', 'cashier')
            ->assertJsonPath('capabilities.tenant_pos_and_wallet', true)
            ->assertJsonPath('capabilities.marketplace_payouts', true);

        // 2. Standard non-pilot organization has POS disabled
        $standardRes = $this->actingAs($user)
            ->withHeader('X-Organization-ID', (string) $standardOrg->id)
            ->getJson('/api/v2/capabilities');

        $standardRes->assertOk()
            ->assertJsonPath('organization_id', $standardOrg->id)
            ->assertJsonPath('role', 'cashier')
            ->assertJsonPath('capabilities.tenant_pos_and_wallet', false)
            ->assertJsonPath('capabilities.marketplace_payouts', false);

        // 3. Direct route guard test: POS routes on standard org fail with 404
        $this->actingAs($user)
            ->withHeader('X-Organization-ID', (string) $standardOrg->id)
            ->getJson('/api/v2/pos/products')
            ->assertStatus(404);
    }
}
