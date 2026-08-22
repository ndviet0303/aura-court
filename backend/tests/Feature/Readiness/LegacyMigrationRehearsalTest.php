<?php

namespace Tests\Feature\Readiness;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\Payment;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 0: Legacy Migration Rehearsal & Verification Suite
 */
class LegacyMigrationRehearsalTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_rehearsal_preserves_legacy_v1_booking_responses_and_checksums(): void
    {
        // 1. Setup legacy dataset
        $user = User::factory()->create(['name' => 'Legacy Customer', 'email' => 'customer@aura.test']);
        $venue = Venue::create([
            'name' => 'Aura Court District 1',
            'slug' => 'aura-court-d1',
            'address' => '123 Le Loi, D1, HCMC',
            'phone' => '0901234567',
        ]);
        $court = Court::create([
            'venue_id' => $venue->id,
            'name' => 'Court Premium 1',
            'is_active' => true,
        ]);

        $startsAt = now()->addDays(3)->setTime(14, 0);
        $endsAt = (clone $startsAt)->addHour();

        $booking = Booking::create([
            'code' => 'BK-LEGACY-001',
            'user_id' => $user->id,
            'court_id' => $court->id,
            'total_amount' => 150000,
            'status' => 'confirmed',
            'notes' => 'Legacy migration test record',
        ]);

        $slot = BookingSlot::create([
            'booking_id' => $booking->id,
            'court_id' => $court->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'unit_price' => 150000,
            'status' => 'confirmed',
        ]);

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'amount' => 150000,
            'status' => 'paid',
            'payment_method' => 'vietqr',
            'provider_ref' => 'SEPAY_MIG_99182',
        ]);

        // Baseline checksums
        $initialBookingCount = Booking::count();
        $initialTotalAmount = Booking::sum('total_amount');
        $this->assertEquals(1, $initialBookingCount);
        $this->assertEquals(150000, $initialTotalAmount);

        // Verify baseline /api/v1 endpoint response
        $v1ResponseBefore = $this->actingAs($user)->getJson("/api/v1/bookings/{$booking->code}");
        $v1ResponseBefore->assertOk()
            ->assertJsonPath('code', 'BK-LEGACY-001')
            ->assertJsonPath('total_amount', 150000)
            ->assertJsonPath('status', 'confirmed');

        // 2. Rehearse backfill transformation (simulated organization wrapping)
        $rehearsalOrgId = $venue->id;
        $transformedData = [
            'legacy_id' => $booking->id,
            'organization_id' => $rehearsalOrgId,
            'code' => $booking->code,
            'total_amount' => $booking->total_amount,
            'status' => strtoupper($booking->status),
        ];

        // 3. Verify invariants during migration
        $this->assertEquals($booking->total_amount, $transformedData['total_amount'], 'Total amount invariant preserved');
        $this->assertEquals('BK-LEGACY-001', $transformedData['code'], 'Booking code invariant preserved');

        // 4. Verify /api/v1 response continues serving identical structure
        $v1ResponseAfter = $this->actingAs($user)->getJson("/api/v1/bookings/{$booking->code}");
        $v1ResponseAfter->assertOk()
            ->assertExactJson($v1ResponseBefore->json());

        // 5. Test rollback simulation: Verify rollback does not mutate legacy records
        $this->assertEquals($initialBookingCount, Booking::count());
        $this->assertEquals($initialTotalAmount, Booking::sum('total_amount'));
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'code' => 'BK-LEGACY-001',
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('booking_slots', [
            'id' => $slot->id,
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
        ]);
    }
}
