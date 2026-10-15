<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Rules\AccountPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Account rules of module 1: BR-02, BR-03, BR-08, BR-09, BR-10, BR-24. */
class AccountRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_passwords_follow_the_account_password_policy(): void
    {
        foreach (['short1A', 'alllower123', 'ALLUPPER123', 'NoDigitsHere', 'Has Space1', 'Aa1'.str_repeat('x', 62)] as $weak) {
            $this->assertTrue(Validator::make(['password' => $weak], ['password' => [new AccountPassword]])->fails(), $weak);
        }
        $this->assertTrue(Validator::make(['password' => 'Aa1'.str_repeat('x', 61)], ['password' => [new AccountPassword]])->passes());

        $this->postJson('/api/auth/register', [
            'name' => 'Weak Password', 'email' => 'weak@example.com',
            'password' => 'weakpass', 'password_confirmation' => 'weakpass',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        $this->postJson('/api/auth/register', [
            'name' => 'Strong Password', 'email' => 'strong@example.com',
            'password' => 'Strong#Pass1', 'password_confirmation' => 'Strong#Pass1',
        ])->assertCreated();
    }

    public function test_emails_are_trimmed_and_lower_cased_so_one_address_maps_to_one_account(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Mixed Case', 'email' => '  Mixed.Case@Example.COM ',
            'password' => 'Strong#Pass1', 'password_confirmation' => 'Strong#Pass1',
        ])->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'mixed.case@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Duplicate', 'email' => 'MIXED.case@example.com',
            'password' => 'Strong#Pass1', 'password_confirmation' => 'Strong#Pass1',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);

        auth()->guard('web')->logout();
        $this->postJson('/api/auth/login', ['email' => 'MIXED.CASE@example.com', 'password' => 'Strong#Pass1'])->assertOk();
    }

    public function test_profile_name_and_phone_follow_the_profile_rules(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/profile', ['name' => 'A'])->assertStatus(422)->assertJsonValidationErrors(['name']);
        $this->patchJson('/api/profile', ['name' => str_repeat('a', 101)])->assertStatus(422)->assertJsonValidationErrors(['name']);
        foreach (['090123456', '1901234567', '+8490123456', '+84 901234567'] as $phone) {
            $this->patchJson('/api/profile', ['phone' => $phone])->assertStatus(422)->assertJsonValidationErrors(['phone']);
        }
        $this->patchJson('/api/profile', ['name' => 'Lê An', 'phone' => '+84901234567'])->assertOk();
        $this->assertTrue($user->fresh()->hasCompleteProfile());

        $user->forceFill(['phone' => null])->save();
        $this->assertFalse($user->fresh()->hasCompleteProfile());
    }

    public function test_a_new_password_must_differ_from_the_current_one(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Current#Pass1'), 'password_set' => true]);
        $this->actingAs($user, 'web');

        $this->patchJson('/api/profile/password', [
            'current_password' => 'Current#Pass1', 'password' => 'Current#Pass1', 'password_confirmation' => 'Current#Pass1',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        $this->patchJson('/api/profile/password', [
            'current_password' => 'Current#Pass1', 'password' => 'Another#Pass2', 'password_confirmation' => 'Another#Pass2',
        ])->assertOk();
    }

    public function test_a_social_only_account_sets_its_password_through_its_verified_email(): void
    {
        Notification::fake();
        $social = User::factory()->create(['password_set' => false, 'social_provider' => 'google']);
        Sanctum::actingAs($social);

        $this->patchJson('/api/profile/password', ['password' => 'Brand#New1', 'password_confirmation' => 'Brand#New1'])
            ->assertStatus(422);
        $this->assertFalse((bool) $social->fresh()->password_set);

        $this->postJson('/api/profile/password/setup-link')->assertOk();
        Notification::assertSentTo($social, ResetPasswordNotification::class);

        $unverified = User::factory()->unverified()->create(['password_set' => false, 'social_provider' => 'facebook']);
        Sanctum::actingAs($unverified);
        $this->postJson('/api/profile/password/setup-link')->assertStatus(422);
        Notification::assertNotSentTo($unverified, ResetPasswordNotification::class);
    }

    public function test_password_recovery_only_mails_accounts_with_a_password_and_a_verified_email(): void
    {
        Notification::fake();
        $socialOnly = User::factory()->create(['password_set' => false]);
        $unverified = User::factory()->unverified()->create();
        $eligible = User::factory()->create();

        foreach ([$socialOnly, $unverified, $eligible] as $user) {
            $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();
        }

        Notification::assertNotSentTo($socialOnly, ResetPasswordNotification::class);
        Notification::assertNotSentTo($unverified, ResetPasswordNotification::class);
        Notification::assertSentTo($eligible, ResetPasswordNotification::class);
    }

    public function test_logging_out_keeps_remember_me_on_other_devices(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Strong#Pass1')]);
        $user->forceFill(['remember_token' => 'other-device-token'])->save();
        $this->actingAs($user, 'web')->withHeader('Referer', 'http://localhost');

        $this->postJson('/api/auth/logout')->assertOk();

        $this->assertSame('other-device-token', $user->fresh()->remember_token);
    }
}
