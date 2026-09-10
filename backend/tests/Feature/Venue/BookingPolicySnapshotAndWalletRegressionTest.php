<?php

namespace Tests\Feature\Venue;

use App\Domain\Booking\Actions\CancelBooking;
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
        $org->update(['settings' => ['cancellation_grace_hours' => 48]]);
        // Verify policy snapshot was captured!
        $this->assertNotNull($booking->policy_snapshot);
        $this->assertEquals(2, $booking->policy_snapshot['cancellation_grace_hours']);
        $this->assertNotNull($booking->price_snapshot);

        // 2. Operator changes organization policy to 48 hours (retroactive attempt)
        $org->update(['cancellation_grace_hours' => 48]);

        // 3. Customer cancels booking (currently ~48 hours before slot, or simulate cancellation within 3 hours)
        // With captured snapshot grace of 2 hours, cancellation at 3 hours before start time must succeed!
        $cancelAction = app(CancelBooking::class);
        $cancelled = $cancelAction->execute($booking, 'Thay đổi lịch cá nhân');

        $this->assertEquals('refunded', $cancelled->status);

        // Verify instant wallet refund credited back
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($customer->id, $org->id);
        $this->assertEquals(500000, (int) $wallet->balance);
    }

    public function test_existing_booking_remains_refundable_after_venue_suspension(): void
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

        // Customer cancels existing booking: must succeed and refund!
        $cancelAction = app(CancelBooking::class);
        $cancelled = $cancelAction->execute($booking, 'Sân bị ngưng');

        $this->assertEquals('refunded', $cancelled->status);

        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($customer->id, $org->id);
        $this->assertEquals(500000, (int) $wallet->balance);
    }
}
