<?php

namespace Tests\Feature\Account;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\SportZone;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerStaffGlobalAdminSeparationTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomerUser(string $email = 'user@test.local'): User
    {
        return User::factory()->create([
            'email' => $email,
            'role' => 'customer',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
    }

    private function createOrg(string $name, string $slug): Organization
    {
        return Organization::create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
    }

    private function createVenue(Organization $org, string $name, string $slug): Venue
    {
        return Venue::create([
            'organization_id' => $org->id,
            'name' => ['vi' => $name, 'en' => $name],
            'slug' => $slug,
            'address' => '123 Test Street',
            'google_maps_url' => 'https://maps.google.com/?q=test',
            'status' => 'published',
            'is_active' => true,
        ]);
    }

    private function createCourt(Venue $venue, string $name): Court
    {
        $zone = SportZone::create([
            'venue_id' => $venue->id,
            'name' => ['vi' => 'Khu Cầu Lông', 'en' => 'Badminton Zone'],
            'sport_type' => 'badminton',
            'slot_duration' => 60,
            'is_active' => true,
        ]);

        return Court::create([
            'venue_id' => $venue->id,
            'sport_zone_id' => $zone->id,
            'name' => ['vi' => $name, 'en' => $name],
            'is_active' => true,
        ]);
    }

    /**
     * Scenario: Owner without global admin
     * An approved customer-role owner operates their assigned venue and catalog without global admin
     */
    public function test_customer_role_owner_can_operate_venue_without_global_admin(): void
    {
        $owner = $this->createCustomerUser('owner@alpha.local');
        $this->assertEquals('customer', $owner->role);
        $this->assertFalse($owner->isAdmin());
        $this->assertFalse($owner->isPlatformAdmin());

        $org = $this->createOrg('Alpha Club', 'alpha-club');
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        $venue = $this->createVenue($org, 'Alpha Arena', 'alpha-arena');
        $court = $this->createCourt($venue, 'Court 1');

        // 1. Can list venues (sees their own venue)
        $venuesRes = $this->actingAs($owner)->getJson('/api/v1/admin/venues');
        $venuesRes->assertOk();
        $this->assertCount(1, $venuesRes->json());
        $this->assertEquals($venue->id, $venuesRes->json('0.id'));

        // 2. Can update their venue
        $updateRes = $this->actingAs($owner)->patchJson("/api/v1/admin/venues/{$venue->id}", [
            'address' => '456 Updated Street',
        ]);
        $updateRes->assertOk();
        $this->assertDatabaseHas('venues', [
            'id' => $venue->id,
            'address' => '456 Updated Street',
        ]);

        // 3. Can list courts
        $courtsRes = $this->actingAs($owner)->getJson('/api/v1/admin/courts');
        $courtsRes->assertOk();
        $this->assertCount(1, $courtsRes->json());

        // 4. Can set court prices (must be 60 minutes)
        $priceRes = $this->actingAs($owner)->postJson("/api/v1/admin/courts/{$court->id}/prices", [
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
            'price' => 150000,
            'is_active' => true,
        ]);
        $priceRes->assertCreated();

        // 5. Can view report
        $reportRes = $this->actingAs($owner)->getJson('/api/v1/admin/reports');
        $reportRes->assertOk();
    }

    /**
     * Scenario: Cross-tenant isolation
     * Owner Alpha cannot read, mutate or export data belonging to Organization Beta
     */
    public function test_owner_is_denied_access_to_other_tenant_resources(): void
    {
        $ownerAlpha = $this->createCustomerUser('owner@alpha.local');
        $orgAlpha = $this->createOrg('Alpha Club', 'alpha-club');
        OrganizationMember::create([
            'organization_id' => $orgAlpha->id,
            'user_id' => $ownerAlpha->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);
        $venueAlpha = $this->createVenue($orgAlpha, 'Alpha Arena', 'alpha-arena');

        $ownerBeta = $this->createCustomerUser('owner@beta.local');
        $orgBeta = $this->createOrg('Beta Club', 'beta-club');
        OrganizationMember::create([
            'organization_id' => $orgBeta->id,
            'user_id' => $ownerBeta->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);
        $venueBeta = $this->createVenue($orgBeta, 'Beta Arena', 'beta-arena');
        $courtBeta = $this->createCourt($venueBeta, 'Beta Court 1');

        // 1. Venues list only contains Alpha, not Beta
        $venuesRes = $this->actingAs($ownerAlpha)->getJson('/api/v1/admin/venues');
        $venuesRes->assertOk();
        $venueIds = collect($venuesRes->json())->pluck('id')->toArray();
        $this->assertContains($venueAlpha->id, $venueIds);
        $this->assertNotContains($venueBeta->id, $venueIds);

        // 2. Cannot update Beta venue
        $this->actingAs($ownerAlpha)
            ->patchJson("/api/v1/admin/venues/{$venueBeta->id}", ['address' => 'Hacked Address'])
            ->assertStatus(403);

        // 3. Cannot deactivate Beta venue
        $this->actingAs($ownerAlpha)
            ->deleteJson("/api/v1/admin/venues/{$venueBeta->id}")
            ->assertStatus(403);

        // 4. Cannot create court in Beta venue
        $this->actingAs($ownerAlpha)
            ->postJson('/api/v1/admin/courts', [
                'venue_id' => $venueBeta->id,
                'name' => 'Hacked Court',
            ])
            ->assertStatus(403);

        // 5. Cannot set prices on Beta court
        $this->actingAs($ownerAlpha)
            ->postJson("/api/v1/admin/courts/{$courtBeta->id}/prices", [
                'weekday' => 1,
                'starts_at' => '08:00',
                'ends_at' => '10:00',
                'price' => 50000,
            ])
            ->assertStatus(403);

        // 6. Cannot query or export reports for Beta venue
        $this->actingAs($ownerAlpha)
            ->getJson("/api/v1/admin/reports?venue_id={$venueBeta->id}")
            ->assertStatus(403);

        $this->actingAs($ownerAlpha)
            ->getJson("/api/v1/admin/reports.csv?venue_id={$venueBeta->id}")
            ->assertStatus(403);
    }

    /**
     * Scenario: Venue manager changes venue operations
     * Manager of Venue A in a multi-venue organization can operate Venue A,
     * but sibling venue Venue B operations and exports are denied.
     */
    public function test_venue_manager_can_operate_assigned_venue_but_is_denied_sibling_venue(): void
    {
        $org = $this->createOrg('Multi Venue Org', 'multi-venue-org');
        $venueA = $this->createVenue($org, 'Venue A', 'venue-a');
        $venueB = $this->createVenue($org, 'Venue B', 'venue-b'); // sibling venue
        $courtA = $this->createCourt($venueA, 'Court A1');
        $courtB = $this->createCourt($venueB, 'Court B1');

        $manager = $this->createCustomerUser('manager@org.local');
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $manager->id,
            'role' => OrganizationMember::ROLE_VENUE_MANAGER,
            'permissions' => ['venue_ids' => [$venueA->id]], // Only assigned to Venue A
            'status' => 'active',
        ]);

        // 1. Can update assigned Venue A
        $this->actingAs($manager)
            ->patchJson("/api/v1/admin/venues/{$venueA->id}", ['address' => 'Updated Venue A'])
            ->assertOk();

        // 2. Cannot update sibling Venue B
        $this->actingAs($manager)
            ->patchJson("/api/v1/admin/venues/{$venueB->id}", ['address' => 'Hacked Venue B'])
            ->assertStatus(403);

        // 3. Can set prices on Court A
        $this->actingAs($manager)
            ->postJson("/api/v1/admin/courts/{$courtA->id}/prices", [
                'weekday' => 2,
                'starts_at' => '14:00',
                'ends_at' => '15:00',
                'price' => 120000,
            ])
            ->assertCreated();

        // 4. Cannot set prices on sibling Court B
        $this->actingAs($manager)
            ->postJson("/api/v1/admin/courts/{$courtB->id}/prices", [
                'weekday' => 2,
                'starts_at' => '14:00',
                'ends_at' => '15:00',
                'price' => 120000,
            ])
            ->assertStatus(403);

        // 5. Cannot export reports for sibling Venue B
        $this->actingAs($manager)
            ->getJson("/api/v1/admin/reports?venue_id={$venueB->id}")
            ->assertStatus(403);

        $this->actingAs($manager)
            ->getJson("/api/v1/admin/reports.csv?venue_id={$venueB->id}")
            ->assertStatus(403);
    }

    /**
     * Scenario: Scope escalation prevention
     * Inviting an owner creates a customer-role account, NOT a global admin
     */
    public function test_inviting_owner_does_not_grant_global_admin(): void
    {
        $owner = $this->createCustomerUser('primary_owner@org.local');
        $org = $this->createOrg('Escalation Test Org', 'escalation-org');
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        $inviteRes = $this->actingAs($owner)->postJson("/api/v1/organizations/{$org->id}/members", [
            'email' => 'new_owner@test.local',
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
        ]);
        $inviteRes->assertCreated();

        $newAccount = User::where('email', 'new_owner@test.local')->firstOrFail();
        // Personal account role MUST remain customer, NOT admin
        $this->assertEquals('customer', $newAccount->role);
        $this->assertFalse($newAccount->isAdmin());
        $this->assertFalse($newAccount->isPlatformAdmin());

        // But within the organization, they are organization_owner
        $this->assertTrue($newAccount->hasRoleInOrganization($org->id, OrganizationMember::ROLE_ORGANIZATION_OWNER));
    }

    /**
     * Scenario: Platform admin operations denied to organization owners
     * Platform administration endpoints (KYC, disputes, partner applications review, payouts)
     * are strictly denied to organization owners.
     */
    public function test_platform_admin_endpoints_are_denied_to_organization_owners(): void
    {
        $owner = $this->createCustomerUser('owner@org.local');
        $org = $this->createOrg('Platform Denied Org', 'platform-denied-org');
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        // 1. KYC review denied
        $this->actingAs($owner)->getJson('/api/v1/admin/kyc')->assertStatus(403);

        // 2. Disputes arbitration denied
        $this->actingAs($owner)->getJson('/api/v1/admin/disputes')->assertStatus(403);

        // 3. Partner application review denied
        $this->actingAs($owner)->getJson('/api/v1/admin/partner/applications')->assertStatus(403);

        // 4. Publication queue denied
        $this->actingAs($owner)->getJson('/api/v1/admin/venues/publication-queue')->assertStatus(403);

        // 5. Payment review denied
        $this->actingAs($owner)->getJson('/api/v1/admin/payments')->assertStatus(403);
    }

    /**
     * Scenario: Cashier requests payout administration or organization administration
     * Denied without mutations.
     */
    public function test_cashier_cannot_access_payout_or_organization_administration(): void
    {
        $cashier = $this->createCustomerUser('cashier@org.local');
        $org = $this->createOrg('Cashier Org', 'cashier-org');
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $cashier->id,
            'role' => OrganizationMember::ROLE_CASHIER,
            'status' => 'active',
        ]);

        // 1. Cashier cannot modify organization settings
        $this->actingAs($cashier)
            ->patchJson("/api/v1/organizations/{$org->id}", ['name' => 'Hacked Club'])
            ->assertStatus(403);

        // 2. Cashier cannot add or manage organization members
        $this->actingAs($cashier)
            ->postJson("/api/v1/organizations/{$org->id}/members", [
                'email' => 'cashier_friend@test.local',
                'role' => OrganizationMember::ROLE_STAFF,
            ])
            ->assertStatus(403);

        // 3. Cashier cannot access payouts administration
        $this->actingAs($cashier)
            ->getJson('/api/v2/finance/payouts')
            ->assertStatus(403);
    }
}
