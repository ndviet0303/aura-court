<?php

namespace Tests\Feature\Account;

use App\Models\Court;
use App\Models\ElevatedAuditLog;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** BR-21 (staff keep a fixed operational permission set) and BR-23 (admin account lock with reason and audit). */
class StaffPermissionsAndAccountLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_staff_operate_their_venue_but_cannot_manage_it(): void
    {
        $org = Organization::create(['name' => 'Perm Org', 'slug' => 'perm-org', 'status' => 'active', 'partner_status' => 'approved']);
        $assigned = $this->venue($org, 'assigned-venue');
        $other = $this->venue($org, 'other-venue');
        $staff = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $org->id, 'user_id' => $staff->id, 'role' => OrganizationMember::ROLE_STAFF,
            'status' => 'active', 'permissions' => ['venue_ids' => [$assigned->id]],
        ]);

        $this->assertTrue($staff->canOperateVenue($assigned));
        $this->assertFalse($staff->canOperateVenue($other));
        $this->assertFalse($staff->canManageVenue($assigned));

        $this->actingAs($staff)->patchJson("/api/admin/venues/{$assigned->id}", ['address' => '2 Changed Street'])->assertForbidden();

        $court = Court::create(['venue_id' => $assigned->id, 'name' => 'Court A', 'is_active' => true]);
        $otherCourt = Court::create(['venue_id' => $other->id, 'name' => 'Court B', 'is_active' => true]);
        $headers = fn () => ['X-Organization-ID' => (string) $org->id, 'Idempotency-Key' => uniqid('ready-')];

        $this->actingAs($staff)->withHeaders($headers())->postJson("/api/attendance/courts/{$court->id}/confirm-ready")->assertOk();
        $this->actingAs($staff)->withHeaders($headers())->postJson("/api/attendance/courts/{$otherCourt->id}/confirm-ready")->assertForbidden();
        $this->actingAs($staff)->getJson("/api/admin/courts/{$court->id}/blocks")->assertOk();
        $this->actingAs($staff)->getJson("/api/admin/courts/{$court->id}/prices")->assertForbidden();
    }

    public function test_an_admin_locks_an_account_with_a_reason_and_the_change_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();
        $target->createToken('mobile');

        $this->actingAs($admin)->postJson("/api/admin/users/{$target->id}/lock", ['reason' => 'ngắn'])
            ->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->actingAs($target)->postJson("/api/admin/users/{$admin->id}/lock", ['reason' => 'Không phải quản trị viên.'])
            ->assertForbidden();
        $this->actingAs($admin)->postJson("/api/admin/users/{$admin->id}/lock", ['reason' => 'Tự khóa chính mình để thử.'])
            ->assertStatus(422);

        $this->actingAs($admin)->postJson("/api/admin/users/{$target->id}/lock", ['reason' => 'Vi phạm điều khoản sử dụng.'])
            ->assertOk()->assertJsonPath('user.status', 'locked');

        $target->refresh();
        $this->assertSame('locked', $target->status);
        $this->assertNotNull($target->sessions_revoked_at);
        $this->assertSame(0, $target->tokens()->count());

        $log = ElevatedAuditLog::where('action', 'ACCOUNT_LOCKED')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Vi phạm điều khoản sử dụng.', $log->purpose);
        $this->assertSame(['status' => 'active'], $log->metadata['before']);
        $this->assertSame(['status' => 'locked'], $log->metadata['after']);

        $this->actingAs($admin)->postJson("/api/admin/users/{$target->id}/unlock", ['reason' => 'Đã xác minh lại danh tính.'])
            ->assertOk()->assertJsonPath('user.status', 'active');
        $this->assertTrue(ElevatedAuditLog::where('action', 'ACCOUNT_UNLOCKED')->exists());
    }

    public function test_only_an_admin_lists_accounts_filtered_by_search_and_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'root@example.com']);
        User::factory()->create(['name' => 'Nguyen Lan', 'email' => 'lan@example.com']);
        User::factory()->create(['name' => 'Tran Locked', 'email' => 'locked@example.com', 'status' => 'locked']);

        $this->actingAs($admin)->getJson('/api/admin/users?search=LAN')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'lan@example.com')
            ->assertJsonMissingPath('data.0.password');
        $this->actingAs($admin)->getJson('/api/admin/users?status=locked')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'locked');
        $this->actingAs($admin)->getJson('/api/admin/users?search=50%25')->assertOk()->assertJsonCount(0, 'data');

        $customer = User::factory()->create();
        $this->actingAs($customer)->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_a_session_signed_in_before_a_lock_stays_ended_after_the_unlock(): void
    {
        $target = User::factory()->create(['email' => 'locked@example.com', 'password' => Hash::make('Password123')]);

        $this->withHeader('Referer', 'http://localhost')
            ->postJson('/api/auth/login', ['email' => 'locked@example.com', 'password' => 'Password123'])->assertOk();
        $this->getJson('/api/auth/me')->assertOk();

        $this->travel(1)->seconds();
        $target->forceFill(['status' => 'locked', 'sessions_revoked_at' => now()])->save();
        $target->forceFill(['status' => 'active'])->save();
        // Each real request builds its guard afresh; the test app would otherwise keep the user cached from the login.
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/auth/me')->assertUnauthorized();

        $this->travel(1)->seconds();
        $this->app['auth']->shouldUse('web');
        $this->postJson('/api/auth/login', ['email' => 'locked@example.com', 'password' => 'Password123'])->assertOk();
        $this->getJson('/api/auth/me')->assertOk();
    }

    private function venue(Organization $org, string $slug): Venue
    {
        return Venue::create([
            'organization_id' => $org->id, 'name' => ['vi' => $slug, 'en' => $slug], 'slug' => $slug,
            'address' => '1 Test Street', 'status' => 'published', 'is_active' => true,
        ]);
    }
}
