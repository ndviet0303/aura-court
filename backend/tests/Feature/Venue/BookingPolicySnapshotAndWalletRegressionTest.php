<?php

namespace Tests\Feature\Venue;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\WalletPaymentService;
use App\Services\Reservation\LegacyBookingBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PricesCourts;
use Tests\TestCase;

class BookingPolicySnapshotAndWalletRegressionTest extends TestCase
{
    use PricesCourts;
    use RefreshDatabase;

    private function createCustomerWithWallet(int $orgId, int $balance = 500000): User
    {
        $user = User::factory()->create(['role' => 'customer']);
        OrganizationMember::create(['organization_id' => $orgId, 'user_id' => $user->id, 'role' => 'customer', 'status' => 'active']);
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id, $orgId);
        $walletService->credit($wallet, $balance, 'test_topup', 1, 'Fund for test');

        return $user;
    }

    public function test_booking_preserves_policy_snapshot_and_prevents_retroactive_terms(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);

        $org = Organization::create([
            'name' => 'Policy Org',
            'slug' => 'policy-org',
            'status' => 'active',
            'partner_status' => 'approved',
            'settings' => ['cancellation_grace_hours' => 2],
        ]);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);

        $plan = PlatformPlan::create(['code' => 'P1', 'name' => 'Plan', 'price' => 100000, 'billing_cycle' => 'monthly', 'is_active' => true]);
        PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Policy Venue',
            'slug' => 'policy-venue',
            'address' => 'Policy Address',
            'status' => 'published',
            'is_active' => true,
            'approved_public_snapshot' => ['venue' => ['name' => 'Policy Venue']],
        ]);

        $court = Court::create(['venue_id' => $venue->id, 'sport_type' => 'badminton', 'name' => 'Court P', 'slug' => 'court-p', 'is_active' => true]);
        $this->priceCourtWith($court, [['10:00', '11:00', 100000, [now()->addDays(2)->dayOfWeekIso]]]);

        $customer = $this->createCustomerWithWallet($org->id, 500000);
        Sanctum::actingAs($customer);

        // 1. Create booking (slots 2 days in future at 10:00)
        $slotTime = now()->addDays(2)->setTime(10, 0, 0)->toIso8601String();
        $bookRes = $this->withHeader('Idempotency-Key', 'policy-snapshot-checkout')->postJson('/api/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$slotTime],
            'expected_total' => 100000,
        ]);
        $bookRes->assertStatus(201);

        $booking = Reservation::findOrFail($bookRes->json('reservation.id'));

        // Verify the new reservation carries no obsolete cancellation fields; a wallet checkout
        // captures no venue booking policy (only direct-owner checkout does).
        $this->assertArrayNotHasKey('cancellation_grace_hours', $booking->metadata ?? []);
        $this->assertArrayNotHasKey('cancellation_grace_hours', $booking->metadata['booking_policy_snapshot'] ?? []);
        $this->assertNotNull($booking->slots()->firstOrFail()->snapshot);

        // 2. Pre-existing historical booking with captured cancellation grace hours
        $historicalBooking = Booking::create([
            'code' => 'BK-HISTORICAL-01',
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'total_amount' => 100000,
            'status' => 'confirmed',
            'policy_snapshot' => ['cancellation_grace_hours' => 2, 'policy_version' => 'legacy'],
            'price_snapshot' => ['base_price' => 100000],
        ]);
        $historicalBooking->slots()->create([
            'court_id' => $court->id,
            'starts_at' => now()->addDays(3)->setTime(10, 0),
            'ends_at' => now()->addDays(3)->setTime(11, 0),
            'unit_price' => 100000,
            'status' => 'confirmed',
        ]);

        app(LegacyBookingBackfillService::class)->backfillBooking($historicalBooking);

        // 3. Invariants: Bookings remain confirmed, wallet balance untouched, snapshots preserved
        $booking->refresh();
        $this->assertEquals('confirmed', $booking->status);
        $this->assertDatabaseMissing('owner_refund_obligations', ['reservation_id' => $booking->id]);

        $historicalBooking->refresh();
        $this->assertEquals('confirmed', $historicalBooking->status);
        $this->assertEquals(2, $historicalBooking->policy_snapshot['cancellation_grace_hours']);
        $this->assertDatabaseMissing('refunds', ['booking_id' => $historicalBooking->id]);

        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($customer->id, $org->id);
        $this->assertEquals(400000, (int) $wallet->balance);

        // Historical read succeeds for both; the legacy booking through its mapped reservation
        $this->getJson("/api/reservations/{$booking->code}")->assertOk()->assertJsonPath('reservation.code', $booking->code);
        $this->getJson('/api/reservations/LEGACY-'.$historicalBooking->code)->assertOk()
            ->assertJsonPath('reservation.legacy_mapping.0.legacy_code', $historicalBooking->code);
    }

    public function test_existing_booking_is_preserved_and_new_checkout_rejected_after_venue_suspension(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);

        $org = Organization::create([
            'name' => 'Sus Org',
            'slug' => 'sus-org',
            'status' => 'active',
            'partner_status' => 'approved',
            'settings' => ['cancellation_grace_hours' => 2],
        ]);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);

        $plan = PlatformPlan::create(['code' => 'P2', 'name' => 'Plan', 'price' => 100000, 'billing_cycle' => 'monthly', 'is_active' => true]);
        PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Sus Venue',
            'slug' => 'sus-venue',
            'address' => 'Sus Address',
            'status' => 'published',
            'is_active' => true,
            'approved_public_snapshot' => ['venue' => ['name' => 'Sus Venue']],
        ]);

        $court = Court::create(['venue_id' => $venue->id, 'sport_type' => 'badminton', 'name' => 'Court S', 'slug' => 'court-s', 'is_active' => true]);
        $this->priceCourtWith($court, [['10:00', '11:00', 100000, [now()->addDays(2)->dayOfWeekIso]]]);

        $customer = $this->createCustomerWithWallet($org->id, 500000);
        Sanctum::actingAs($customer);

        $slotTime = now()->addDays(2)->setTime(10, 0, 0)->toIso8601String();
        $bookRes = $this->withHeader('Idempotency-Key', 'suspension-checkout-1')->postJson('/api/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$slotTime],
            'expected_total' => 100000,
        ]);
        $bookRes->assertStatus(201);
        $booking = Reservation::findOrFail($bookRes->json('reservation.id'));

        // Venue gets suspended!
        $venue->update(['status' => 'suspended', 'is_active' => false]);

        // New checkout on the suspended venue is rejected
        $this->withHeader('Idempotency-Key', 'suspension-checkout-2')->postJson('/api/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [now()->addDays(2)->setTime(11, 0, 0)->toIso8601String()],
            'expected_total' => 100000,
        ])->assertNotFound()->assertJsonPath('message', 'Sân không tồn tại hoặc đã ngừng hoạt động.');
        $this->assertSame(1, Reservation::count());

        $booking->refresh();
        $this->assertEquals('confirmed', $booking->status);
        $this->assertDatabaseMissing('owner_refund_obligations', ['reservation_id' => $booking->id]);

        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($customer->id, $org->id);
        $this->assertEquals(400000, (int) $wallet->balance);

        // Historical read succeeds
        $this->getJson("/api/reservations/{$booking->code}")->assertOk()->assertJsonPath('reservation.code', $booking->code);
    }
}
