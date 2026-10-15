<?php

namespace Tests\Feature\Auth;

use App\Domain\Partner\Actions\ApprovePartnerApplication;
use App\Domain\Partner\Actions\ReviewPartnerApplication;
use App\Domain\Partner\DTOs\ReviewPartnerApplicationData;
use App\Models\HostKyc;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** Security rules of module 1 (accounts, partners, permissions): BR-05, BR-06, BR-10, BR-15, BR-20. */
class Module1SecurityRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.frontend_url' => 'http://localhost:5173']);
    }

    private function fakeSocialUser(string $provider, array $attributes): void
    {
        config(["services.{$provider}.redirect" => "http://localhost/api/v1/auth/{$provider}/callback"]);
        $driver = Mockery::mock(AbstractProvider::class);
        $driver->shouldReceive('redirectUrl')->andReturnSelf();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn(SocialiteUser::fake($attributes));
        Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
    }

    public function test_social_login_never_signs_into_an_existing_account_by_matching_email(): void
    {
        User::factory()->create(['email' => 'victim@example.com', 'password_set' => true]);
        $this->fakeSocialUser('google', ['id' => 'attacker-1', 'email' => 'VICTIM@example.com', 'email_verified' => true]);

        $this->get('/api/v1/auth/google/callback')
            ->assertRedirectContains('/login?error=oauth_email_taken');

        $this->assertGuest();
        $this->assertSame(1, User::count());
    }

    public function test_social_login_returns_to_the_account_holding_the_provider_identity(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com', 'social_provider' => 'google', 'onboarded' => true,
        ]);
        $user->forceFill(['social_id' => 'g-42'])->save();
        $this->fakeSocialUser('google', ['id' => 'g-42', 'email' => 'renamed@example.com', 'email_verified' => true]);

        $this->get('/api/v1/auth/google/callback')->assertRedirect('http://localhost:5173/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_only_a_provider_verified_email_is_marked_verified(): void
    {
        $this->fakeSocialUser('facebook', ['id' => 'fb-7', 'email' => 'fan@example.com']);

        $this->get('/api/v1/auth/facebook/callback')->assertRedirect('http://localhost:5173/onboarding');

        $user = User::where('email', 'fan@example.com')->firstOrFail();
        $this->assertSame('fb-7', $user->social_id);
        $this->assertNull($user->email_verified_at);
    }

    public function test_a_legacy_oauth_account_is_claimed_once_when_the_provider_vouches_for_the_email(): void
    {
        $legacy = User::factory()->create([
            'email' => 'legacy@example.com', 'social_provider' => 'google', 'password_set' => false,
        ]);
        $this->fakeSocialUser('google', ['id' => 'g-legacy', 'email' => 'legacy@example.com', 'email_verified' => true]);

        $this->get('/api/v1/auth/google/callback');

        $this->assertAuthenticatedAs($legacy);
        $this->assertSame('g-legacy', $legacy->fresh()->social_id);
    }

    public function test_staff_without_assigned_venues_reach_no_venue(): void
    {
        $org = Organization::create(['name' => 'Scope Org', 'slug' => 'scope-org', 'status' => 'active', 'partner_status' => 'approved']);
        $other = Organization::create(['name' => 'Other Org', 'slug' => 'other-org', 'status' => 'active', 'partner_status' => 'approved']);
        $venue = $this->venue($org, 'scope-venue');
        $foreignVenue = $this->venue($other, 'foreign-venue');

        $unassigned = User::factory()->create();
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $unassigned->id, 'role' => OrganizationMember::ROLE_STAFF, 'status' => 'active']);

        $this->assertFalse($unassigned->canOperateVenue($venue));
        $this->assertSame([], $unassigned->getAuthorizedVenueIds());

        // A venue id from another organization never widens a member's scope.
        $assigned = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $org->id, 'user_id' => $assigned->id, 'role' => OrganizationMember::ROLE_STAFF, 'status' => 'active',
            'permissions' => ['venue_ids' => [$venue->id, $foreignVenue->id]],
        ]);

        $this->assertTrue($assigned->canOperateVenue($venue));
        $this->assertFalse($assigned->canOperateVenue($foreignVenue));
        $this->assertSame([$venue->id], $assigned->getAuthorizedVenueIds());
    }

    public function test_a_reviewer_cannot_decide_their_own_partner_application(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $kyc = HostKyc::create([
            'kyc_code' => 'KYC-SELF-01', 'user_id' => $admin->id, 'current_user_id' => $admin->id,
            'business_type' => 'individual', 'business_name' => 'Self Review', 'legal_name' => 'Self Review',
            'tax_code' => '0101010101', 'representative_name' => 'Self Review', 'id_card_number' => '079123456789',
            'bank_name' => 'VCB', 'bank_code' => 'VCB', 'bank_account_number' => '111222', 'bank_account_holder' => 'SELF REVIEW',
            'status' => 'pending', 'version' => 1,
        ]);

        foreach ([[ReviewPartnerApplication::class, 'reject'], [ApprovePartnerApplication::class, 'approve']] as [$action, $decision]) {
            try {
                app($action)->execute(new ReviewPartnerApplicationData(
                    reviewerId: $admin->id,
                    kycId: $kyc->id,
                    decision: $decision,
                    reason: 'Tự xét duyệt hồ sơ của chính mình.',
                    reviewerNotes: null,
                    feedbackFields: null,
                    expectedVersion: 1,
                ));
                $this->fail("{$action} let a reviewer decide their own application.");
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }

        $this->assertSame('pending', $kyc->fresh()->status);
    }

    public function test_verification_emails_are_rate_limited_per_account(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/email/verification-notification')->assertOk();
        $this->postJson('/api/auth/email/verification-notification')->assertStatus(429);
    }

    public function test_verification_and_reset_links_last_fifteen_minutes(): void
    {
        $this->freezeTime();
        $user = User::factory()->unverified()->create();
        $notification = new class extends VerifyEmailNotification
        {
            public function url(object $notifiable): string
            {
                return $this->verificationUrl($notifiable);
            }
        };

        parse_str((string) parse_url($notification->url($user), PHP_URL_QUERY), $query);

        $this->assertSame(now()->addMinutes(15)->getTimestamp(), (int) $query['expires']);
        $this->assertSame(15, config('auth.passwords.users.expire'));
    }

    public function test_password_reset_requests_are_limited_to_one_a_minute_per_email(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'reset@example.com'])->assertOk();
        $this->postJson('/api/auth/forgot-password', ['email' => 'RESET@example.com'])->assertStatus(429);
    }

    public function test_changing_the_password_ends_sessions_signed_in_with_the_old_one(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword1')]);
        $this->actingAs($user, 'web')->withHeader('Referer', 'http://localhost');
        $this->getJson('/api/auth/me')->assertOk();

        // Another device changes the password; this session still carries the old hash.
        $user->forceFill(['password' => Hash::make('NewPassword1')])->save();

        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    private function venue(Organization $org, string $slug): Venue
    {
        return Venue::create([
            'organization_id' => $org->id, 'name' => ['vi' => $slug, 'en' => $slug], 'slug' => $slug,
            'address' => '1 Test Street', 'status' => 'published', 'is_active' => true,
        ]);
    }
}
