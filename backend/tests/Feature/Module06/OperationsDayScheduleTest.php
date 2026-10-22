<?php

namespace Tests\Feature\Module06;

use App\Models\Court;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Module06\Concerns\OperatesBookings;
use Tests\TestCase;

/** UC-6.1, FR-101, BR-121: a venue's day by court, with each booking's operational status beside its booked time. */
class OperationsDayScheduleTest extends TestCase
{
    use OperatesBookings;
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

    public function test_fr_101_the_day_lists_the_venue_courts_by_code_and_its_confirmed_bookings_with_their_status(): void
    {
        $this->court->update(['code' => 'S02']);
        $first = $this->activeCourt($this->court->venue, ['code' => 'S01']);
        $waiting = $this->booking($this->court, $this->at('10:00'));
        $playing = $this->booking($first, $this->at('09:00'));
        $this->booking($this->court, $this->at('12:00'), status: 'held');
        $this->booking($this->court, $this->at('14:00'), status: 'cancelled');
        $this->booking($this->activeCourt($this->openVenue(['organization_id' => $this->court->venue->organization_id])), $this->at('10:00'));
        $this->travelTo($this->at('09:00'));
        $this->attend($this->staff, 'check-in', $playing)->assertCreated();

        $this->day('2026-11-03')
            ->assertOk()
            ->assertJsonPath('date', '2026-11-03')
            ->assertJsonPath('venue.id', $this->court->venue_id)
            ->assertJsonPath('courts.*.code', ['S01', 'S02'])
            ->assertJsonPath('reservations.*.code', [$playing->code, $waiting->code])
            ->assertJsonPath('reservations.*.operational_status', ['in_use', 'confirmed'])
            ->assertJsonPath('reservations.0.court.code', 'S01');
    }

    public function test_fr_101_the_day_is_the_venue_calendar_day_from_midnight_to_midnight(): void
    {
        $this->booking($this->court, $this->at('23:00', '2026-11-02'));
        $midnight = $this->booking($this->court, $this->at('00:00'));
        $late = $this->booking($this->court, $this->at('23:00'));
        $this->booking($this->court, $this->at('00:00', '2026-11-04'));

        $this->day('2026-11-03')->assertOk()->assertJsonPath('reservations.*.code', [$midnight->code, $late->code]);
        $this->day('3/11/2026')->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->day('')->assertUnprocessable()->assertJsonValidationErrors('date');
    }

    public function test_br_121_a_late_check_out_never_stretches_the_booked_time(): void
    {
        $booking = $this->booking($this->court, $this->at('10:00'));
        $this->travelTo($this->at('10:00'));
        $this->attend($this->staff, 'check-in', $booking)->assertCreated();

        $this->travelTo($this->at('11:20'));
        $this->day('2026-11-03')
            ->assertJsonPath('reservations.0.operational_status', 'in_use')
            ->assertJsonPath('reservations.0.usage_flag', 'overdue')
            ->assertJsonPath('reservations.0.ends_at', $this->at('11:00')->utc()->toIso8601String());

        $this->attend($this->staff, 'check-out', $booking)->assertOk();
        $this->day('2026-11-03')
            ->assertJsonPath('reservations.0.operational_status', 'completed')
            ->assertJsonPath('reservations.0.usage_flag', null)
            ->assertJsonPath('reservations.0.starts_at', $this->at('10:00')->utc()->toIso8601String())
            ->assertJsonPath('reservations.0.ends_at', $this->at('11:00')->utc()->toIso8601String())
            ->assertJsonPath('reservations.0.attendance.checked_out_at', $this->at('11:20')->toIso8601String());
    }

    private function day(string $date): TestResponse
    {
        return $this->actingAs($this->staff)->getJson(
            "/api/operations/venues/{$this->court->venue_id}/day?date={$date}",
            ['X-Organization-ID' => (string) $this->court->venue->organization_id],
        );
    }
}
