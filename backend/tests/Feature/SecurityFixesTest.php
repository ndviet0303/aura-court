<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_social_profile_payload_cannot_authenticate_an_account(): void
    {
        $victim = User::factory()->create(['email' => 'victim@example.com']);

        foreach ([
            [
                'provider' => 'google',
                'email' => $victim->email,
                'name' => 'Attacker',
                'provider_id' => 'attacker-controlled',
            ],
            [
                'provider' => 'facebook',
                'email' => 'new-attacker-account@example.com',
                'name' => 'Attacker',
            ],
        ] as $payload) {
            $this->postJson('/api/v1/auth/social-login', $payload)->assertNotFound();
        }

        $this->assertGuest();
        $this->assertSame(1, User::query()->count());
    }

    public function test_verified_socialite_callback_still_authenticates_the_provider_identity(): void
    {
        config([
            'services.google.redirect' => 'http://localhost/api/v1/auth/google/callback',
            'app.frontend_url' => 'http://localhost:5173',
        ]);

        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('redirectUrl')
            ->once()
            ->with('http://localhost/api/v1/auth/google/callback')
            ->andReturnSelf();
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn(SocialiteUser::fake([
            'id' => 'provider-user-123',
            'email' => 'verified@example.com',
            'name' => 'Verified User',
        ]));
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->get('/api/v1/auth/google/callback')
            ->assertRedirect('http://localhost:5173/onboarding');

        $user = User::where('email', 'verified@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google', $user->social_provider);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_authenticated_users_cannot_credit_their_own_wallet(): void
    {
        $user = User::factory()->create();

        foreach (['/api/v1/wallet/topup', '/api/v1/wallet/credit'] as $endpoint) {
            $this->actingAs($user)
                ->postJson($endpoint, [
                    'amount' => 50000000,
                    'method' => 'stripe',
                    'wallet_id' => 'attacker-selected',
                ])
                ->assertNotFound();
        }

        $this->assertDatabaseCount('wallet_accounts', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_onboarded_user_cannot_re_onboard_or_escalate_to_admin(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'onboarded' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/auth/onboarding', [
            'account_type' => 'court_owner',
            'organization_name' => 'Attacker Club',
            'phone' => '0987654321',
        ]);

        $response->assertStatus(400);
        $this->assertSame('customer', $user->fresh()->role);
    }

    public function test_non_onboarded_user_can_complete_onboarding_only_once(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'onboarded' => false,
        ]);

        $this->actingAs($user)->postJson('/api/v1/auth/onboarding', [
            'account_type' => 'customer',
            'phone' => '0912345678',
            'play_level' => 'intermediate',
        ])->assertOk();

        $this->assertTrue($user->fresh()->onboarded);
        $this->assertSame('customer', $user->fresh()->role);

        // Subsequent call must be blocked
        $this->actingAs($user->fresh())->postJson('/api/v1/auth/onboarding', [
            'account_type' => 'court_owner',
            'organization_name' => 'Escalated Club',
        ])->assertStatus(400);

        $this->assertSame('customer', $user->fresh()->role);
    }
}
