<?php

namespace Tests\Feature\Module01;

use App\Models\OrganizationMember;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** FR-ACC-01, FR-ACC-02, BR-ACC-01..03, BR-ACC-05, BR-ACC-08: registration, login, logout and onboarding. */
class AccountRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secret123';

    /** @return array<string, string> */
    private function form(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Nguyễn Văn A',
            'email' => 'a@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ];
    }

    public function test_fr_acc_01_br_acc_03_registration_creates_a_signed_in_customer_and_sends_verification(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', $this->form())->assertCreated();

        $user = User::where('email', 'a@example.com')->sole();
        $this->assertSame('customer', $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->organizationMembers()->exists());
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_br_acc_05_registration_ignores_a_requested_role(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', $this->form(['role' => 'platform_admin', 'status' => 'active']))->assertCreated();

        $this->assertSame('customer', User::where('email', 'a@example.com')->value('role'));
    }

    public function test_br_acc_01_an_email_belongs_to_one_account_case_insensitively(): void
    {
        User::factory()->create(['email' => 'a@example.com']);

        $this->postJson('/api/auth/register', $this->form(['email' => '  A@Example.COM ']))
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame(1, User::count());
    }

    public function test_br_acc_02_password_must_meet_the_policy_and_match_its_confirmation(): void
    {
        $this->postJson('/api/auth/register', $this->form(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertJsonValidationErrors('password');
        $this->postJson('/api/auth/register', $this->form(['password' => 'alllowercase1', 'password_confirmation' => 'alllowercase1']))
            ->assertJsonValidationErrors('password');
        $this->postJson('/api/auth/register', $this->form(['password_confirmation' => 'Different123']))
            ->assertJsonValidationErrors('password');
        $this->postJson('/api/auth/register', $this->form(['name' => '', 'email' => 'not-an-email']))
            ->assertJsonValidationErrors(['name', 'email']);

        $this->assertSame(0, User::count());
    }

    public function test_fr_acc_02_login_accepts_valid_credentials_and_rejects_wrong_ones(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com', 'password' => self::PASSWORD]);

        $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'Wrong1234'])->assertUnprocessable();
        $this->assertGuest();
        $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => self::PASSWORD])->assertUnprocessable();

        $this->postJson('/api/auth/login', ['email' => ' A@EXAMPLE.com', 'password' => self::PASSWORD])
            ->assertOk()->assertJsonPath('user.id', $user->id);
        $this->assertAuthenticatedAs($user);
    }

    public function test_fr_acc_02_logout_ends_the_session(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => self::PASSWORD]);
        $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => self::PASSWORD])->assertOk();
        $this->getJson('/api/auth/me')->assertOk();

        $this->postJson('/api/auth/logout')->assertOk();

        $this->assertGuest('web');
        $this->app['auth']->forgetGuards(); // the sanctum guard caches its user for the life of the test app
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_br_acc_08_owner_intent_at_onboarding_only_points_to_partner_registration(): void
    {
        $user = User::factory()->create(['onboarded' => false]);

        $this->actingAs($user)->postJson('/api/auth/onboarding', [
            'account_type' => 'court_owner',
            'phone' => '0912345678',
            'organization_name' => 'CLB Cầu Lông',
            'courts_count' => 4,
        ])->assertOk()
            ->assertJsonPath('next_step', 'partner_registration')
            ->assertJsonPath('partner_registration_url', '/partner/register');

        $user->refresh();
        $this->assertTrue($user->onboarded);
        $this->assertSame('customer', $user->role);
        $this->assertFalse(OrganizationMember::where('user_id', $user->id)->exists());
    }

    public function test_br_acc_05_onboarding_never_changes_the_account_role(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->postJson('/api/auth/onboarding', ['account_type' => 'customer'])->assertOk();

        $this->assertSame('platform_admin', $admin->fresh()->role);
    }
}
