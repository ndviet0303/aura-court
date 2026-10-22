<?php

namespace Tests\Feature\Module06;

use App\Models\BookingAttendance;
use App\Models\Court;
use App\Models\OperationalStop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\RecordsOwnerPayments;
use Tests\Feature\Module06\Concerns\OperatesBookings;
use Tests\TestCase;

/** UC-6.2, FR-103..FR-105, BR-119, BR-123..BR-127, BR-130, BR-132: check-in by a scanned or typed booking code. */
class CheckInTest extends TestCase
{
    use OperatesBookings, RecordsOwnerPayments {
        OperatesBookings::booking insteadof RecordsOwnerPayments;
    }
    use RefreshDatabase;

    private Court $court;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo($this->at('08:00'));
        $this->court = $this->bookableCourt();
        $this->staff = $this->staffOf($this->court->venue);
    }

    public function test_fr_103_check_in_records_its_time_and_actor_and_the_booking_stays_confirmed(): void
    {
        $booking = $this->booking($this->court, $this->at('10:00'));
        $this->travelTo($this->at('10:05'));

        $this->attend($this->staff, 'check-in', $booking)
            ->assertCreated()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('reservation.status', 'confirmed')
            ->assertJsonPath('reservation.operational_status', 'in_use')
            ->assertJsonPath('reservation.attendance.checked_in_by', $this->staff->name)
            ->assertJsonPath('reservation.attendance.checked_in_at', now()->toIso8601String());

        $this->assertDatabaseHas('booking_attendances', [
            'reservation_id' => $booking->id, 'status' => 'checked_in', 'checked_in_by' => $this->staff->id, 'no_show_at' => null,
        ]);
        $fresh = $booking->fresh();
        $this->assertSame('confirmed', $fresh->status);
        $this->assertSame('in_use', $fresh->metadata['operational_status']);
    }

    public function test_br_126_check_in_opens_ten_minutes_before_the_start(): void
    {
        $booking = $this->booking($this->court, $this->at('10:00'));

        $this->travelTo($this->at('09:49:59'));
        $this->attend($this->staff, 'check-in', $booking)->assertStatus(422)->assertJsonPath('code', 'CHECK_IN_TOO_EARLY');
        $this->assertDatabaseMissing('booking_attendances', ['reservation_id' => $booking->id]);

        $this->travelTo($this->at('09:50'));
        $this->attend($this->staff, 'check-in', $booking)->assertCreated();
    }

    public function test_br_126_a_midnight_booking_checks_in_from_23_50_the_day_before(): void
    {
        $booking = $this->booking($this->court, $this->at('00:00', '2026-11-04'));
        $this->travelTo($this->at('23:50'));

        $this->attend($this->staff, 'check-in', $booking)->assertCreated();
    }

    public function test_br_127_check_in_closes_at_the_booked_end(): void
    {
        $lastSecond = $this->booking($this->court, $this->at('10:00'));
        $ended = $this->booking($this->court, $this->at('12:00'));

        $this->travelTo($this->at('10:59:59'));
        $this->attend($this->staff, 'check-in', $lastSecond)->assertCreated();

        $this->travelTo($this->at('13:00'));
        $this->attend($this->staff, 'check-in', $ended)->assertStatus(422)->assertJsonPath('code', 'CHECK_IN_WINDOW_CLOSED');
    }

    public function test_br_132_check_in_needs_the_court_in_service_not_free(): void
    {
        $first = $this->booking($this->court, $this->at('10:00'));
        $next = $this->booking($this->court, $this->at('11:00'));
        $paused = $this->booking($this->court, $this->at('12:00'));

        $this->travelTo($this->at('10:00'));
        $this->attend($this->staff, 'check-in', $first)->assertCreated();
        $this->travelTo($this->at('10:55'));
        $this->attend($this->staff, 'check-in', $next)->assertCreated();

        $this->court->update(['status' => Court::STATUS_PAUSED]);
        $this->travelTo($this->at('12:00'));
        $this->attend($this->staff, 'check-in', $paused)->assertStatus(422)->assertJsonPath('code', 'COURT_OUT_OF_SERVICE');
    }

    public function test_br_132_a_started_venue_stop_blocks_check_in_until_it_ends(): void
    {
        $booking = $this->booking($this->court, $this->at('10:00'));
        $stop = OperationalStop::create([
            'venue_id' => $this->court->venue_id, 'kind' => 'closure', 'reason' => 'Mất điện toàn khu',
            'starts_at' => $this->at('10:00'), 'created_by' => $this->staff->id,
        ]);

        $this->travelTo($this->at('10:00'));
        $this->attend($this->staff, 'check-in', $booking)->assertStatus(422)->assertJsonPath('code', 'COURT_OUT_OF_SERVICE');

        $stop->update(['ended_at' => $this->at('10:20')]);
        $this->travelTo($this->at('10:20'));
        $this->attend($this->staff, 'check-in', $booking)->assertCreated();
    }

    public function test_br_123_the_scanned_prefix_and_a_typed_lowercase_code_find_the_booking(): void
    {
        $scanned = $this->booking($this->court, $this->at('10:00'));
        $typed = $this->booking($this->court, $this->at('11:00'));
        $this->travelTo($this->at('10:55'));

        $this->attend($this->staff, 'check-in', $scanned, ' AURA_BOOKING_'.$scanned->code.' ')
            ->assertCreated()->assertJsonPath('reservation.code', $scanned->code);
        $this->attend($this->staff, 'check-in', $typed, Str::lower($typed->code))
            ->assertCreated()->assertJsonPath('reservation.code', $typed->code);
    }

    public function test_br_123_a_payment_qr_code_is_not_a_booking_code(): void
    {
        $booking = $this->booking($this->court, $this->at('10:00'));
        $this->receipt($this->court->venue, 100000, $this->at('08:00'), $booking);
        $paymentCode = (string) DB::table('owner_payment_attempts')->where('purpose_id', $booking->code)->value('reference');
        $this->travelTo($this->at('10:00'));

        $this->attend($this->staff, 'check-in', $booking, $paymentCode)->assertNotFound()->assertJsonPath('code', 'BOOKING_NOT_FOUND');
        $this->assertSame(0, BookingAttendance::count());
    }

    public function test_br_130_a_repeated_check_in_keeps_the_first_time_and_actor(): void
    {
        $booking = $this->booking($this->court, $this->at('10:00'));
        $this->travelTo($this->at('10:00'));
        $this->attend($this->staff, 'check-in', $booking)->assertCreated();

        $this->travelTo($this->at('10:10'));
        $this->attend($this->ownerOf($this->court->venue->organization), 'check-in', $booking)
            ->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('reservation.attendance.checked_in_by', $this->staff->name)
            ->assertJsonPath('reservation.attendance.checked_in_at', $this->at('10:00')->toIso8601String());
        $this->assertSame(1, BookingAttendance::count());
    }

    public function test_br_125_only_a_confirmed_booking_checks_in(): void
    {
        $held = $this->booking($this->court, $this->at('10:00'), status: 'held');
        $cancelled = $this->booking($this->court, $this->at('11:00'), status: 'cancelled');
        $this->travelTo($this->at('10:55'));

        foreach ([$held, $cancelled] as $booking) {
            $this->attend($this->staff, 'check-in', $booking)->assertStatus(422)->assertJsonPath('code', 'BOOKING_NOT_CONFIRMED');
        }
        $this->assertSame(0, BookingAttendance::count());
    }

    public function test_br_119_staff_operate_only_their_venues_and_owners_only_their_organization(): void
    {
        $booking = $this->booking($this->court, $this->at('10:00'));
        $otherVenueStaff = $this->staffOf($this->openVenue(['organization_id' => $this->court->venue->organization_id]));
        $elsewhere = $this->openVenue();
        $this->travelTo($this->at('10:00'));

        $this->attend($otherVenueStaff, 'check-in', $booking)->assertForbidden()->assertJsonPath('code', 'VENUE_NOT_ASSIGNED');
        $this->actingAs($this->ownerOf($elsewhere->organization))
            ->postJson('/api/attendance/check-in', ['code' => $booking->code], [
                'X-Organization-ID' => (string) $elsewhere->organization_id,
                'Idempotency-Key' => (string) Str::uuid(),
            ])
            ->assertNotFound()->assertJsonPath('code', 'BOOKING_NOT_FOUND');
        $this->assertSame(0, BookingAttendance::count());
    }
}
