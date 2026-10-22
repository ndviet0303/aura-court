<?php

namespace Tests\Feature\Module01;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsVenues;
use Tests\TestCase;

/** FR-ACC-07, BR-ACC-05, BR-ACC-12: profile edits, no self-escalation, and locked/suspended/banned accounts. */
class ProfileAndRestrictionTest extends TestCase
{
    use BuildsVenues;
    use RefreshDatabase;

    public function test_fr_acc_07_profile_edits_persist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/profile', [
            'name' => 'Lê Văn C', 'phone' => '+84912345678', 'avatar_url' => 'https://cdn.example.com/a.png',
            'play_level' => 'advanced', 'favorite_sports' => ['badminton', 'pickleball'],
        ])->assertOk()->assertJsonPath('name', 'Lê Văn C');

        $this->getJson('/api/auth/me')->assertOk()
            ->assertJsonPath('phone', '+84912345678')
            ->assertJsonPath('play_level', 'advanced')
            ->assertJsonPath('favorite_sports', ['badminton', 'pickleball']);
    }

    public function test_fr_acc_07_invalid_profile_values_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())->patchJson('/api/profile', ['name' => 'A', 'phone' => '12345', 'play_level' => 'god'])
            ->assertJsonValidationErrors(['name', 'phone', 'play_level']);
    }

    public function test_fr_acc_07_staff_owner_and_admin_edit_their_own_profile(): void
    {
        $venue = $this->openVenue();
        foreach ([$this->staffOf($venue), $this->ownerOf($venue->organization), $this->platformAdmin()] as $user) {
            $this->actingAs($user)->patchJson('/api/profile', ['name' => 'Tên Mới'])->assertOk();
            $this->assertSame('Tên Mới', $user->fresh()->name);
        }
    }

    public function test_br_acc_05_profile_cannot_grant_a_role_status_or_verification(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->patchJson('/api/profile', [
            'name' => 'Lê Văn C', 'role' => 'platform_admin', 'status' => 'active', 'email_verified_at' => now()->toIso8601String(),
            'email' => 'other@example.com',
        ])->assertOk();

        $user->refresh();
        $this->assertSame('customer', $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertNotSame('other@example.com', $user->email);
        $this->actingAs($user)->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_br_acc_05_a_customer_cannot_reach_owner_or_admin_operations(): void
    {
        $organization = Organization::create(['name' => 'CLB', 'slug' => 'clb-x', 'status' => 'active', 'partner_status' => 'approved']);
        $customer = User::factory()->create();

        $this->actingAs($customer)->getJson("/api/partner/organizations/{$organization->id}/venues")->assertForbidden();
        $this->actingAs($customer)->postJson("/api/organizations/{$organization->id}/invitations", ['email' => 'x@example.com', 'venue_ids' => [1]])->assertForbidden();
        $this->actingAs($customer)->getJson('/api/admin/partner/applications')->assertForbidden();
        $this->actingAs($customer)->getJson('/api/admin/kyc')->assertForbidden();
    }

    /** @return list<array{string}> */
    public static function restrictedStatuses(): array
    {
        return [['locked'], ['suspended'], ['banned']];
    }

    #[DataProvider('restrictedStatuses')]
    public function test_br_acc_12_a_restricted_account_cannot_sign_in(string $status): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => 'Secret123', 'status' => $status]);

        $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'Secret123'])
            ->assertForbidden()->assertJsonPath('error', 'account_restricted');
        $this->assertGuest('web');
    }

    #[DataProvider('restrictedStatuses')]
    public function test_br_acc_12_an_existing_session_of_a_restricted_account_is_refused(string $status): void
    {
        $court = $this->bookableCourt();
        $user = User::factory()->create(['status' => $status]);

        $this->actingAs($user)->getJson('/api/auth/me')->assertOk();
        $this->actingAs($user)->patchJson('/api/profile', ['name' => 'Tên Mới'])->assertForbidden();
        $this->actingAs($user)->postJson('/api/reservations/checkout', ['court_id' => $court->id])->assertForbidden()
            ->assertJsonPath('error', 'account_restricted');
        $this->actingAs($user)->postJson('/api/partner/application/start')->assertForbidden();
    }

    public function test_br_acc_12_admin_lock_revokes_access_and_unlock_restores_sign_in(): void
    {
        $admin = $this->platformAdmin();
        $user = User::factory()->create(['email' => 'a@example.com', 'password' => 'Secret123']);
        $token = $user->createToken('app')->plainTextToken;

        $this->actingAs($admin)->postJson("/api/admin/users/{$user->id}/lock", ['reason' => 'Vi phạm điều khoản sử dụng'])->assertOk();

        $this->assertSame('locked', $user->fresh()->status);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('elevated_audit_logs', ['user_id' => $admin->id, 'action' => 'ACCOUNT_LOCKED']);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/notifications')->assertUnauthorized();

        $this->actingAs($admin)->postJson("/api/admin/users/{$user->id}/unlock", ['reason' => 'Đã xác minh lại tài khoản'])->assertOk();
        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_br_acc_12_only_a_platform_admin_locks_accounts(): void
    {
        $target = User::factory()->create();
        $owner = $this->ownerOf($this->openVenue()->organization);

        $this->actingAs($owner)->postJson("/api/admin/users/{$target->id}/lock", ['reason' => 'Vi phạm điều khoản sử dụng'])->assertForbidden();
        $this->assertSame('active', $target->fresh()->status);
    }
}
