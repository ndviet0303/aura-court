<?php

namespace Tests\Feature\Account;

use App\Models\OrganizationMember;
use App\Models\PointLedger;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\TenantCustomer;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Payment\WalletPaymentService;
use App\Services\Social\MarketplaceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RestrictedAccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_login_is_blocked_for_restricted_accounts(): void
    {
        foreach (['suspended', 'banned', 'locked'] as $status) {
            $user = User::factory()->create([
                'email' => "user_{$status}@example.com",
                'password' => bcrypt('secret123'),
                'status' => $status,
                'email_verified_at' => now(),
            ]);

            $response = $this->postJson('/api/auth/login', [
                'email' => "user_{$status}@example.com",
                'password' => 'secret123',
            ]);

            $response->assertStatus(403);
            $response->assertJson([
                'error' => 'account_restricted',
            ]);
            $this->assertGuest();
        }
    }

    public function test_oauth_login_callback_is_blocked_for_restricted_accounts(): void
    {
        $user = User::factory()->create([
            'email' => 'oauth_restricted@example.com',
            'status' => 'locked',
            'email_verified_at' => now(),
        ]);

        $provider = \Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('redirectUrl')
            ->once()
            ->withAnyArgs()
            ->andReturnSelf();
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn(SocialiteUser::fake([
            'id' => 'google-restricted-123',
            'email' => 'oauth_restricted@example.com',
            'name' => 'Restricted User',
        ]));
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $response = $this->get('/api/auth/google/callback');

        $response->assertRedirect();
        $this->assertStringContainsString('error=account_restricted', $response->headers->get('Location'));
        $this->assertGuest();
    }

    public function test_existing_session_cannot_create_booking_when_account_becomes_restricted(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $court = $fixture['org1']['court1'];
        $user = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        // Credit user wallet
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id);
        $wallet->update(['balance' => 5000000]);

        // Establish session
        Sanctum::actingAs($user);

        // Account is locked after login
        $user->update(['status' => 'locked']);

        $slot = Carbon::tomorrow()->setTime(9, 0, 0)->toIso8601String();

        $initialReservations = Reservation::count();
        $initialTransactions = WalletTransaction::count();
        $initialPoints = PointLedger::count();

        $response = $this->withHeader('Idempotency-Key', 'test-booking-'.uniqid())->postJson('/api/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$slot],
        ]);

        $response->assertStatus(403);
        $this->assertSame($initialReservations, Reservation::count(), 'No new booking must be created.');
        $this->assertSame($initialTransactions, WalletTransaction::count(), 'No wallet transaction must occur.');
        $this->assertSame($initialPoints, PointLedger::count(), 'No points must be earned or deducted.');
        $this->assertEquals(5000000, $wallet->fresh()->balance, 'Wallet balance must be preserved.');
    }

    public function test_existing_session_cannot_create_v2_reservation_when_account_becomes_restricted(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $court = $fixture['org1']['court1'];
        $org = $fixture['org1']['org'];
        $user = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        // Account is suspended
        $user->update(['status' => 'suspended']);

        $slot = Carbon::tomorrow()->setTime(10, 0, 0)->toIso8601String();

        $initialReservations = Reservation::count();

        $response = $this->withHeaders([
            'X-Organization-Id' => (string) $org->id,
        ])->postJson('/api/reservations', [
            'court_id' => $court->id,
            'slots' => [$slot],
        ]);

        $response->assertStatus(403);
        $this->assertSame($initialReservations, Reservation::count(), 'No reservation must be held.');
    }

    public function test_existing_session_cannot_create_recurring_booking_when_restricted(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $court = $fixture['org1']['court1'];
        $user = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $user->update(['status' => 'banned']);

        $start = Carbon::tomorrow()->setTime(14, 0, 0)->toIso8601String();

        $initialReservations = Reservation::count();

        $response = $this->withHeader('Idempotency-Key', 'test-recurring-'.uniqid())->postJson('/api/recurring-series', [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'starts_at' => $start,
            'occurrences' => 4,
        ]);

        $response->assertStatus(403);
        $this->assertSame($initialReservations, Reservation::count(), 'No recurring bookings must be created.');
    }

    public function test_staff_assisted_walkin_blocks_restricted_customer(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $court = $fixture['org1']['court1'];
        $org = $fixture['org1']['org'];
        $staff = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $staff->id,
            'role' => 'staff',
            'status' => 'active',
        ]);
        $customer = User::factory()->create([
            'role' => 'customer',
            'status' => 'locked',
            'email_verified_at' => now(),
        ]);
        TenantCustomer::create([
            'organization_id' => $org->id,
            'user_id' => $customer->id,
            'name' => 'Locked Customer',
        ]);

        Sanctum::actingAs($staff);

        $slot = Carbon::tomorrow()->setTime(11, 0, 0)->toIso8601String();

        $response = $this->withHeaders([
            'X-Organization-Id' => (string) $org->id,
            'Idempotency-Key' => 'test-walkin-'.uniqid(),
        ])->postJson('/api/reservations', [
            'court_id' => $court->id,
            'slots' => [$slot],
            'customer_id' => $customer->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_restricted_user_can_still_check_auth_me_and_logout(): void
    {
        $user = User::factory()->create([
            'status' => 'locked',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        // GET auth/me should succeed and report locked status
        $meRes = $this->getJson('/api/auth/me');
        $meRes->assertStatus(200);
        $this->assertEquals('locked', $meRes->json('status'));

        // POST auth/logout should succeed
        $logoutRes = $this->postJson('/api/auth/logout');
        $logoutRes->assertStatus(200);
    }

    public function test_restricted_user_cannot_list_or_purchase_marketplace_transfer(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $org = $fixture['org1']['org'];

        $seller = User::factory()->create(['status' => 'locked', 'email_verified_at' => now()]);
        $buyer = User::factory()->create(['status' => 'suspended', 'email_verified_at' => now()]);
        $reservation = Reservation::create([
            'organization_id' => $org->id,
            'user_id' => $seller->id,
            'court_id' => $fixture['org1']['court1']->id,
            'code' => 'RES-TEST-'.uniqid(),
            'status' => 'confirmed',
            'total_amount' => 100000,
        ]);
        $slot = ReservationSlot::create([
            'organization_id' => $org->id,
            'reservation_id' => $reservation->id,
            'court_id' => $fixture['org1']['court1']->id,
            'starts_at' => now()->addHours(5),
            'ends_at' => now()->addHours(6),
            'unit_price' => 100000,
            'status' => 'confirmed',
        ]);

        $entitlement = ReservationEntitlement::create([
            'organization_id' => $org->id,
            'reservation_id' => $reservation->id,
            'reservation_slot_id' => $slot->id,
            'user_id' => $seller->id,
            'court_id' => $fixture['org1']['court1']->id,
            'status' => 'active',
            'starts_at' => now()->addHours(5),
            'ends_at' => now()->addHours(6),
            'amount' => 100000,
        ]);

        $marketplace = app(MarketplaceService::class);

        try {
            $marketplace->list($entitlement, $seller, 100000);
            $this->fail('Restricted seller should be blocked from listing.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }

        // If listed by active seller, restricted buyer cannot purchase
        $seller->update(['status' => 'active']);
        $listing = $marketplace->list($entitlement, $seller, 100000);

        try {
            $marketplace->purchase($listing, $buyer, 'idemp-'.uniqid(), 'pay-ref-'.uniqid());
            $this->fail('Restricted buyer should be blocked from purchasing.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }
    }
}
