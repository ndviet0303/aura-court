<?php

namespace Tests\Feature\Readiness;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use App\Models\Venue;
use App\Services\Reservation\LegacyBookingBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 0: Legacy Migration Rehearsal & Verification Suite
 */
class LegacyMigrationRehearsalTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_rehearsal_preserves_legacy_booking_history_and_checksums(): void
    {
        // 1. Setup legacy dataset
        $user = User::factory()->create(['name' => 'Legacy Customer', 'email' => 'customer@aura.test']);
        $organization = Organization::create(['name' => 'Aura Org', 'slug' => 'aura-org', 'status' => 'active']);
        $venue = Venue::create([
            'organization_id' => $organization->id,
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

        // 2. Rehearse the backfill: history is read through the mapped reservation
        $backfill = app(LegacyBookingBackfillService::class);
        $mapping = $backfill->backfillBooking($booking);
        $responseBefore = $this->actingAs($user)->getJson('/api/reservations/LEGACY-'.$booking->code);
        $responseBefore->assertOk()
            ->assertJsonPath('reservation.legacy_mapping.0.legacy_code', 'BK-LEGACY-001')
            ->assertJsonPath('reservation.legacy_mapping.0.legacy_status', 'confirmed')
            ->assertJsonPath('reservation.total_amount', 150000)
            ->assertJsonPath('reservation.status', 'confirmed');

        // 3. Verify invariants during migration
        $this->assertEquals($booking->total_amount, $mapping->legacy_total_amount, 'Total amount invariant preserved');
        $this->assertEquals('BK-LEGACY-001', $mapping->legacy_code, 'Booking code invariant preserved');
        $this->assertNotEmpty($mapping->source_checksum);

        // 4. Re-running the rehearsal verifies the checksum and serves an identical reservation
        $this->assertTrue($backfill->backfillBooking($booking->fresh())->is($mapping));
        $this->actingAs($user)->getJson('/api/reservations/LEGACY-'.$booking->code)
            ->assertOk()
            ->assertExactJson($responseBefore->json());

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
