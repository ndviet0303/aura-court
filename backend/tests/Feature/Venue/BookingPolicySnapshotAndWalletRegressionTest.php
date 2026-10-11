<?php

namespace Tests\Feature\Venue;

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\SportZone;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\WalletPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingPolicySnapshotAndWalletRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomerWithWallet(int $orgId, int $balance = 500000): User
    {
        $user = User::factory()->create(['role' => 'customer']);
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

        $sportZone = SportZone::create(['venue_id' => $venue->id, 'name' => 'Zone 1', 'sport_type' => 'badminton', 'is_active' => true]);
        $court = Court::create(['venue_id' => $venue->id, 'sport_zone_id' => $sportZone->id, 'name' => 'Court P', 'slug' => 'court-p', 'is_active' => true]);
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => now()->addDays(2)->dayOfWeekIso,
            'starts_at' => '10:00',
            'ends_at' => '11:00',
            'price' => 100000,
            'is_active' => true,
        ]);

        $customer = $this->createCustomerWithWallet($org->id, 500000);
        Sanctum::actingAs($customer);

        // 1. Create booking (slots 2 days in future at 10:00)
        $slotTime = now()->addDays(2)->setTime(10, 0, 0)->toIso8601String();
        $bookRes = $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$slotTime],
        ]);
        $bookRes->assertStatus(201);

        $bookingId = $bookRes->json('booking.id');
        $booking = Booking::findOrFail($bookingId);

        // Verify new policy snapshot was captured without obsolete cancellation fields
        $this->assertNotNull($booking->policy_snapshot);
        $this->assertArrayNotHasKey('cancellation_grace_hours', $booking->policy_snapshot);
        $this->assertNotNull($booking->price_snapshot);

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

        // 3. Customer attempts to cancel either booking: routes are retired and return 404
        $this->postJson("/api/v1/bookings/{$booking->code}/cancel", ['reason' => 'Thay đổi lịch cá nhân'])->assertNotFound();
        $this->postJson("/api/v1/bookings/{$historicalBooking->code}/cancel", ['reason' => 'Thay đổi lịch cá nhân'])->assertNotFound();

        // 4. Invariants: Bookings remain confirmed, wallet balance untouched, snapshots preserved
        $booking->refresh();
        $this->assertEquals('confirmed', $booking->status);
        $this->assertDatabaseMissing('refunds', ['booking_id' => $booking->id]);

        $historicalBooking->refresh();
        $this->assertEquals('confirmed', $historicalBooking->status);
        $this->assertEquals(2, $historicalBooking->policy_snapshot['cancellation_grace_hours']);
        $this->assertDatabaseMissing('refunds', ['booking_id' => $historicalBooking->id]);

        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($customer->id, $org->id);
        $this->assertEquals(400000, (int) $wallet->balance);

        // Historical read succeeds for both
        $this->getJson("/api/v1/bookings/{$booking->code}")->assertOk()->assertJsonPath('code', $booking->code);
        $this->getJson("/api/v1/bookings/{$historicalBooking->code}")->assertOk()->assertJsonPath('code', $historicalBooking->code);
    }

    public function test_existing_booking_preserves_snapshot_and_rejects_retired_commands_after_venue_suspension(): void
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

        $sportZone = SportZone::create(['venue_id' => $venue->id, 'name' => 'Zone 1', 'sport_type' => 'badminton', 'is_active' => true]);
        $court = Court::create(['venue_id' => $venue->id, 'sport_zone_id' => $sportZone->id, 'name' => 'Court S', 'slug' => 'court-s', 'is_active' => true]);
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => now()->addDays(2)->dayOfWeekIso,
            'starts_at' => '10:00',
            'ends_at' => '11:00',
            'price' => 100000,
            'is_active' => true,
        ]);

        $customer = $this->createCustomerWithWallet($org->id, 500000);
        Sanctum::actingAs($customer);

        $slotTime = now()->addDays(2)->setTime(10, 0, 0)->toIso8601String();
        $bookRes = $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$slotTime],
        ]);
        $bookRes->assertStatus(201);
        $booking = Booking::findOrFail($bookRes->json('booking.id'));

        // Venue gets suspended!
        $venue->update(['status' => 'suspended', 'is_active' => false]);

        // Customer attempts to cancel existing booking: returns 404
        $cancelRes = $this->postJson("/api/v1/bookings/{$booking->code}/cancel", [
            'reason' => 'Sân bị ngưng',
        ]);
        $cancelRes->assertNotFound();

        $booking->refresh();
        $this->assertEquals('confirmed', $booking->status);
        $this->assertDatabaseMissing('refunds', ['booking_id' => $booking->id]);

        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($customer->id, $org->id);
        $this->assertEquals(400000, (int) $wallet->balance);

        // Historical read succeeds
        $this->getJson("/api/v1/bookings/{$booking->code}")->assertOk()->assertJsonPath('code', $booking->code);
    }
}
