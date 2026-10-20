<?php

namespace Tests\Feature\Venue;

use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerVenueReadAccessTest extends TestCase
{
    use RefreshDatabase;

    private function member(int $organizationId, string $role, array $permissions = []): User
    {
        $user = User::factory()->create(['role' => 'customer']);
        OrganizationMember::create([
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
            'permissions' => $permissions,
        ]);

        return $user;
    }

    /** @return array<string, string> */
    private function venueUrls(int $organizationId, int $venueId): array
    {
        $base = "/api/partner/organizations/{$organizationId}/venues";

        return ['index' => $base, 'show' => "{$base}/{$venueId}", 'readiness' => "{$base}/{$venueId}/readiness"];
    }

    public function test_users_outside_the_organization_cannot_read_its_venues(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $org = $fixture['org1']['org'];
        $outsiders = [
            'customer' => User::factory()->create(['role' => 'customer']),
            'customer member' => $this->member($org->id, OrganizationMember::ROLE_CUSTOMER),
            'owner of another organization' => $fixture['org2']['owner'],
            // BR-23: platform admin rights do not stand in for an organization membership.
            'platform admin' => User::factory()->create(['role' => 'platform_admin']),
        ];

        foreach ($outsiders as $label => $user) {
            foreach ($this->venueUrls($org->id, $fixture['org1']['venue1']->id) as $endpoint => $url) {
                $response = $this->actingAs($user)->getJson($url);
                $this->assertSame(403, $response->status(), "{$label} on {$endpoint}");
                $response->assertJsonMissing(['name' => 'Venue 1-A Primary']);
            }
        }
    }

    public function test_venue_of_another_organization_is_not_found_through_the_route_organization(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $foreignVenue = $fixture['org2']['venue'];
        $urls = $this->venueUrls($fixture['org1']['org']->id, $foreignVenue->id);

        $this->actingAs($fixture['org1']['owner'])->getJson($urls['show'])->assertNotFound()->assertJsonMissing(['name' => $foreignVenue->name]);
        $this->actingAs($fixture['org1']['owner'])->getJson($urls['readiness'])->assertNotFound();
        // An outsider gets the same 403 for any venue id, so the status code reveals nothing about where a venue lives
        foreach ([$foreignVenue->id, $fixture['org1']['venue1']->id, 999999] as $venueId) {
            $this->actingAs($fixture['org2']['owner'])->getJson("/api/partner/organizations/{$fixture['org1']['org']->id}/venues/{$venueId}")
                ->assertForbidden()->assertJsonMissing(['name' => $foreignVenue->name]);
        }
    }

    public function test_owner_reads_every_venue_of_the_organization(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $org = $fixture['org1']['org'];
        $owner = $fixture['org1']['owner'];
        $urls = $this->venueUrls($org->id, $fixture['org1']['venue2']->id);

        $this->actingAs($owner)->getJson($urls['index'])
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonMissing(['name' => $fixture['org2']['venue']->name]);
        $this->actingAs($owner)->getJson($urls['show'])->assertOk()->assertJsonPath('data.id', $fixture['org1']['venue2']->id);
        $this->actingAs($owner)->getJson($urls['readiness'])->assertOk();
    }

    public function test_staff_read_only_the_venues_assigned_to_them(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $org = $fixture['org1']['org'];
        $assigned = $fixture['org1']['venue1'];
        $sibling = $fixture['org1']['venue2'];
        $staff = $this->member($org->id, OrganizationMember::ROLE_STAFF, ['venue_ids' => [$assigned->id]]);

        $this->actingAs($staff)->getJson("/api/partner/organizations/{$org->id}/venues")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $assigned->id);

        $assignedUrls = $this->venueUrls($org->id, $assigned->id);
        $this->actingAs($staff)->getJson($assignedUrls['show'])->assertOk()->assertJsonPath('data.id', $assigned->id);
        $this->actingAs($staff)->getJson($assignedUrls['readiness'])->assertOk();

        $siblingUrls = $this->venueUrls($org->id, $sibling->id);
        $this->actingAs($staff)->getJson($siblingUrls['show'])->assertForbidden()->assertJsonMissing(['name' => $sibling->name]);
        $this->actingAs($staff)->getJson($siblingUrls['readiness'])->assertForbidden();
    }

    public function test_organization_detail_is_scoped_to_the_viewer_role(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $org = $fixture['org1']['org'];
        $assigned = $fixture['org1']['venue1'];
        $staff = $this->member($org->id, OrganizationMember::ROLE_STAFF, ['venue_ids' => [$assigned->id]]);
        $customer = $this->member($org->id, OrganizationMember::ROLE_CUSTOMER);

        $staffView = $this->actingAs($staff)->getJson("/api/organizations/{$org->id}")->assertOk();
        $this->assertSame([$assigned->id], array_column($staffView->json('organization.venues'), 'id'));
        $staffView->assertJsonPath('organization.venues.0.courts.0.id', $fixture['org1']['court1']->id)
            ->assertJsonMissingPath('organization.members')
            ->assertJsonMissing(['email' => $fixture['org1']['owner']->email]);

        $this->actingAs($customer)->getJson("/api/organizations/{$org->id}")->assertForbidden();

        $ownerView = $this->actingAs($fixture['org1']['owner'])->getJson("/api/organizations/{$org->id}")->assertOk();
        $this->assertCount(2, $ownerView->json('organization.venues'));
        $this->assertCount(3, $ownerView->json('organization.members'));

        $this->actingAs(User::factory()->create(['role' => 'platform_admin']))->getJson("/api/organizations/{$org->id}")
            ->assertOk()
            ->assertJsonCount(2, 'organization.venues')
            ->assertJsonCount(3, 'organization.members');
    }
}
