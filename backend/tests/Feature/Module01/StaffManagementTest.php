<?php

namespace Tests\Feature\Module01;

use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsVenues;
use Tests\TestCase;

/** FR-ACC-17, FR-ACC-18: owners invite, activate, scope, suspend and end staff; staff reach only assigned venues. */
class StaffManagementTest extends TestCase
{
    use BuildsVenues;
    use RefreshDatabase;

    private Venue $venueA;

    private Venue $venueB;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->venueA = $this->openVenue();
        $this->venueB = $this->openVenue(['organization_id' => $this->venueA->organization_id]);
        $this->owner = $this->ownerOf($this->venueA->organization);
    }

    private function orgId(): int
    {
        return $this->venueA->organization_id;
    }

    /** @param  list<int>  $venueIds */
    private function invite(User $invitee, array $venueIds, ?User $actor = null, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($actor ?? $this->owner)->postJson("/api/organizations/{$this->orgId()}/invitations", $extra + [
            'email' => $invitee->email, 'venue_ids' => $venueIds,
        ]);
    }

    private function venueRead(User $user, Venue $venue): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->getJson("/api/partner/organizations/{$this->orgId()}/venues/{$venue->id}");
    }

    public function test_fr_acc_17_an_accepted_invitation_activates_scoped_staff_once(): void
    {
        $invitee = User::factory()->create();
        $id = $this->invite($invitee, [$this->venueA->id])->assertCreated()->assertJsonPath('invitation.role', 'staff')->json('invitation.id');
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $invitee->id]);

        $this->assertNull($invitee->roleInOrganization($this->orgId()));
        $this->actingAs($invitee)->getJson('/api/invitations')->assertOk()->assertJsonCount(1, 'invitations');
        $this->actingAs($invitee)->postJson("/api/invitations/{$id}/accept")->assertOk();
        $this->actingAs($invitee)->postJson("/api/invitations/{$id}/accept")->assertOk();

        $member = OrganizationMember::where('user_id', $invitee->id)->sole();
        $this->assertSame(['staff', 'active', [$this->venueA->id]], [$member->role, $member->status, $member->venue_ids]);
        $this->assertSame('customer', $invitee->fresh()->role);
    }

    public function test_fr_acc_17_an_invitation_never_grants_ownership_or_platform_roles(): void
    {
        $invitee = User::factory()->create();
        $id = $this->invite($invitee, [$this->venueA->id], extra: ['role' => 'organization_owner'])->assertCreated()->json('invitation.id');
        $this->actingAs($invitee)->postJson("/api/invitations/{$id}/accept")->assertOk();

        $this->assertSame('staff', $invitee->roleInOrganization($this->orgId()));
        $this->assertFalse($invitee->fresh()->isPlatformAdmin());
    }

    public function test_fr_acc_17_only_the_owner_invites_and_only_to_own_venues(): void
    {
        $staff = $this->staffOf($this->venueA);
        $invitee = User::factory()->create();
        $foreignVenue = $this->openVenue();

        $this->invite($invitee, [$this->venueA->id], $staff)->assertForbidden();
        $this->invite($invitee, [$foreignVenue->id])->assertJsonValidationErrors('venue_ids.0');
        $this->invite($invitee, [])->assertJsonValidationErrors('venue_ids');
        $this->invite(User::factory()->unverified()->create(), [$this->venueA->id])->assertJsonValidationErrors('email');
        $this->assertSame(0, OrganizationInvitation::count());
    }

    public function test_fr_acc_17_revoked_declined_and_expired_invitations_never_activate(): void
    {
        [$a, $b, $c] = User::factory()->count(3)->create()->all();
        $revoked = $this->invite($a, [$this->venueA->id])->json('invitation.id');
        $declined = $this->invite($b, [$this->venueA->id])->json('invitation.id');
        $expired = $this->invite($c, [$this->venueA->id])->json('invitation.id');

        $this->actingAs($this->owner)->postJson("/api/organizations/{$this->orgId()}/invitations/{$revoked}/revoke")->assertOk();
        $this->actingAs($b)->postJson("/api/invitations/{$declined}/decline")->assertOk();
        $this->travel(8)->days();

        $this->actingAs($a)->postJson("/api/invitations/{$revoked}/accept")->assertStatus(422);
        $this->actingAs($b)->postJson("/api/invitations/{$declined}/accept")->assertStatus(422);
        $this->actingAs($c)->postJson("/api/invitations/{$expired}/accept")->assertStatus(422);
        $this->assertSame(0, OrganizationMember::where('role', 'staff')->count());
    }

    public function test_fr_acc_17_only_the_addressee_answers_an_invitation(): void
    {
        $id = $this->invite(User::factory()->create(), [$this->venueA->id])->json('invitation.id');

        $this->actingAs(User::factory()->create())->postJson("/api/invitations/{$id}/accept")->assertForbidden();
    }

    public function test_fr_acc_18_staff_reach_only_assigned_venues(): void
    {
        $staff = $this->staffOf($this->venueA);

        $this->venueRead($staff, $this->venueA)->assertOk();
        $this->venueRead($staff, $this->venueB)->assertForbidden();
        $this->actingAs($staff)->getJson("/api/partner/organizations/{$this->orgId()}/venues")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->venueA->id);
        $this->actingAs($staff)->getJson("/api/organizations/{$this->orgId()}")->assertOk()
            ->assertJsonCount(1, 'organization.venues');
        $this->venueRead($this->owner, $this->venueB)->assertOk();
    }

    public function test_fr_acc_18_staff_cannot_do_owner_administration(): void
    {
        $staff = $this->staffOf($this->venueA);
        $other = $this->staffOf($this->venueA);
        $member = OrganizationMember::where('user_id', $other->id)->sole();

        $this->actingAs($staff)->getJson("/api/organizations/{$this->orgId()}/members")->assertForbidden();
        $this->actingAs($staff)->patchJson("/api/organizations/{$this->orgId()}/members/{$member->id}", ['venue_ids' => [$this->venueB->id]])->assertForbidden();
        $this->actingAs($staff)->patchJson("/api/organizations/{$this->orgId()}", ['name' => 'Đổi tên'])->assertForbidden();
    }

    public function test_fr_acc_18_owner_grants_and_revokes_venue_scope(): void
    {
        $staff = $this->staffOf($this->venueA);
        $member = OrganizationMember::where('user_id', $staff->id)->sole();
        $url = "/api/organizations/{$this->orgId()}/members/{$member->id}";

        $this->actingAs($this->owner)->patchJson($url, ['venue_ids' => [$this->venueB->id]])->assertOk();
        $this->venueRead($staff, $this->venueA)->assertForbidden();
        $this->venueRead($staff, $this->venueB)->assertOk();

        $this->actingAs($this->owner)->patchJson($url, ['venue_ids' => []])->assertOk();
        $this->venueRead($staff, $this->venueB)->assertForbidden();
        $this->assertDatabaseHas('elevated_audit_logs', ['user_id' => $this->owner->id, 'action' => 'STAFF_ROLE_UPDATED']);
    }

    public function test_fr_acc_18_a_foreign_venue_cannot_be_assigned(): void
    {
        $staff = $this->staffOf($this->venueA);
        $member = OrganizationMember::where('user_id', $staff->id)->sole();

        $this->actingAs($this->owner)->patchJson("/api/organizations/{$this->orgId()}/members/{$member->id}", ['venue_ids' => [$this->openVenue()->id]])
            ->assertJsonValidationErrors('venue_ids.0');
    }

    public function test_fr_acc_17_suspending_and_ending_staff_removes_access_but_keeps_the_customer(): void
    {
        $staff = $this->staffOf($this->venueA);
        $member = OrganizationMember::where('user_id', $staff->id)->sole();
        $url = "/api/organizations/{$this->orgId()}/members/{$member->id}";

        $this->actingAs($this->owner)->patchJson($url, ['status' => 'suspended'])->assertOk();
        $this->venueRead($staff, $this->venueA)->assertForbidden();

        $this->actingAs($this->owner)->patchJson($url, ['status' => 'active'])->assertOk();
        $this->venueRead($staff, $this->venueA)->assertOk();

        $this->actingAs($this->owner)->deleteJson($url)->assertOk();
        $this->venueRead($staff, $this->venueA)->assertForbidden();
        $this->assertSame('customer', $member->fresh()->role);
        $this->assertNotNull($staff->fresh());
    }

    public function test_fr_acc_17_owner_rows_are_not_edited_through_staff_management(): void
    {
        $ownerRow = OrganizationMember::where('user_id', $this->owner->id)->sole();
        $url = "/api/organizations/{$this->orgId()}/members/{$ownerRow->id}";

        $this->actingAs($this->owner)->patchJson($url, ['status' => 'suspended'])->assertStatus(422);
        $this->actingAs($this->owner)->deleteJson($url)->assertStatus(422);
        $this->assertSame(['organization_owner', 'active'], [$ownerRow->fresh()->role, $ownerRow->fresh()->status]);
    }

    public function test_br_par_12_venue_details_and_receiving_account_stay_with_the_owner(): void
    {
        $staff = $this->staffOf($this->venueA);
        $base = "/api/partner/organizations/{$this->orgId()}/venues/{$this->venueA->id}";

        $this->actingAs($staff)->putJson($base, ['name' => 'Tên mới'])->assertForbidden();
        $this->actingAs($staff)->getJson("{$base}/receiving-connection")->assertForbidden();
        $this->actingAs($staff)->postJson("{$base}/receiving-connection", ['bank_code' => 'VCB'])->assertForbidden();
        $this->assertNotSame('Tên mới', $this->venueA->fresh()->name);
    }
}
