<?php

namespace Tests\Feature\Module01;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/** FR-ACC-06, BR-ACC-10, BR-ACC-11: password recovery, set-up for linked accounts and change. */
class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    /** Each HTTP request in production gets a new app; here the guards and the default guard carry over. */
    private function signOutTestClient(): void
    {
        $this->app['auth']->forgetGuards();
        config(['auth.defaults.guard' => 'web']);
    }

    private function reset(User $user, string $token, string $password = 'NewSecret1'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/auth/reset-password', [
            'token' => $token, 'email' => $user->email, 'password' => $password, 'password_confirmation' => $password,
        ]);
    }

    public function test_fr_acc_06_reset_link_sets_a_new_password_once(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'a@example.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'A@example.com'])->assertOk();
        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($n) use (&$token) {
            $token = (fn () => $this->token)->call($n);

            return true;
        });

        $this->reset($user, $token)->assertOk();
        $this->assertTrue(Hash::check('NewSecret1', $user->fresh()->password));

        $this->reset($user, $token, 'Another123')->assertUnprocessable();
        $this->assertTrue(Hash::check('NewSecret1', $user->fresh()->password));
    }

    public function test_fr_acc_06_unknown_email_gets_the_same_answer_without_mail(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])
            ->assertOk()->assertJsonPath('message', __('auth.password_reset_sent'));

        Notification::assertNothingSent();
    }

    public function test_br_acc_11_an_expired_reset_link_is_refused(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->travel(16)->minutes();

        $this->reset($user, $token)->assertUnprocessable();
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_br_acc_02_reset_password_follows_the_password_policy(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->reset($user, $token, 'weak')->assertJsonValidationErrors('password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_br_acc_10_a_linked_account_without_password_sets_one_through_the_mailed_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password_set' => false, 'social_provider' => 'google']);

        $this->actingAs($user)->patchJson('/api/profile/password', [
            'current_password' => 'x', 'password' => 'NewSecret1', 'password_confirmation' => 'NewSecret1',
        ])->assertUnprocessable();

        $this->actingAs($user)->postJson('/api/profile/password/setup-link')->assertOk();
        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($n) use (&$token) {
            $token = (fn () => $this->token)->call($n);

            return true;
        });
        $this->reset($user, $token)->assertOk();

        $this->assertTrue($user->fresh()->password_set);
        $this->signOutTestClient();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'NewSecret1'])->assertOk();
    }

    public function test_br_acc_10_set_up_link_is_refused_when_a_password_already_exists(): void
    {
        $this->actingAs(User::factory()->create()->fresh())->postJson('/api/profile/password/setup-link')->assertUnprocessable();
    }

    public function test_password_change_needs_the_current_password_and_ends_the_session(): void
    {
        $user = User::factory()->create(['password' => 'Secret123'])->fresh();

        $this->actingAs($user)->patchJson('/api/profile/password', [
            'current_password' => 'Wrong1234', 'password' => 'NewSecret1', 'password_confirmation' => 'NewSecret1',
        ])->assertJsonValidationErrors('current_password');

        $this->actingAs($user)->patchJson('/api/profile/password', [
            'current_password' => 'Secret123', 'password' => 'NewSecret1', 'password_confirmation' => 'NewSecret1',
        ])->assertOk()->assertJsonPath('logged_out', true);

        $this->assertTrue(Hash::check('NewSecret1', $user->fresh()->password));
        $this->assertGuest('web');
    }
}
