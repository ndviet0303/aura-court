<?php

namespace Tests\Feature\Account;

use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\SportZone;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationGrantsAndVenueScopingTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomer(string $email): User
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

    public function test_add_member_stores_venue_grants_and_function_permissions(): void
    {
        $owner = $this->createCustomer('owner@grant.test');
        $org = $this->createOrg('Grant Org', 'grant-org');
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);
        $venue1 = $this->createVenue($org, 'Venue One', 'venue-one');
        $venue2 = $this->createVenue($org, 'Venue Two', 'venue-two');

        // Invite a manager assigned only to Venue 1
        $manager = $this->createCustomer('manager_assigned@test.local');
        $invitationId = $this->actingAs($owner)->postJson("/api/organizations/{$org->id}/invitations", [
            'email' => 'manager_assigned@test.local',
            'role' => OrganizationMember::ROLE_VENUE_MANAGER,
            'venue_id' => $venue1->id,
            'venue_ids' => [$venue1->id],
            'permissions' => ['functions' => ['pricing', 'calendar']],
        ])->assertCreated()->json('invitation.id');

        $res = $this->actingAs($manager)->postJson("/api/invitations/{$invitationId}/accept")->assertOk();
        $this->assertEquals($venue1->id, $res->json('member.venue_id'));
        $this->assertEquals([$venue1->id], $res->json('member.venue_ids'));
        $this->assertEquals(['pricing', 'calendar'], $res->json('member.permissions.functions'));

        $createdMember = OrganizationMember::where('organization_id', $org->id)
            ->whereHas('user', fn ($q) => $q->where('email', 'manager_assigned@test.local'))
            ->firstOrFail();

        $this->assertEquals([$venue1->id], $createdMember->permissions['venue_ids']);
        $this->assertEquals($venue1->id, $createdMember->permissions['venue_id']);
    }

    public function test_update_member_role_and_revoke_venue_grant(): void
    {
        $owner = $this->createCustomer('owner@revoke.test');
        $org = $this->createOrg('Revoke Org', 'revoke-org');
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);
        $venueA = $this->createVenue($org, 'Venue A', 'venue-a');
        $venueB = $this->createVenue($org, 'Venue B', 'venue-b');
        $courtA = $this->createCourt($venueA, 'Court A');
        $courtB = $this->createCourt($venueB, 'Court B');

        $managerUser = $this->createCustomer('manager@revoke.test');
        $member = OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $managerUser->id,
            'role' => OrganizationMember::ROLE_VENUE_MANAGER,
            'permissions' => ['venue_ids' => [$venueA->id], 'venue_id' => $venueA->id],
            'status' => 'active',
        ]);

        // Manager can initially manage Venue A
        $this->assertTrue($managerUser->canManageVenue($venueA));
        $this->assertFalse($managerUser->canManageVenue($venueB));

        // Owner revokes Venue A grant and shifts manager to Venue B
        $updateRes = $this->actingAs($owner)->patchJson("/api/organizations/{$org->id}/members/{$member->id}", [
            'role' => OrganizationMember::ROLE_VENUE_MANAGER,
            'venue_id' => $venueB->id,
            'venue_ids' => [$venueB->id],
        ]);
        $updateRes->assertOk();

        // Refresh manager user and verify immediate effect
        $freshManager = $managerUser->fresh();
        $this->assertFalse($freshManager->canManageVenue($venueA));
        $this->assertTrue($freshManager->canManageVenue($venueB));

        // Direct request by manager to set prices on Court A is now denied
        $this->actingAs($managerUser)
            ->postJson("/api/admin/courts/{$courtA->id}/prices", [
                'weekday' => 1,
                'starts_at' => '09:00',
                'ends_at' => '10:00',
                'price' => 100000,
            ])
            ->assertStatus(403);

        // But setting prices on Court B works
        $this->actingAs($managerUser)
            ->postJson("/api/admin/courts/{$courtB->id}/prices", [
                'weekday' => 1,
                'starts_at' => '09:00',
                'ends_at' => '10:00',
                'price' => 100000,
            ])
            ->assertCreated();
    }

    public function test_organization_switch_does_not_depend_on_hardcoded_org_one(): void
    {
        $user = $this->createCustomer('multi_org_manager@aura.test');
        // Organizations with non-1 IDs
        $org10 = $this->createOrg('Tenth Org', 'tenth-org');
        $org20 = $this->createOrg('Twentieth Org', 'twentieth-org');

        $venue10 = $this->createVenue($org10, 'Venue 10', 'venue-10');
        $venue20 = $this->createVenue($org20, 'Venue 20', 'venue-20');

        // User is manager in Org 10, but suspended or customer in Org 20
        OrganizationMember::create([
            'organization_id' => $org10->id,
            'user_id' => $user->id,
            'role' => OrganizationMember::ROLE_VENUE_MANAGER,
            'permissions' => ['venue_ids' => [$venue10->id]],
            'status' => 'active',
        ]);
        OrganizationMember::create([
            'organization_id' => $org20->id,
            'user_id' => $user->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);

        // When acting on Org 10, user can list its members and manage Venue 10
        $this->actingAs($user)
            ->getJson("/api/organizations/{$org10->id}/members")
            ->assertOk();

        $this->actingAs($user)
            ->patchJson("/api/admin/venues/{$venue10->id}", ['address' => 'Updated 10'])
            ->assertOk();

        // When attempting to access Org 20 members, denied with 403
        $this->actingAs($user)
            ->getJson("/api/organizations/{$org20->id}/members")
            ->assertStatus(403);

        // Attempting to mutate Venue 20, denied with 403
        $this->actingAs($user)
            ->patchJson("/api/admin/venues/{$venue20->id}", ['address' => 'Hacked 20'])
            ->assertStatus(403);
    }
}
