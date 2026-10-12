<?php

namespace Tests\Feature\Infrastructure;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Services\Reservation\LegacyBookingBackfillService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ReservationReferenceMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_up_links_reviews_and_disputes_of_mapped_bookings_and_down_restores_booking_ids(): void
    {
        [$booking, $court, $user] = $this->mappedBooking();
        $reservationId = (int) $booking->legacyMapping()->value('reservation_id');
        $migration = $this->migration();

        $migration->down();
        DB::table('reviews')->insert(['user_id' => $user->id, 'court_id' => $court->id, 'booking_id' => $booking->id, 'rating' => 5, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('dispute_cases')->insert(['dispute_code' => 'DSP-MIG-1', 'booking_id' => $booking->id, 'dispute_type' => 'billing', 'title' => 'Charge', 'summary' => 'Charged twice', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $migration->up();

        $this->assertSame($reservationId, (int) DB::table('reviews')->value('reservation_id'));
        $this->assertSame($reservationId, (int) DB::table('dispute_cases')->value('reservation_id'));

        DB::table('reviews')->update(['booking_id' => null]);
        $migration->down();

        $this->assertSame($booking->id, (int) DB::table('reviews')->value('booking_id'));
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('reviews', 'reservation_id'));
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('dispute_cases', 'reservation_id'));
    }

    public function test_down_refuses_to_drop_reviews_of_reservations_without_a_legacy_booking(): void
    {
        [, $court, $user] = $this->mappedBooking();
        $reservation = Reservation::withoutGlobalScope('tenant_isolation')->firstOrFail();
        DB::table('legacy_booking_mappings')->delete();
        DB::table('reviews')->insert(['user_id' => $user->id, 'court_id' => $court->id, 'reservation_id' => $reservation->id, 'rating' => 4, 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(RuntimeException::class);
        $this->migration()->down();
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_11_000011_add_reservation_id_to_reviews_and_dispute_cases.php');
    }

    /**
     * @return array{0: Booking, 1: Court, 2: User}
     */
    private function mappedBooking(): array
    {
        $org = Organization::create(['name' => 'Org Mig', 'slug' => 'org-mig-'.uniqid()]);
        $venue = Venue::create(['organization_id' => $org->id, 'name' => 'Mig Venue', 'slug' => 'mig-'.uniqid()]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Court 1']);
        $user = User::factory()->create();
        $booking = Booking::create(['code' => 'MIG-'.uniqid(), 'user_id' => $user->id, 'court_id' => $court->id, 'total_amount' => 100000, 'status' => 'confirmed']);
        BookingSlot::create([
            'booking_id' => $booking->id,
            'court_id' => $court->id,
            'starts_at' => now()->subDay()->setTime(18, 0),
            'ends_at' => now()->subDay()->setTime(19, 0),
            'unit_price' => 100000,
            'status' => 'confirmed',
        ]);
        app(LegacyBookingBackfillService::class)->backfillBooking($booking);

        return [$booking, $court, $user];
    }
}
