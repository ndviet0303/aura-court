<?php

namespace Tests\Feature\Module06;

use App\Models\Court;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Module06\Concerns\OperatesBookings;
use Tests\TestCase;

/** UC-6.3, FR-106, BR-128..BR-130: check-out of a checked-in booking. */
class CheckOutTest extends TestCase
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

    public function test_fr_106_check_out_completes_the_booking_with_its_time_and_actor(): void
    {
        $booking = $this->booking($this->court, $this->at('10:00'));
        $this->travelTo($this->at('10:00'));
        $this->attend($this->staff, 'check-in', $booking)->assertCreated();

        $this->travelTo($this->at('10:58'));
        $this->attend($this->staff, 'check-out', $booking)
            ->assertOk()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('reservation.status', 'confirmed')
            ->assertJsonPath('reservation.operational_status', 'completed')
            ->assertJsonPath('reservation.attendance.checked_in_at', $this->at('10:00')->toIso8601String())
            ->assertJsonPath('reservation.attendance.checked_out_by', $this->staff->name)
            ->assertJsonPath('reservation.attendance.checked_out_at', now()->toIso8601String());

        $this->assertDatabaseHas('booking_attendances', ['reservation_id' => $booking->id, 'status' => 'checked_out', 'checked_out_by' => $this->staff->id]);
        $this->assertSame('completed', $booking->fresh()->metadata['operational_status']);
    }

    public function test_br_128_a_late_check_out_keeps_the_real_action_time(): void
    {
        $booking = $this->booking($this->court, $this->at('10:00'));
        $this->travelTo($this->at('10:00'));
        $this->attend($this->staff, 'check-in', $booking)->assertCreated();

        $this->travelTo($this->at('12:30'));
        $this->attend($this->staff, 'check-out', $booking)
            ->assertOk()->assertJsonPath('reservation.attendance.checked_out_at', $this->at('12:30')->toIso8601String());
    }

    public function test_br_129_an_early_check_out_changes_neither_the_amount_nor_the_booked_time(): void
    {
        $booking = $this->booking($this->court, $this->at('10:00'), hours: 2);
        $this->travelTo($this->at('10:00'));
        $this->attend($this->staff, 'check-in', $booking)->assertCreated();

        $this->travelTo($this->at('10:30'));
        $this->attend($this->staff, 'check-out', $booking)->assertOk();

        $fresh = $booking->fresh(['slots']);
        $this->assertSame(200000, (int) $fresh->total_amount);
        $this->assertCount(2, $fresh->slots);
        $this->assertTrue($fresh->ends_at->equalTo($this->at('12:00')));
    }

    public function test_br_128_check_out_needs_a_check_in(): void
    {
        $booking = $this->booking($this->court, $this->at('10:00'));
        $missed = $this->booking($this->court, $this->at('06:00'));
        $this->travelTo($this->at('10:30'));

        $this->attend($this->staff, 'check-out', $booking)->assertStatus(422)->assertJsonPath('code', 'NOT_CHECKED_IN');
        $this->attend($this->staff, 'no-show', $missed)->assertOk();
        $this->attend($this->staff, 'check-out', $missed)->assertStatus(422)->assertJsonPath('code', 'ALREADY_NO_SHOW');
    }

    public function test_br_130_repeats_after_completion_keep_the_first_record(): void
    {
        $booking = $this->booking($this->court, $this->at('10:00'));
        $this->travelTo($this->at('10:00'));
        $this->attend($this->staff, 'check-in', $booking)->assertCreated();
        $this->travelTo($this->at('10:50'));
        $this->attend($this->staff, 'check-out', $booking)->assertOk();

        $this->travelTo($this->at('10:55'));
        $this->attend($this->ownerOf($this->court->venue->organization), 'check-out', $booking)
            ->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('reservation.attendance.checked_out_by', $this->staff->name)
            ->assertJsonPath('reservation.attendance.checked_out_at', $this->at('10:50')->toIso8601String());
        $this->attend($this->staff, 'check-in', $booking)->assertStatus(422)->assertJsonPath('code', 'ALREADY_COMPLETED');
    }
}
