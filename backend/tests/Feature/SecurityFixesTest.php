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
}
