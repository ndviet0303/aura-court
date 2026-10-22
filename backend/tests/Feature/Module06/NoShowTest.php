<?php

namespace Tests\Feature\Module06;

use App\Models\Court;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Module06\Concerns\OperatesBookings;
use Tests\TestCase;

/** UC-6.4, FR-107, BR-130, BR-133, BR-135: a no-show once the booked time has ended, with no refund. */
class NoShowTest extends TestCase
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

    public function test_fr_107_br_135_no_show_from_the_booked_end_with_its_own_time_and_no_check_in(): void
    {
        $booking = $this->booking($this->court, $this->at('10:00'));

        $this->travelTo($this->at('10:59:59'));
        $this->attend($this->staff, 'no-show', $booking)->assertStatus(422)->assertJsonPath('code', 'NO_SHOW_TOO_EARLY');

        $this->travelTo($this->at('11:00'));
        $this->attend($this->staff, 'no-show', $booking)
            ->assertOk()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('reservation.operational_status', 'no_show')
            ->assertJsonPath('reservation.attendance.no_show_by', $this->staff->name)
            ->assertJsonPath('reservation.attendance.no_show_at', now()->toIso8601String())
            ->assertJsonPath('reservation.attendance.checked_in_at', null);

        $this->assertDatabaseHas('booking_attendances', [
            'reservation_id' => $booking->id, 'status' => 'no_show', 'no_show_by' => $this->staff->id,
            'checked_in_at' => null, 'checked_in_by' => null,
        ]);
        $fresh = $booking->fresh();
        $this->assertSame('confirmed', $fresh->status);
        $this->assertSame(100000, (int) $fresh->total_amount);
        $this->assertSame('no_show', $fresh->metadata['operational_status']);
    }

    public function test_br_133_a_checked_in_booking_is_never_a_no_show(): void
    {
        $inUse = $this->booking($this->court, $this->at('10:00'));
        $completed = $this->booking($this->court, $this->at('11:00'));
        $this->travelTo($this->at('10:55'));
        $this->attend($this->staff, 'check-in', $inUse)->assertCreated();
        $this->attend($this->staff, 'check-in', $completed)->assertCreated();
        $this->attend($this->staff, 'check-out', $completed)->assertOk();

        $this->travelTo($this->at('12:30'));
        foreach ([$inUse, $completed] as $booking) {
            $this->attend($this->staff, 'no-show', $booking)->assertStatus(422)->assertJsonPath('code', 'ALREADY_CHECKED_IN');
        }
    }

    public function test_br_130_a_repeated_no_show_keeps_the_first_record_and_check_in_is_refused(): void
    {
        $booking = $this->booking($this->court, $this->at('10:00'));
        $this->travelTo($this->at('11:00'));
        $this->attend($this->staff, 'no-show', $booking)->assertOk();

        $this->travelTo($this->at('11:20'));
        $this->attend($this->ownerOf($this->court->venue->organization), 'no-show', $booking)
            ->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('reservation.attendance.no_show_by', $this->staff->name)
            ->assertJsonPath('reservation.attendance.no_show_at', $this->at('11:00')->toIso8601String());
        $this->attend($this->staff, 'check-in', $booking)->assertStatus(422)->assertJsonPath('code', 'ALREADY_NO_SHOW');
    }
}
