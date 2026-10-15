<?php

namespace Tests\Feature\Account;

use App\Models\OrganizationMember;
use App\Models\PriceRule;
use App\Models\Reservation;
use App\Models\TenantCustomer;
use App\Models\User;
use App\Services\Booking\OrdinaryCheckoutService;
use App\Services\Booking\RecurringSeriesService;
use App\Services\Payment\WalletPaymentService;
use App\Services\Reservation\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EmailVerificationBookingEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_customer_calling_booking_is_denied(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $court = $fixture['org1']['court1'];

        $user = User::factory()->unverified()->create([
            'role' => 'customer',
            'status' => 'active',
        ]);

        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id);
        $wallet->update(['balance' => 5000000]);

        Sanctum::actingAs($user);

        $slot = Carbon::tomorrow()->setTime(9, 0, 0)->toIso8601String();

        $response = $this->withHeader('Idempotency-Key', 'test-booking-'.uniqid())->postJson('/api/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$slot],
        ]);

        $response->assertStatus(403);
        $this->assertSame(0, Reservation::count());
    }

    public function test_unverified_customer_calling_recurring_is_denied(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $court = $fixture['org1']['court1'];

        $user = User::factory()->unverified()->create([
            'role' => 'customer',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $start = Carbon::tomorrow()->setTime(10, 0, 0)->toIso8601String();

        $response = $this->withHeader('Idempotency-Key', 'test-recurring-'.uniqid())->postJson('/api/recurring-series', [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'starts_at' => $start,
            'occurrences' => 4,
        ]);

        $response->assertStatus(403);
        $this->assertSame(0, Reservation::count());
    }

    public function test_unverified_customer_calling_v2_reservations_directly_fails_with_guidance(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $court = $fixture['org1']['court1'];
        $org = $fixture['org1']['org'];

        $user = User::factory()->unverified()->create([
            'role' => 'customer',
            'status' => 'active',
        ]);
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'customer',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $slot = Carbon::tomorrow()->setTime(14, 0, 0)->toIso8601String();

        $response = $this->withHeaders([
            'X-Organization-Id' => (string) $org->id,
            'Idempotency-Key' => 'test-idemp-'.uniqid(),
        ])->postJson('/api/reservations', [
            'court_id' => $court->id,
            'slots' => [$slot],
        ]);

        $response->assertStatus(403);
        $msg = strtolower((string) $response->json('message'));
        $this->assertTrue(str_contains($msg, 'verified') || str_contains($msg, 'xác thực'));
        $this->assertSame(0, Reservation::count());
    }

    public function test_unverified_customer_calling_v2_checkout_directly_fails_with_guidance(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $court = $fixture['org1']['court1'];
        $org = $fixture['org1']['org'];

        $user = User::factory()->unverified()->create([
            'role' => 'customer',
            'status' => 'active',
        ]);
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'customer',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $slot = Carbon::tomorrow()->setTime(15, 0, 0)->toIso8601String();

        $response = $this->withHeaders([
            'X-Organization-Id' => (string) $org->id,
            'Idempotency-Key' => 'test-checkout-'.uniqid(),
        ])->postJson('/api/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$slot],
        ]);

        $response->assertStatus(403);
        $this->assertSame(0, Reservation::count());
    }

    public function test_unverified_customer_calling_v2_recurring_series_directly_fails_with_guidance(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $court = $fixture['org1']['court1'];
        $org = $fixture['org1']['org'];

        $user = User::factory()->unverified()->create([
            'role' => 'customer',
            'status' => 'active',
        ]);
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'customer',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $start = Carbon::tomorrow()->setTime(16, 0, 0)->toIso8601String();

        $response = $this->withHeaders([
            'X-Organization-Id' => (string) $org->id,
            'Idempotency-Key' => 'test-recurring-'.uniqid(),
        ])->postJson('/api/recurring-series', [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'starts_at' => $start,
            'occurrences' => 4,
        ]);

        $response->assertStatus(403);
    }

    public function test_direct_service_calls_cannot_bypass_email_verification(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $court = $fixture['org1']['court1'];
        $org = $fixture['org1']['org'];
        $org->update(['partner_status' => 'approved']);

        $user = User::factory()->unverified()->create([
            'role' => 'customer',
            'status' => 'active',
        ]);

        $slot = Carbon::tomorrow()->setTime(17, 0, 0)->toIso8601String();

        // 1. Direct OrdinaryCheckoutService::checkout without actor
        $checkoutService = app(OrdinaryCheckoutService::class);
        try {
            $checkoutService->checkout($user, $org->id, [
                'court_id' => $court->id,
                'slots' => [$slot],
            ], actor: null);
            $this->fail('checkout directly must not bypass email verification');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }

        // 2. Direct ReservationService::hold without actor
        $reservationService = app(ReservationService::class);
        try {
            $reservationService->hold($user, $org->id, [
                'court_id' => $court->id,
                'slots' => [$slot],
            ], actor: null);
            $this->fail('hold directly must not bypass email verification');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }

        // 3. Direct RecurringSeriesService::createAndConfirm
        $recurringService = app(RecurringSeriesService::class);
        try {
            $recurringService->createAndConfirm($user, $org->id, [
                'court_id' => $court->id,
                'frequency' => 'weekly',
                'starts_at' => $slot,
                'occurrences' => 4,
            ]);
            $this->fail('createAndConfirm directly must not bypass email verification');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }
    }

    public function test_staff_assisted_walkin_can_proceed_for_walkin_customer_with_authorized_actor(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $court = $fixture['org1']['court1'];
        $org = $fixture['org1']['org'];
        $org->update(['partner_status' => 'approved']);

        PriceRule::create([
            'organization_id' => $org->id,
            'court_id' => $court->id,
            'type' => 'base',
            'price' => 100000,
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $staff->id,
            'role' => 'staff',
            'status' => 'active',
        ]);
        $this->assignStaffVenues($org, $fixture['org1']['venue1']);

        $walkinCustomer = User::factory()->unverified()->create([
            'role' => 'customer',
            'status' => 'active',
        ]);
        TenantCustomer::create([
            'organization_id' => $org->id,
            'user_id' => $walkinCustomer->id,
            'name' => 'Walkin Customer',
        ]);

        Sanctum::actingAs($staff);

        $slot = Carbon::tomorrow()->setTime(18, 0, 0)->toIso8601String();

        $response = $this->withHeaders([
            'X-Organization-Id' => (string) $org->id,
            'Idempotency-Key' => 'test-walkin-'.uniqid(),
        ])->postJson('/api/reservations', [
            'court_id' => $court->id,
            'slots' => [$slot],
            'customer_id' => $walkinCustomer->id,
        ]);

        $response->assertCreated();
        $reservation = Reservation::first();
        $this->assertEquals($walkinCustomer->id, $reservation->user_id);
    }
}
