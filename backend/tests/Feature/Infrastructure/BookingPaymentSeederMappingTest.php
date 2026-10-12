<?php

namespace Tests\Feature\Infrastructure;

use App\Models\Booking;
use App\Models\LegacyBookingMapping;
use App\Models\Review;
use Database\Seeders\BookingPaymentSeeder;
use Database\Seeders\OrganizationVenueSeeder;
use Database\Seeders\SportZoneCourtSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPaymentSeederMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_bookings_are_mapped_and_reseeding_is_idempotent(): void
    {
        $this->seed([OrganizationVenueSeeder::class, UserSeeder::class, SportZoneCourtSeeder::class]);

        $this->seed(BookingPaymentSeeder::class);
        $mappings = LegacyBookingMapping::withoutGlobalScope('tenant_isolation')->orderBy('id')->pluck('reservation_id', 'legacy_booking_id')->all();
        $reviews = Review::count();
        $this->seed(BookingPaymentSeeder::class);

        $this->assertSame(72, Booking::count());
        $this->assertSame(Booking::orderBy('id')->pluck('id')->all(), array_keys($mappings));
        $this->assertSame($mappings, LegacyBookingMapping::withoutGlobalScope('tenant_isolation')->orderBy('id')->pluck('reservation_id', 'legacy_booking_id')->all());
        $this->assertGreaterThan(0, $reviews);
        $this->assertSame($reviews, Review::count());
        $this->assertSame(0, Review::whereNull('reservation_id')->orWhereNull('booking_id')->count());
    }
}
