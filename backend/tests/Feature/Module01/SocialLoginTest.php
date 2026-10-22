<?php

namespace Tests\Feature\Module01;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/** FR-ACC-03, BR-ACC-04, BR-ACC-07, BR-ACC-12: Google/Facebook sign-in with a faked provider. */
class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    private const FRONTEND = 'http://localhost:5173';

    private function fakeProvider(string $id, ?string $email, bool $emailVerified = true, string $name = 'Trần Thị B'): void
    {
        $socialUser = (new SocialiteUser)->setRaw(['email_verified' => $emailVerified])
            ->map(['id' => $id, 'email' => $email, 'name' => $name, 'avatar' => null]);
        $driver = Mockery::mock();
        $driver->shouldReceive('redirectUrl')->andReturnSelf();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($socialUser);
        Socialite::shouldReceive('driver')->andReturn($driver);
    }

    public function test_fr_acc_03_br_acc_04_first_google_sign_in_creates_a_verified_customer(): void
    {
        $this->fakeProvider('g-1', 'b@gmail.com');

        $this->get('/api/v1/auth/google/callback')->assertRedirect(self::FRONTEND.'/onboarding');

        $user = User::where('email', 'b@gmail.com')->sole();
        $this->assertSame('customer', $user->role);
        $this->assertSame('google', $user->social_provider);
        $this->assertFalse($user->password_set);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertAuthenticatedAs($user);
    }

    public function test_fr_acc_03_a_returning_identity_signs_into_the_same_account(): void
    {
        $this->fakeProvider('g-1', 'b@gmail.com');
        $this->get('/api/v1/auth/google/callback');
        User::where('email', 'b@gmail.com')->update(['onboarded' => true]);

        $this->get('/api/v1/auth/google/callback')->assertRedirect(self::FRONTEND.'/');

        $this->assertSame(1, User::count());
    }

    public function test_fr_acc_03_a_platform_admin_lands_on_the_admin_area(): void
    {
        User::factory()->create(['email' => 'admin@gmail.com', 'role' => 'platform_admin', 'social_provider' => 'google', 'social_id' => 'g-admin', 'onboarded' => true]);
        $this->fakeProvider('g-admin', 'admin@gmail.com');

        $this->get('/api/v1/auth/google/callback')->assertRedirect(self::FRONTEND.'/admin');
    }

    public function test_br_acc_04_facebook_sign_in_creates_a_customer_whose_email_still_needs_verification(): void
    {
        $this->fakeProvider('fb-1', 'c@example.com');

        $this->get('/api/v1/auth/facebook/callback')->assertRedirect(self::FRONTEND.'/onboarding');

        $user = User::where('email', 'c@example.com')->sole();
        $this->assertSame('customer', $user->role);
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_fr_acc_03_a_facebook_account_without_email_adds_one_before_verifying(): void
    {
        $this->fakeProvider('fb-2', null);
        $this->get('/api/v1/auth/facebook/callback');
        $user = User::where('social_id', 'fb-2')->sole();
        $this->assertTrue($user->needsEmail());

        $this->postJson('/api/auth/email/verification-notification')->assertStatus(422);
        $this->postJson('/api/auth/email', ['email' => 'real@example.com'])->assertOk();

        $this->assertSame('real@example.com', $user->fresh()->email);
    }

    public function test_fr_acc_03_an_email_owned_by_a_password_account_is_not_taken_over(): void
    {
        $owner = User::factory()->create(['email' => 'b@gmail.com']);
        $this->fakeProvider('g-9', 'b@gmail.com');

        $this->get('/api/v1/auth/google/callback')->assertRedirectContains('error=oauth_email_taken');

        $this->assertGuest();
        $this->assertNull($owner->fresh()->social_id);
    }

    public function test_alt_08_a_provider_failure_does_not_sign_in(): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('redirectUrl')->andReturnSelf();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andThrow(new \RuntimeException('denied'));
        Socialite::shouldReceive('driver')->andReturn($driver);

        $this->get('/api/v1/auth/google/callback')->assertRedirectContains('error=oauth_failed');
        $this->assertGuest();
    }

    public function test_br_acc_07_only_google_and_facebook_are_offered(): void
    {
        $this->getJson('/api/auth/twitter/redirect')->assertStatus(400);
        $this->get('/api/v1/auth/github/callback')->assertRedirect(self::FRONTEND.'/login?error=invalid_provider');
        $this->getJson('/api/auth/google/redirect')->assertOk();
    }

    public function test_br_acc_12_a_locked_account_cannot_sign_in_through_a_provider(): void
    {
        $user = User::factory()->create(['email' => 'b@gmail.com', 'social_provider' => 'google', 'password_set' => false, 'status' => 'locked']);
        $user->forceFill(['social_id' => 'g-1'])->save();
        $this->fakeProvider('g-1', 'b@gmail.com');

        $this->get('/api/v1/auth/google/callback')->assertRedirectContains('error=account_restricted');
        $this->assertGuest();
    }
}
