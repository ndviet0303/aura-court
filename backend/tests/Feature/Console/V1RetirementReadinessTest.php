<?php

namespace Tests\Feature\Console;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\LegacyBookingMapping;
use App\Models\Organization;
use App\Models\User;
use App\Models\V1AdoptionSnapshot;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Spec scenario "Historical booking without a mapping": reported with its reason, left unchanged, blocks retirement.
 */
class V1RetirementReadinessTest extends TestCase
{
    use RefreshDatabase;

    // The command resolves the path from the repository root (base_path('../')).
    private const APPROVED_CHANGE = 'backend/tests/Fixtures/approved-v1-retirement-change';

    public function test_unmappable_historical_bookings_are_reported_unchanged_and_block_retirement(): void
    {
        $court = $this->court();
        $ownerlessVenue = Venue::create(['organization_id' => null, 'name' => 'Ownerless Venue', 'slug' => 'ownerless-'.uniqid()]);
        $ownerlessCourt = Court::create(['venue_id' => $ownerlessVenue->id, 'name' => 'Ownerless Court']);
        $user = User::factory()->create();
        $mappable = $this->booking($court, $user, 'BK-MAPPABLE', withSlot: true);
        $zeroSlots = $this->booking($court, $user, 'BK-ZERO-SLOTS', withSlot: false);
        $ownerless = $this->booking($ownerlessCourt, $user, 'BK-NO-OWNER', withSlot: true);
        $unmappableIds = [$zeroSlots->id, $ownerless->id];
        $this->snapshot();
        $before = $this->rows($unmappableIds);

        $this->assertSame(1, Artisan::call('v2:backfill-legacy-bookings'));
        $backfill = json_decode(Artisan::output(), true);
        $this->assertSame(1, $backfill['created']);
        $this->assertSame(
            ['BK-ZERO-SLOTS' => 'Booking has zero slots', 'BK-NO-OWNER' => 'Missing venue or organization owner'],
            array_column($backfill['orphans'], 'reason', 'code'),
        );

        $this->assertSame($before, $this->rows($unmappableIds));
        $this->assertFalse(LegacyBookingMapping::whereIn('legacy_booking_id', $unmappableIds)->exists());
        $this->assertTrue(LegacyBookingMapping::where('legacy_booking_id', $mappable->id)->exists());

        $this->assertSame(1, Artisan::call('v1:retirement-readiness', ['--approved-change' => self::APPROVED_CHANGE]));
        $readiness = json_decode(Artisan::output(), true);
        $this->assertFalse($readiness['ready']);
        $this->assertTrue($readiness['approved_change_present']);
        $this->assertTrue($readiness['adoption_snapshot_present']);
        $this->assertFalse($readiness['legacy_mapping_valid']);
        $this->assertSame([
            ['legacy_booking_id' => $zeroSlots->id, 'code' => 'BK-ZERO-SLOTS', 'reason' => 'Booking has zero slots'],
            ['legacy_booking_id' => $ownerless->id, 'code' => 'BK-NO-OWNER', 'reason' => 'Missing venue or organization owner'],
        ], $readiness['unmapped_bookings']);
        $this->assertSame($before, $this->rows($unmappableIds));
    }

    public function test_readiness_reports_unbackfilled_bookings_and_passes_once_every_booking_is_mapped(): void
    {
        $booking = $this->booking($this->court(), User::factory()->create(), 'BK-PENDING', withSlot: true);
        $this->snapshot();

        $this->assertSame(1, Artisan::call('v1:retirement-readiness', ['--approved-change' => self::APPROVED_CHANGE]));
        $readiness = json_decode(Artisan::output(), true);
        $this->assertFalse($readiness['ready']);
        $this->assertSame(
            [['legacy_booking_id' => $booking->id, 'code' => 'BK-PENDING', 'reason' => 'No reservation mapping yet']],
            $readiness['unmapped_bookings'],
        );

        $this->assertSame(0, Artisan::call('v2:backfill-legacy-bookings'));

        $this->assertSame(0, Artisan::call('v1:retirement-readiness', ['--approved-change' => self::APPROVED_CHANGE]));
        $readiness = json_decode(Artisan::output(), true);
        $this->assertTrue($readiness['ready']);
        $this->assertTrue($readiness['legacy_mapping_valid']);
        $this->assertSame([], $readiness['unmapped_bookings']);
    }

    private function court(): Court
    {
        $org = Organization::create(['name' => 'Org Ready', 'slug' => 'org-ready-'.uniqid()]);
        $venue = Venue::create(['organization_id' => $org->id, 'name' => 'Ready Venue', 'slug' => 'ready-'.uniqid()]);

        return Court::create(['venue_id' => $venue->id, 'name' => 'Court 1']);
    }

    private function booking(Court $court, User $user, string $code, bool $withSlot): Booking
    {
        $booking = Booking::create(['code' => $code, 'user_id' => $user->id, 'court_id' => $court->id, 'total_amount' => 100000, 'status' => 'confirmed']);
        if ($withSlot) {
            BookingSlot::create([
                'booking_id' => $booking->id,
                'court_id' => $court->id,
                'starts_at' => now()->subDays(3)->setTime(18, 0),
                'ends_at' => now()->subDays(3)->setTime(19, 0),
                'unit_price' => 100000,
                'status' => 'confirmed',
            ]);
        }

        return $booking;
    }

    private function snapshot(): void
    {
        V1AdoptionSnapshot::create(['period_start' => now()->subDays(30)->toDateString(), 'period_end' => now()->toDateString()]);
    }

    /**
     * Every column of the bookings and their slots, to prove the rows were not touched.
     */
    private function rows(array $bookingIds): array
    {
        return [
            DB::table('bookings')->whereIn('id', $bookingIds)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            DB::table('booking_slots')->whereIn('booking_id', $bookingIds)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        ];
    }
}
