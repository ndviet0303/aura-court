<?php

namespace Tests\Feature\Account;

use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class VenueChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function canJoin(User $user, Venue $venue): bool
    {
        // The test broadcaster is null, so call the registered callback the auth endpoint would run.
        return Broadcast::getChannels()['venue.{venueId}']($user, $venue->id);
    }

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

    public function test_venue_channel_follows_current_roles_and_venue_scope(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $org = $fixture['org1']['org'];
        $venue1 = $fixture['org1']['venue1'];
        $venue2 = $fixture['org1']['venue2'];
        $owner = $fixture['org1']['owner'];

        $this->assertFalse($owner->isPlatformAdmin());
        $this->assertTrue($this->canJoin($owner, $venue1));
        $this->assertTrue($this->canJoin($owner, $venue2));
        $this->assertFalse($this->canJoin($owner, $fixture['org2']['venue']));

        $scopedManager = $this->member($org->id, OrganizationMember::ROLE_STAFF, ['venue_ids' => [$venue1->id]]);
        $this->assertTrue($this->canJoin($scopedManager, $venue1));
        $this->assertFalse($this->canJoin($scopedManager, $venue2));

        // BR-20: staff without an assigned venue reach none, and platform admin rights do not stand in.
        $unscopedStaff = $this->member($org->id, OrganizationMember::ROLE_STAFF, ['pos.sell']);
        $this->assertFalse($this->canJoin($unscopedStaff, $venue2));
        $this->assertFalse($this->canJoin(User::factory()->create(['role' => 'platform_admin']), $venue1));

        $customer = $this->member($org->id, OrganizationMember::ROLE_CUSTOMER);
        $this->assertFalse($this->canJoin($customer, $venue1));
    }
}
