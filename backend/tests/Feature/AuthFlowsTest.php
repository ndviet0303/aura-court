<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_a_reset_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
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

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }
}
