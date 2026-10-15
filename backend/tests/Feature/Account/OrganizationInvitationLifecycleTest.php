<?php

namespace Tests\Feature\Account;

use App\Models\ElevatedAuditLog;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationInvitationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomerUser(string $email, string $name = 'Test User'): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'role' => 'customer',
            'status' => 'active',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
    }

    private function createOrg(string $name = 'Alpha Club', string $slug = 'alpha-club'): Organization
    {
        return Organization::create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
    }

    private function orgWithOwner(): array
    {
        $owner = $this->createCustomerUser('owner@alpha.local', 'Owner Alpha');
        $org = $this->createOrg();
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        return [$owner, $org];
    }

    private function invite(User $owner, Organization $org, string $email, string $role = OrganizationMember::ROLE_STAFF, array $extra = []): OrganizationInvitation
    {
        $this->actingAs($owner)->postJson("/api/organizations/{$org->id}/invitations", ['email' => $email, 'role' => $role] + $extra)
            ->assertStatus(201);

        return OrganizationInvitation::where('email', $email)->latest('id')->firstOrFail();
    }

    /**
     * BR-17/BR-18: an invitation goes to an existing account, lasts 7 days, and the invitee is told
     * which organization, who invited them and until when.
     */
    public function test_owner_invites_an_existing_account_with_a_seven_day_notice(): void
    {
        [$owner, $org] = $this->orgWithOwner();
        $venue = Venue::create(['organization_id' => $org->id, 'name' => 'Venue Alpha 1', 'slug' => 'venue-alpha-1', 'is_active' => true]);
        $invitee = $this->createCustomerUser('newstaff@alpha.local', 'New Staff');

        $res = $this->actingAs($owner)->postJson("/api/organizations/{$org->id}/invitations", [
            'email' => 'NewStaff@Alpha.local',
            'role' => OrganizationMember::ROLE_VENUE_MANAGER,
            'venue_id' => $venue->id,
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('invitation.email', 'newstaff@alpha.local')
            ->assertJsonPath('invitation.status', 'pending')
            ->assertJsonMissingPath('invitation.token');

        $invitation = OrganizationInvitation::firstOrFail();
        $this->assertEqualsWithDelta(now()->addDays(7)->getTimestamp(), $invitation->expires_at->getTimestamp(), 5);
        $this->assertDatabaseMissing('organization_members', ['organization_id' => $org->id, 'user_id' => $invitee->id]);

        $notice = $invitee->notifications()->firstOrFail()->data;
        $this->assertSame('Lời mời tham gia quản trị', $notice['title']);
        $this->assertStringContainsString('Owner Alpha', $notice['body']);
        $this->assertStringContainsString($org->name, $notice['body']);
        $this->assertStringContainsString($invitation->expires_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y'), $notice['body']);
        $this->assertSame('/invitations', $notice['action_url']);

        $audit = ElevatedAuditLog::where('action', 'STAFF_INVITATION_CREATED')->firstOrFail();
        $this->assertSame($owner->id, $audit->user_id);
        $this->assertArrayNotHasKey('token', $audit->metadata);
    }

    /** BR-17: who may not be invited. */
    public function test_invitations_are_refused_for_accounts_that_cannot_join(): void
    {
        [$owner, $org] = $this->orgWithOwner();
        $member = $this->createCustomerUser('member@alpha.local');
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $member->id, 'role' => OrganizationMember::ROLE_STAFF, 'status' => 'active']);
        User::factory()->unverified()->create(['email' => 'unverified@alpha.local']);
        $this->createCustomerUser('locked@alpha.local')->forceFill(['status' => 'locked'])->save();
        $this->invite($owner, $org, $this->createCustomerUser('pending@alpha.local')->email);

        foreach (['nobody@alpha.local', 'owner@alpha.local', 'unverified@alpha.local', 'locked@alpha.local', 'member@alpha.local', 'pending@alpha.local'] as $email) {
            $this->actingAs($owner)->postJson("/api/organizations/{$org->id}/invitations", ['email' => $email, 'role' => OrganizationMember::ROLE_STAFF])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
        }

        $this->assertDatabaseMissing('users', ['email' => 'nobody@alpha.local']);
        $this->assertSame(1, OrganizationInvitation::count());
    }

    /** BR-19: the invitee sees their pending invitations and accepts once; repeats are idempotent. */
    public function test_addressed_user_accepts_invitation_twice_idempotently(): void
    {
        [$owner, $org] = $this->orgWithOwner();
        $venue = Venue::create(['organization_id' => $org->id, 'name' => 'Venue Alpha 1', 'slug' => 'venue-alpha-1', 'is_active' => true]);
        $invitee = $this->createCustomerUser('manager@alpha.local');
        $invitation = $this->invite($owner, $org, $invitee->email, OrganizationMember::ROLE_VENUE_MANAGER, ['venue_id' => $venue->id]);

        $this->actingAs($invitee)->getJson('/api/invitations')
            ->assertOk()
            ->assertJsonCount(1, 'invitations')
            ->assertJsonPath('invitations.0.organization.name', $org->name)
            ->assertJsonPath('invitations.0.inviter.name', 'Owner Alpha');
        $this->assertFalse($invitee->hasRoleInOrganization($org->id, OrganizationMember::ROLE_VENUE_MANAGER));

        $this->actingAs($invitee)->postJson("/api/invitations/{$invitation->id}/accept")
            ->assertOk()
            ->assertJsonPath('invitation.status', 'accepted')
            ->assertJsonPath('member.status', 'active');

        $member = OrganizationMember::where('organization_id', $org->id)->where('user_id', $invitee->id)->firstOrFail();
        $this->assertSame(OrganizationMember::ROLE_VENUE_MANAGER, $member->role);
        $this->assertSame($venue->id, $member->venue_id);
        $this->assertSame('Lời mời được chấp nhận', $owner->notifications()->firstOrFail()->data['title']);
        $this->assertDatabaseHas('elevated_audit_logs', ['user_id' => $invitee->id, 'action' => 'STAFF_INVITATION_ACCEPTED']);

        $this->actingAs($invitee)->postJson("/api/invitations/{$invitation->id}/accept")
            ->assertOk()
            ->assertJsonPath('member.status', 'active');
        $this->assertSame(1, OrganizationMember::where('organization_id', $org->id)->where('user_id', $invitee->id)->count());
        $this->actingAs($invitee)->getJson('/api/invitations')->assertJsonCount(0, 'invitations');
    }

    /** BR-19: only the invitee answers, and only with a verified email. */
    public function test_only_the_verified_invitee_can_accept(): void
    {
        [$owner, $org] = $this->orgWithOwner();
        $invitee = $this->createCustomerUser('victim@alpha.local');
        $invitation = $this->invite($owner, $org, $invitee->email);

        $this->actingAs($this->createCustomerUser('stranger@other.local'))
            ->postJson("/api/invitations/{$invitation->id}/accept")->assertStatus(403);
        $this->actingAs($this->createCustomerUser('stranger2@other.local'))
            ->postJson("/api/invitations/{$invitation->id}/decline")->assertStatus(403);

        $invitee->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($invitee->fresh())->postJson("/api/invitations/{$invitation->id}/accept")->assertStatus(422);

        $this->assertSame('pending', $invitation->fresh()->status);
    }

    public function test_the_invitee_declines_and_the_inviter_is_told(): void
    {
        [$owner, $org] = $this->orgWithOwner();
        $invitee = $this->createCustomerUser('declines@alpha.local');
        $invitation = $this->invite($owner, $org, $invitee->email);

        $this->actingAs($invitee)->postJson("/api/invitations/{$invitation->id}/decline")
            ->assertOk()
            ->assertJsonPath('invitation.status', 'declined');

        $this->assertNotNull($invitation->fresh()->declined_at);
        $this->assertSame('Lời mời bị từ chối', $owner->notifications()->firstOrFail()->data['title']);
        $this->actingAs($invitee)->postJson("/api/invitations/{$invitation->id}/accept")->assertStatus(422);
        $this->assertDatabaseMissing('organization_members', ['organization_id' => $org->id, 'user_id' => $invitee->id]);

        // A declined invitation does not block a fresh one.
        $this->invite($owner, $org, $invitee->email);
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        [$owner, $org] = $this->orgWithOwner();
        $invitee = $this->createCustomerUser('expired_user@alpha.local');
        $invitation = $this->invite($owner, $org, $invitee->email);

        $this->travel(7)->days();
        $this->travel(1)->minutes();

        $this->actingAs($invitee)->postJson("/api/invitations/{$invitation->id}/accept")->assertStatus(422);
        $this->assertSame('expired', $invitation->fresh()->status);
    }

    /** BR-18: the owner revokes a pending invitation; an answered one stays as it is. */
    public function test_owner_can_revoke_a_pending_invitation(): void
    {
        [$owner, $org] = $this->orgWithOwner();
        $invitee = $this->createCustomerUser('revoked_user@alpha.local');
        $invitation = $this->invite($owner, $org, $invitee->email);

        $this->actingAs($owner)->getJson("/api/organizations/{$org->id}/invitations")
            ->assertOk()
            ->assertJsonPath('invitations.0.status', 'pending');

        $this->actingAs($owner)->postJson("/api/organizations/{$org->id}/invitations/{$invitation->id}/revoke")->assertOk();
        $this->assertSame('revoked', $invitation->fresh()->status);

        $this->actingAs($invitee)->postJson("/api/invitations/{$invitation->id}/accept")->assertStatus(422);
        $this->actingAs($owner)->postJson("/api/organizations/{$org->id}/invitations/{$invitation->id}/revoke")->assertStatus(422);
        $this->assertDatabaseMissing('organization_members', ['organization_id' => $org->id, 'user_id' => $invitee->id]);
    }

    /** An organization role never grants platform administration. */
    public function test_inviting_owner_does_not_grant_global_admin(): void
    {
        [$owner, $org] = $this->orgWithOwner();
        $newAccount = $this->createCustomerUser('new_owner_invite@org.local');
        $invitation = $this->invite($owner, $org, $newAccount->email, OrganizationMember::ROLE_ORGANIZATION_OWNER);

        $this->actingAs($newAccount)->postJson("/api/invitations/{$invitation->id}/accept")->assertOk();

        $newAccount->refresh();
        $this->assertEquals('customer', $newAccount->role);
        $this->assertFalse($newAccount->isAdmin());
        $this->assertFalse($newAccount->isPlatformAdmin());
        $this->assertTrue($newAccount->hasRoleInOrganization($org->id, OrganizationMember::ROLE_ORGANIZATION_OWNER));
    }

    /**
     * Scenario: Last owner and quota
     * When a mutation would suspend or remove the final active owner, it SHALL fail atomically.
     */
    public function test_cannot_demote_suspend_or_remove_the_last_active_owner(): void
    {
        $owner = $this->createCustomerUser('sole_owner@org.local');
        $org = $this->createOrg('Sole Owner Org', 'sole-owner-org');
        $member = OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        // 1. Demoting last owner fails
        $this->actingAs($owner)->patchJson("/api/organizations/{$org->id}/members/{$member->id}", [
            'role' => OrganizationMember::ROLE_VENUE_MANAGER,
        ])->assertStatus(422);

        // 2. Suspending last owner fails
        $this->actingAs($owner)->patchJson("/api/organizations/{$org->id}/members/{$member->id}", [
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'suspended',
        ])->assertStatus(422);

        // 3. Removing last owner fails
        $this->actingAs($owner)->deleteJson("/api/organizations/{$org->id}/members/{$member->id}")
            ->assertStatus(422);

        // Still active owner
        $this->assertSame('active', $member->fresh()->status);
        $this->assertSame(OrganizationMember::ROLE_ORGANIZATION_OWNER, $member->fresh()->role);
    }

    /**
     * Staff quota enforcement on invitations
     */
    public function test_staff_quota_prevents_invitations_exceeding_plan_limit(): void
    {
        $owner = $this->createCustomerUser('owner@quota.local');
        $org = $this->createOrg('Quota Org', 'quota-org');
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        $plan = PlatformPlan::create([
            'code' => 'BASIC_PLAN',
            'name' => 'Basic Plan',
            'price' => 500000,
            'max_staff' => 1,
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

        // First staff member fills quota
        $staff1 = $this->createCustomerUser('staff1@quota.local');
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $staff1->id,
            'role' => OrganizationMember::ROLE_STAFF,
            'status' => 'active',
        ]);

        // Attempting to invite a second staff member must fail due to quota
        $this->createCustomerUser('staff2@quota.local');
        $res = $this->actingAs($owner)->postJson("/api/organizations/{$org->id}/invitations", [
            'email' => 'staff2@quota.local',
            'role' => OrganizationMember::ROLE_STAFF,
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['quota']);
    }
}
