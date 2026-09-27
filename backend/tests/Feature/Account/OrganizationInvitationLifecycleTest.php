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

    /**
     * Requirement: Real staff invitation and permission lifecycle
     * Invitations create pending invitation, send persisted notification, and audit actor.
     */
    public function test_owner_can_create_pending_invitation_with_expiry_token_and_notification(): void
    {
        $owner = $this->createCustomerUser('owner@alpha.local', 'Owner Alpha');
        $org = $this->createOrg();
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Venue Alpha 1',
            'slug' => 'venue-alpha-1',
            'is_active' => true,
        ]);

        $inviteRes = $this->actingAs($owner)->postJson("/api/v1/organizations/{$org->id}/invitations", [
            'email' => 'newstaff@alpha.local',
            'role' => OrganizationMember::ROLE_VENUE_MANAGER,
            'venue_id' => $venue->id,
        ]);

        $inviteRes->assertStatus(201);
        $inviteRes->assertJsonPath('invitation.email', 'newstaff@alpha.local');
        $inviteRes->assertJsonPath('invitation.role', OrganizationMember::ROLE_VENUE_MANAGER);
        $inviteRes->assertJsonPath('invitation.status', 'pending');
        $inviteRes->assertJsonPath('member.status', 'invited');

        $invitation = OrganizationInvitation::where('email', 'newstaff@alpha.local')->firstOrFail();
        $this->assertSame('pending', $invitation->status);
        $this->assertNotNull($invitation->token);
        $this->assertTrue($invitation->expires_at->isFuture());

        // Target user was created as customer and not global admin
        $targetUser = User::where('email', 'newstaff@alpha.local')->firstOrFail();
        $this->assertSame('customer', $targetUser->role);
        $this->assertFalse($targetUser->isPlatformAdmin());

        // Target user received persisted notification
        $this->assertGreaterThanOrEqual(1, $targetUser->notifications()->count());
        $notif = $targetUser->notifications()->first();
        $this->assertSame('Lời mời tham gia quản trị', $notif->data['title']);

        // Elevated audit log was written
        $this->assertDatabaseHas('elevated_audit_logs', [
            'user_id' => $owner->id,
            'organization_id' => $org->id,
            'action' => 'STAFF_INVITATION_CREATED',
            'outcome' => 'success',
        ]);
    }

    /**
     * Scenario: Invite accepted
     * When the addressed user accepts a valid invitation twice, exactly one scoped membership
     * becomes active, delivery and acceptance notifications reflect actual state, and call is idempotent.
     */
    public function test_addressed_user_accepts_invitation_twice_idempotently(): void
    {
        $owner = $this->createCustomerUser('owner@alpha.local', 'Owner Alpha');
        $org = $this->createOrg();
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Venue Alpha 1',
            'slug' => 'venue-alpha-1',
            'is_active' => true,
        ]);

        // Send invite
        $this->actingAs($owner)->postJson("/api/v1/organizations/{$org->id}/invitations", [
            'email' => 'manager@alpha.local',
            'role' => OrganizationMember::ROLE_VENUE_MANAGER,
            'venue_id' => $venue->id,
        ])->assertStatus(201);

        $invitation = OrganizationInvitation::where('email', 'manager@alpha.local')->firstOrFail();
        $invitee = User::where('email', 'manager@alpha.local')->firstOrFail();

        // 1. Before accept: member status is 'invited' and hasRoleInOrganization is false (requires active)
        $this->assertFalse($invitee->hasRoleInOrganization($org->id, OrganizationMember::ROLE_VENUE_MANAGER));

        // 2. First accept by token
        $acceptRes1 = $this->actingAs($invitee)->postJson("/api/v1/invitations/{$invitation->token}/accept");
        $acceptRes1->assertOk();
        $acceptRes1->assertJsonPath('invitation.status', 'accepted');
        $acceptRes1->assertJsonPath('member.status', 'active');

        // Check active membership in DB
        $member = OrganizationMember::where('organization_id', $org->id)->where('user_id', $invitee->id)->firstOrFail();
        $this->assertSame('active', $member->status);
        $this->assertSame(OrganizationMember::ROLE_VENUE_MANAGER, $member->role);
        $this->assertSame($venue->id, $member->venue_id);
        $this->assertTrue($invitee->hasRoleInOrganization($org->id, OrganizationMember::ROLE_VENUE_MANAGER));

        // Inviter received notification
        $ownerNotif = $owner->notifications()->first();
        $this->assertNotNull($ownerNotif);
        $this->assertSame('Lời mời được chấp nhận', $ownerNotif->data['title']);

        // Audit log written
        $this->assertDatabaseHas('elevated_audit_logs', [
            'user_id' => $invitee->id,
            'organization_id' => $org->id,
            'action' => 'STAFF_INVITATION_ACCEPTED',
            'outcome' => 'success',
        ]);

        // 3. Second accept (duplicate call): MUST be idempotent!
        $acceptRes2 = $this->actingAs($invitee)->postJson("/api/v1/invitations/{$invitation->token}/accept");
        $acceptRes2->assertOk();
        $acceptRes2->assertJsonPath('invitation.status', 'accepted');
        $acceptRes2->assertJsonPath('member.status', 'active');

        // Verify still exactly one membership record exists for this user in the org
        $this->assertSame(1, OrganizationMember::where('organization_id', $org->id)->where('user_id', $invitee->id)->count());
    }

    /**
     * Acceptance verification: Non-addressed user cannot accept someone else's invitation
     */
    public function test_stranger_cannot_accept_another_users_invitation(): void
    {
        $owner = $this->createCustomerUser('owner@alpha.local', 'Owner Alpha');
        $org = $this->createOrg();
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        $this->actingAs($owner)->postJson("/api/v1/organizations/{$org->id}/invitations", [
            'email' => 'victim@alpha.local',
            'role' => OrganizationMember::ROLE_STAFF,
        ])->assertStatus(201);

        $invitation = OrganizationInvitation::where('email', 'victim@alpha.local')->firstOrFail();
        $stranger = $this->createCustomerUser('stranger@other.local', 'Stranger');

        $this->actingAs($stranger)
            ->postJson("/api/v1/invitations/{$invitation->token}/accept")
            ->assertStatus(403);

        $this->assertSame('pending', $invitation->fresh()->status);
    }

    /**
     * Expired invitation cannot be accepted
     */
    public function test_expired_invitation_cannot_be_accepted(): void
    {
        $owner = $this->createCustomerUser('owner@alpha.local', 'Owner Alpha');
        $org = $this->createOrg();
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        $this->actingAs($owner)->postJson("/api/v1/organizations/{$org->id}/invitations", [
            'email' => 'expired_user@alpha.local',
            'role' => OrganizationMember::ROLE_STAFF,
        ])->assertStatus(201);

        $invitation = OrganizationInvitation::where('email', 'expired_user@alpha.local')->firstOrFail();
        $invitation->update(['expires_at' => now()->subDay()]);

        $invitee = User::where('email', 'expired_user@alpha.local')->firstOrFail();

        $this->actingAs($invitee)
            ->postJson("/api/v1/invitations/{$invitation->token}/accept")
            ->assertStatus(422);

        $this->assertSame('expired', $invitation->fresh()->status);
    }

    /**
     * Owner can revoke pending invitation, preventing activation
     */
    public function test_owner_can_revoke_invitation(): void
    {
        $owner = $this->createCustomerUser('owner@alpha.local', 'Owner Alpha');
        $org = $this->createOrg();
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        $this->actingAs($owner)->postJson("/api/v1/organizations/{$org->id}/invitations", [
            'email' => 'revoked_user@alpha.local',
            'role' => OrganizationMember::ROLE_STAFF,
        ])->assertStatus(201);

        $invitation = OrganizationInvitation::where('email', 'revoked_user@alpha.local')->firstOrFail();
        $invitee = User::where('email', 'revoked_user@alpha.local')->firstOrFail();

        // Revoke
        $revokeRes = $this->actingAs($owner)->postJson("/api/v1/organizations/{$org->id}/invitations/{$invitation->id}/revoke");
        $revokeRes->assertOk();
        $this->assertSame('revoked', $invitation->fresh()->status);

        // Attempting to accept revoked invitation fails
        $this->actingAs($invitee)
            ->postJson("/api/v1/invitations/{$invitation->token}/accept")
            ->assertStatus(422);

        // Member was cleaned up
        $this->assertDatabaseMissing('organization_members', [
            'organization_id' => $org->id,
            'user_id' => $invitee->id,
        ]);
    }

    /**
     * Scenario: Scope escalation
     * When an owner invites an unknown email as an organization owner, the personal account MUST NOT acquire global admin rights.
     */
    public function test_inviting_owner_does_not_grant_global_admin(): void
    {
        $owner = $this->createCustomerUser('primary_owner@org.local');
        $org = $this->createOrg('Escalation Org', 'escalation-org');
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        $inviteRes = $this->actingAs($owner)->postJson("/api/v1/organizations/{$org->id}/invitations", [
            'email' => 'new_owner_invite@org.local',
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
        ]);
        $inviteRes->assertStatus(201);

        $newAccount = User::where('email', 'new_owner_invite@org.local')->firstOrFail();
        $this->assertEquals('customer', $newAccount->role);
        $this->assertFalse($newAccount->isAdmin());
        $this->assertFalse($newAccount->isPlatformAdmin());

        // Accept invitation
        $invitation = OrganizationInvitation::where('email', 'new_owner_invite@org.local')->firstOrFail();
        $this->actingAs($newAccount)->postJson("/api/v1/invitations/{$invitation->token}/accept")->assertOk();

        // Still customer globally, but organization_owner in org
        $this->assertEquals('customer', $newAccount->fresh()->role);
        $this->assertFalse($newAccount->fresh()->isAdmin());
        $this->assertFalse($newAccount->fresh()->isPlatformAdmin());
        $this->assertTrue($newAccount->fresh()->hasRoleInOrganization($org->id, OrganizationMember::ROLE_ORGANIZATION_OWNER));
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
        $this->actingAs($owner)->patchJson("/api/v1/organizations/{$org->id}/members/{$member->id}", [
            'role' => OrganizationMember::ROLE_VENUE_MANAGER,
        ])->assertStatus(422);

        // 2. Suspending last owner fails
        $this->actingAs($owner)->patchJson("/api/v1/organizations/{$org->id}/members/{$member->id}", [
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'suspended',
        ])->assertStatus(422);

        // 3. Removing last owner fails
        $this->actingAs($owner)->deleteJson("/api/v1/organizations/{$org->id}/members/{$member->id}")
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
        $res = $this->actingAs($owner)->postJson("/api/v1/organizations/{$org->id}/invitations", [
            'email' => 'staff2@quota.local',
            'role' => OrganizationMember::ROLE_STAFF,
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['quota']);
    }
}
