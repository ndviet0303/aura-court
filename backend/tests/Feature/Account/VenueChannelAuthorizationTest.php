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

    public function test_restricted_accounts_cannot_join_the_venue_channel(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $venue = $fixture['org1']['venue1'];
        $staff = $this->member($fixture['org1']['org']->id, OrganizationMember::ROLE_STAFF, ['venue_ids' => [$venue->id]]);
        $this->assertTrue($this->canJoin($staff, $venue));

        foreach (['locked', 'suspended', 'banned'] as $status) {
            $staff->update(['status' => $status]);
            $this->assertFalse($this->canJoin($staff->fresh(), $venue), $status);
        }
        $fixture['org1']['owner']->update(['status' => 'locked']);
        $this->assertFalse($this->canJoin($fixture['org1']['owner']->fresh(), $venue));
    }

    public function test_broadcasting_auth_refuses_restricted_accounts_and_revoked_sessions(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $venue = $fixture['org1']['venue1'];
        $staff = $this->member($fixture['org1']['org']->id, OrganizationMember::ROLE_STAFF, ['venue_ids' => [$venue->id]]);
        $channel = ['socket_id' => '1234.5678', 'channel_name' => 'private-venue.'.$venue->id];

        // BR-23: locked then unlocked, so the session signed in before the lock stays ended.
        $staff->forceFill(['status' => 'active', 'sessions_revoked_at' => now()])->save();
        $this->actingAs($staff)->withSession(['auth_at' => now()->subHour()->getTimestamp()])
            ->postJson('/broadcasting/auth', $channel)->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->actingAs($staff->fresh())->withSession(['auth_at' => now()->addSecond()->getTimestamp()])
            ->postJson('/broadcasting/auth', $channel)->assertOk();

        $staff->forceFill(['status' => 'locked'])->save();
        $this->app['auth']->forgetGuards();
        $this->actingAs($staff->fresh())->withSession(['auth_at' => now()->addSecond()->getTimestamp()])
            ->postJson('/broadcasting/auth', $channel)->assertForbidden();
    }
}
