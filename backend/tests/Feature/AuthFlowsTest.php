<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_a_reset_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', 'Nếu email tồn tại, liên kết đặt lại mật khẩu đã được gửi.');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_reset_password_updates_the_password_and_redirect_route_points_to_frontend(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com', 'password' => 'OldPassword123!']);
        $token = Password::broker()->createToken($user);

        $this->get('/reset-password/'.$token.'?email='.urlencode($user->email))
            ->assertRedirectContains('/reset-password?token='.$token)
            ->assertRedirectContains('email='.urlencode($user->email));

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    public function test_registration_creates_user_and_dispatches_registered_event(): void
    {
        Event::fake([Registered::class]);

        $payload = [
            'name' => 'Athlete User',
            'email' => 'newuser@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ];

        $response = $this->postJson('/api/auth/register', $payload);
        $response->assertCreated()
            ->assertJsonPath('user.email', 'newuser@example.com');

        $user = User::where('email', 'newuser@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertSame('customer', $user->role);

        Event::assertDispatched(Registered::class, function ($event) use ($user) {
            return $event->user->id === $user->id;
        });
    }

    public function test_email_verification_persists_with_signed_url(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->create(['email_verified_at' => null]);

        $signedUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $response = $this->getJson($signedUrl);
        $response->assertOk()
            ->assertJsonPath('message', 'Email đã được xác thực thành công.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);

        // Idempotent: verifying again succeeds without error
        $response2 = $this->getJson($signedUrl);
        $response2->assertOk();
    }

    public function test_expired_or_invalid_email_verification_url_fails_without_verifying(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        // 1. Expired URL
        $expiredUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinutes(10),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );
        $this->getJson($expiredUrl)->assertStatus(403);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());

        // 2. Tampered hash
        $tamperedUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            [
                'id' => $user->id,
                'hash' => 'invalid-tampered-hash',
            ]
        );
        $this->getJson($tamperedUrl)->assertStatus(403);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_login_and_logout_revokes_token_and_protected_access(): void
    {
        $user = User::factory()->create([
            'email' => 'loginuser@example.com',
            'password' => 'Password123!',
        ]);

        // Login via session
        $loginRes = $this->postJson('/api/auth/login', [
            'email' => 'loginuser@example.com',
            'password' => 'Password123!',
        ]);
        $loginRes->assertOk()->assertJsonPath('user.email', 'loginuser@example.com');

        // Can access protected route
        $this->getJson('/api/auth/me')->assertOk();

        // Logout
        $this->postJson('/api/auth/logout')->assertOk();
        $this->app['auth']->forgetGuards();

        // Protected request is now rejected
        $this->getJson('/api/auth/me')->assertUnauthorized();
        // Bearer token authentication test
        $token = $user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertOk();

        // Logout with bearer token
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout')
            ->assertOk();
        $this->app['auth']->forgetGuards();
        // Protected request with revoked token fails
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_password_reset_replay_and_expired_token_rejected_with_actionable_error(): void
    {
        $user = User::factory()->create([
            'email' => 'replay@example.com',
            'password' => 'InitialPassword123!',
        ]);
        $token = Password::broker()->createToken($user);

        // 1. Consume token once
        $firstReset = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'SecondPassword123!',
            'password_confirmation' => 'SecondPassword123!',
        ]);
        $firstReset->assertOk();
        $this->assertTrue(Hash::check('SecondPassword123!', $user->fresh()->password));

        // 2. Replay the same token: MUST fail and return actionable error message
        $replayReset = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ThirdPassword123!',
            'password_confirmation' => 'ThirdPassword123!',
        ]);
        $replayReset->assertStatus(422)
            ->assertJsonPath('message', 'Mã đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.');

        // Password remains unchanged from the first reset
        $this->assertTrue(Hash::check('SecondPassword123!', $user->fresh()->password));

        // 3. Completely invalid token
        $invalidReset = $this->postJson('/api/auth/reset-password', [
            'token' => 'invalid-token-xyz',
            'email' => $user->email,
            'password' => 'FourthPassword123!',
            'password_confirmation' => 'FourthPassword123!',
        ]);
        $invalidReset->assertStatus(422)
            ->assertJsonPath('message', 'Mã đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.');
    }

    public function test_password_change_requires_valid_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'CurrentPassword123!',
            'password_set' => true,
        ]);

        // Wrong current password
        $this->actingAs($user)->patchJson('/api/profile/password', [
            'current_password' => 'WrongPassword!',
            'password' => 'NewPassword999!',
            'password_confirmation' => 'NewPassword999!',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('CurrentPassword123!', $user->fresh()->password));

        // Correct current password
        $this->actingAs($user)->patchJson('/api/profile/password', [
            'current_password' => 'CurrentPassword123!',
            'password' => 'NewPassword999!',
            'password_confirmation' => 'NewPassword999!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword999!', $user->fresh()->password));
    }

    public function test_profile_update_persists_valid_fields_and_reloads(): void
    {
        $user = User::factory()->create([
            'name' => 'Initial Name',
            'phone' => '0901111111',
            'play_level' => 'beginner',
            'favorite_sports' => ['badminton'],
            'avatar_url' => 'https://example.com/old.png',
        ]);

        $payload = [
            'name' => 'Updated Athlete Name',
            'phone' => '0909999999',
            'play_level' => 'advanced',
            'favorite_sports' => ['badminton', 'pickleball'],
            'avatar_url' => 'https://example.com/new.png',
        ];

        // 1. Update profile via PATCH /api/profile
        $updateRes = $this->actingAs($user)->patchJson('/api/profile', $payload);
        $updateRes->assertOk()
            ->assertJsonPath('name', 'Updated Athlete Name')
            ->assertJsonPath('phone', '0909999999')
            ->assertJsonPath('play_level', 'advanced')
            ->assertJsonPath('avatar_url', 'https://example.com/new.png');

        // 2. Verify database was persisted
        $freshUser = $user->fresh();
        $this->assertSame('Updated Athlete Name', $freshUser->name);
        $this->assertSame('0909999999', $freshUser->phone);
        $this->assertSame('advanced', $freshUser->play_level);
        $this->assertSame(['badminton', 'pickleball'], $freshUser->favorite_sports);
        $this->assertSame('https://example.com/new.png', $freshUser->avatar_url);

        // 3. Reload journey via GET /api/auth/me
        $reloadRes = $this->actingAs($user)->getJson('/api/auth/me');
        $reloadRes->assertOk()
            ->assertJsonPath('name', 'Updated Athlete Name')
            ->assertJsonPath('phone', '0909999999')
            ->assertJsonPath('play_level', 'advanced')
            ->assertJsonPath('avatar_url', 'https://example.com/new.png');
    }

    public function test_profile_update_returns_actionable_validation_errors(): void
    {
        $user = User::factory()->create([
            'name' => 'Unchanged Name',
            'play_level' => 'intermediate',
        ]);

        // Invalid play_level value
        $response = $this->actingAs($user)->patchJson('/api/profile', [
            'play_level' => 'non_existent_tier',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['play_level']);

        $this->assertSame('intermediate', $user->fresh()->play_level);
    }

    public function test_auth_endpoints_return_localized_messages_based_on_locale(): void
    {
        $user = User::factory()->create();

        // 1. Invalid verification link with English locale
        $resEn = $this->withHeader('X-Locale', 'en')
            ->getJson("/api/auth/verify-email/{$user->id}/invalid-hash");
        $resEn->assertStatus(403)
            ->assertJsonPath('message', 'The verification link is invalid.');

        // 2. Invalid verification link with Vietnamese locale
        $resVi = $this->withHeader('X-Locale', 'vi')
            ->getJson("/api/auth/verify-email/{$user->id}/invalid-hash");
        $resVi->assertStatus(403)
            ->assertJsonPath('message', 'Liên kết xác thực không hợp lệ.');

        // 3. Login failure with English locale
        $loginEn = $this->withHeader('X-Locale', 'en')
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'WrongPassword!',
            ]);
        $loginEn->assertStatus(422)
            ->assertJsonPath('message', 'These credentials do not match our records.');

        // 4. Login failure with Vietnamese locale
        $loginVi = $this->withHeader('X-Locale', 'vi')
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'WrongPassword!',
            ]);
        $loginVi->assertStatus(422)
            ->assertJsonPath('message', 'Thông tin đăng nhập không đúng.');
    }
}
