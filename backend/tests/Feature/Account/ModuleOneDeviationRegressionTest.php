<?php

namespace Tests\Feature\Account;

use App\Models\ElevatedAuditLog;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Regression cover for the module 1 deviations fixed after the BR-01..24 re-audit. */
class ModuleOneDeviationRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function staffOf(Organization $organization, array $venueIds): OrganizationMember
    {
        return OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create()->id,
            'role' => OrganizationMember::ROLE_STAFF,
            'status' => 'active',
            'permissions' => ['venue_ids' => $venueIds, 'venue_id' => $venueIds[0] ?? null],
        ]);
    }

    /** Group 1: a court of another organization cannot move a request the role middleware already authorised. */
    public function test_court_of_another_organization_is_refused_under_the_verified_organization(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();

        $this->actingAs($fixture['org1']['owner'])
            ->withHeader('X-Organization-ID', (string) $fixture['org1']['org']->id)
            ->postJson('/api/attendance/check-in', ['court_id' => $fixture['org2']['court']->id])
            ->assertForbidden()
            ->assertJsonPath('code', 'ORGANIZATION_MISMATCH');
    }

    /** BR-23: platform admin rights do not stand in for organization operations; dispute arbitration stays a platform function. */
    public function test_platform_admin_is_refused_organization_operations_but_keeps_dispute_arbitration(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $admin = User::factory()->create(['role' => 'platform_admin']);

        $this->actingAs($admin)->getJson('/api/admin/venues')->assertForbidden();
        $this->actingAs($admin)->patchJson("/api/admin/venues/{$fixture['org1']['venue1']->id}", ['address' => 'Hijacked'])->assertForbidden();
        $this->actingAs($admin)->getJson('/api/admin/disputes')->assertOk();
    }

    /** BR-21/BR-22: the owner edits and ends staff memberships only; ending one keeps the person as a customer, audited. */
    public function test_owner_adjusts_and_ends_staff_membership_only(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $org = $fixture['org1']['org'];
        $owner = $fixture['org1']['owner'];
        $ownerRow = OrganizationMember::where('organization_id', $org->id)->where('user_id', $owner->id)->firstOrFail();
        $customerRow = OrganizationMember::create(['organization_id' => $org->id, 'user_id' => User::factory()->create()->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);
        $staff = $this->staffOf($org, [$fixture['org1']['venue1']->id]);

        foreach ([$ownerRow, $customerRow] as $row) {
            $this->actingAs($owner)->patchJson("/api/organizations/{$org->id}/members/{$row->id}", ['status' => 'suspended'])->assertStatus(422);
            $this->actingAs($owner)->deleteJson("/api/organizations/{$org->id}/members/{$row->id}")->assertStatus(422);
        }
        $this->actingAs($owner)->patchJson("/api/organizations/{$org->id}/members/{$staff->id}", ['venue_ids' => [$fixture['org2']['venue']->id]])
            ->assertStatus(422)->assertJsonValidationErrors(['venue_ids.0']);

        $this->actingAs($owner)->deleteJson("/api/organizations/{$org->id}/members/{$staff->id}")->assertOk();

        $staff->refresh();
        $this->assertSame(OrganizationMember::ROLE_CUSTOMER, $staff->role);
        $this->assertSame('active', $staff->status);
        $this->assertNull($staff->permissions);
        $audit = ElevatedAuditLog::where('action', 'STAFF_MEMBER_REMOVED')->firstOrFail();
        $this->assertSame($owner->id, $audit->user_id);
        $this->assertSame($staff->user_id, $audit->metadata['target_user_id']);
        $this->assertSame([$fixture['org1']['venue1']->id], $audit->metadata['before']['venue_ids']);
    }

    /** BR-20: a shift opens only at a venue the staff member is assigned to; several venues need an explicit choice. */
    public function test_staff_open_shifts_only_at_assigned_venues(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $org = $fixture['org1']['org'];
        config(['features.pilot_organizations' => [(string) $org->id], 'features.flags.tenant_pos_and_wallet' => true]);
        $venue1 = $fixture['org1']['venue1'];
        $venue2 = $fixture['org1']['venue2'];
        $open = fn (OrganizationMember $member, array $payload, string $key) => $this->actingAs($member->user)
            ->withHeaders(['X-Organization-ID' => (string) $org->id, 'Idempotency-Key' => $key])
            ->postJson('/api/pos/shifts', ['opening_cash' => 100000] + $payload);

        $single = $this->staffOf($org, [$venue1->id]);
        $open($single, ['venue_id' => $venue2->id], 'shift-foreign')->assertForbidden();
        $open($single, [], 'shift-single')->assertCreated()->assertJsonPath('shift.venue_id', $venue1->id);

        $multi = $this->staffOf($org, [$venue1->id, $venue2->id]);
        $open($multi, [], 'shift-multi')->assertStatus(422)->assertJsonPath('message', 'Vui lòng chọn cơ sở bạn được giao.');
        $open($multi, ['venue_id' => $venue2->id], 'shift-multi-chosen')->assertCreated();
    }

    /** BR-20: a service tab belongs to the venue of its reservation; staff of another venue cannot open it. */
    public function test_staff_cannot_open_a_service_tab_at_another_venue(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $org = $fixture['org1']['org'];
        config(['features.pilot_organizations' => [(string) $org->id], 'features.flags.tenant_pos_and_wallet' => true]);
        $reservation = Reservation::create(['organization_id' => $org->id, 'user_id' => User::factory()->create()->id, 'court_id' => $fixture['org1']['court1']->id, 'code' => 'RSV-TAB-SCOPE', 'status' => 'confirmed', 'currency' => 'VND', 'total_amount' => 100000, 'payment_model' => 'legacy_wallet_v1']);
        $tab = fn (OrganizationMember $member) => $this->actingAs($member->user)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->getJson("/api/service-tabs/{$reservation->code}");

        $tab($this->staffOf($org, [$fixture['org1']['venue2']->id]))->assertForbidden()->assertJsonPath('message', 'Bạn không được giao cơ sở này.');
        $tab($this->staffOf($org, [$fixture['org1']['venue1']->id]))->assertSuccessful();
    }

    /** BR-15: a tax code already held by another organization is refused, whatever its case. */
    public function test_organization_tax_code_stays_unique(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $fixture['org2']['org']->update(['tax_code' => '0312345678-AB']);

        $this->actingAs($fixture['org1']['owner'])
            ->patchJson("/api/organizations/{$fixture['org1']['org']->id}", ['tax_code' => '0312345678-ab'])
            ->assertStatus(422)->assertJsonValidationErrors(['tax_code']);
    }

    /** BR-04: money, waitlist and dispute actions need a verified email. */
    public function test_unverified_account_cannot_join_the_waitlist(): void
    {
        $this->actingAs(User::factory()->unverified()->create())->postJson('/api/waitlist', [])->assertForbidden();
    }

    /** BR-05: the email sent at registration counts toward the resend throttle. */
    public function test_resend_right_after_registration_is_throttled(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Fresh Player', 'email' => 'fresh@aura.test', 'password' => 'FreshPassword123!', 'password_confirmation' => 'FreshPassword123!',
        ])->assertCreated();

        $this->postJson('/api/auth/email/verification-notification')->assertStatus(429);
    }

    /** BR-06: a Facebook account without an email adds one before any verification mail goes out. */
    public function test_facebook_account_without_email_adds_one_before_verifying(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'fb-123'.User::SOCIAL_PLACEHOLDER_EMAIL_DOMAIN, 'social_provider' => 'facebook']);

        $this->actingAs($user)->getJson('/api/auth/me')->assertOk()->assertJsonPath('capabilities.needs_email', true);
        $this->actingAs($user)->postJson('/api/auth/email/verification-notification')->assertStatus(422);
        $this->actingAs($user)->postJson('/api/auth/email', ['email' => 'other'.User::SOCIAL_PLACEHOLDER_EMAIL_DOMAIN])
            ->assertStatus(422)->assertJsonValidationErrors(['email']);
        Notification::assertNothingSent();

        $this->actingAs($user)->postJson('/api/auth/email', ['email' => ' Real.Player@Example.com '])->assertOk();

        $user->refresh();
        $this->assertSame('real.player@example.com', $user->email);
        $this->assertFalse($user->needsEmail());
        Notification::assertSentTo($user, VerifyEmailNotification::class);
        $this->actingAs($user)->postJson('/api/auth/email/verification-notification')->assertStatus(429);
        $this->actingAs($user)->postJson('/api/auth/email', ['email' => 'again@example.com'])->assertForbidden();
    }

    /** BR-10: changing the password ends the current session as well. */
    public function test_password_change_signs_the_user_out(): void
    {
        $user = User::factory()->create(['password' => 'CurrentPassword123!', 'password_set' => true]);

        $this->actingAs($user)->patchJson('/api/profile/password', [
            'current_password' => 'CurrentPassword123!', 'password' => 'NewPassword999!', 'password_confirmation' => 'NewPassword999!',
        ])->assertOk()->assertJsonPath('logged_out', true);

        $this->assertGuest('web');
        $this->app['auth']->forgetGuards(); // the next request starts with fresh guards, as in production
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }
}
