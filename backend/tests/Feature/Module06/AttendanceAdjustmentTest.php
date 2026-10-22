<?php

namespace Tests\Feature\Module06;

use App\Models\AffectedReservationCase;
use App\Models\BookingAttendance;
use App\Models\BookingAttendanceAdjustment;
use App\Models\Court;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Module06\Concerns\OperatesBookings;
use Tests\TestCase;

/** UC-6.6, FR-108, FR-109, BR-134..BR-137: the owner corrects an operational record and the history keeps it. */
class AttendanceAdjustmentTest extends TestCase
{
    use OperatesBookings;
    use RefreshDatabase;

    private Court $court;

    private User $staff;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo($this->at('08:00'));
        $this->court = $this->bookableCourt();
        $this->staff = $this->staffOf($this->court->venue);
        $this->owner = $this->ownerOf($this->court->venue->organization);
    }

    public function test_fr_108_br_136_br_137_the_owner_turns_a_no_show_into_completed_and_the_history_keeps_both(): void
    {
        $booking = $this->booking($this->court, $this->at('10:00'));
        $this->travelTo($this->at('11:00'));
        $this->attend($this->staff, 'no-show', $booking)->assertOk();

        $this->travelTo($this->at('12:00'));
        $this->adjust($this->owner, $booking, 'completed', ['checked_in_at' => '2026-11-03T10:05:00+07:00', 'checked_out_at' => '2026-11-03T11:00:00+07:00'])
            ->assertCreated()
            ->assertJsonPath('reservation.operational_status', 'completed')
            ->assertJsonPath('reservation.attendance.checked_in_at', $this->at('10:05')->toIso8601String())
            ->assertJsonPath('reservation.attendance.checked_in_by', $this->owner->name)
            ->assertJsonPath('reservation.attendance.no_show_at', null)
            ->assertJsonPath('adjustment.before.status', 'no_show')
            ->assertJsonPath('adjustment.before.no_show_at', $this->at('11:00')->toIso8601String())
            ->assertJsonPath('adjustment.after.status', 'completed')
            ->assertJsonPath('adjustment.adjusted_by', $this->owner->name)
            ->assertJsonPath('adjustment.reason', 'Khách đã đến, nhân viên ghi nhầm');

        $this->assertDatabaseHas('booking_attendances', ['reservation_id' => $booking->id, 'status' => 'checked_out', 'no_show_at' => null, 'no_show_by' => null]);
        $fresh = $booking->fresh();
        $this->assertSame('confirmed', $fresh->status);
        $this->assertSame(100000, (int) $fresh->total_amount);
        $this->assertSame($booking->starts_at->toIso8601String(), $fresh->starts_at->toIso8601String());
        $this->assertSame('completed', $fresh->metadata['operational_status']);
        $this->history($this->owner, $booking)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.after.status', 'completed');
    }

    public function test_br_136_a_kept_time_keeps_its_actor_and_back_to_confirmed_clears_the_record(): void
    {
        $booking = $this->booking($this->court, $this->at('10:00'));
        $this->travelTo($this->at('10:00'));
        $this->attend($this->staff, 'check-in', $booking)->assertCreated();

        $this->travelTo($this->at('10:30'));
        $this->adjust($this->owner, $booking, 'completed', ['checked_in_at' => '2026-11-03T10:00:00+07:00', 'checked_out_at' => '2026-11-03T10:30:00+07:00'])
            ->assertCreated()
            ->assertJsonPath('reservation.attendance.checked_in_by', $this->staff->name)
            ->assertJsonPath('reservation.attendance.checked_out_by', $this->owner->name);
        $this->adjust($this->owner, $booking, 'confirmed')
            ->assertCreated()
            ->assertJsonPath('reservation.operational_status', 'confirmed')
            ->assertJsonPath('reservation.attendance', null)
            ->assertJsonPath('adjustment.before.status', 'completed');

        $this->assertDatabaseMissing('booking_attendances', ['reservation_id' => $booking->id]);
        $this->assertArrayNotHasKey('operational_status', $booking->fresh()->metadata ?? []);
        $this->history($this->owner, $booking)->assertJsonPath('data.*.after.status', ['confirmed', 'completed']);
        $this->adjust($this->owner, $booking, 'confirmed')->assertStatus(422)->assertJsonPath('code', 'ADJUSTMENT_NO_CHANGE');
        $this->assertSame(2, BookingAttendanceAdjustment::withoutGlobalScopes()->count());
    }

    public function test_br_134_only_the_owner_adjusts_and_reads_the_history(): void
    {
        $booking = $this->booking($this->court, $this->at('10:00'));
        $this->travelTo($this->at('11:00'));

        $this->adjust($this->staff, $booking, 'no_show')->assertForbidden();
        $this->history($this->staff, $booking)->assertForbidden();
        $elsewhere = $this->openVenue();
        $this->actingAs($this->ownerOf($elsewhere->organization))
            ->postJson('/api/attendance/adjustments', ['code' => $booking->code, 'status' => 'no_show', 'reason' => 'Sửa'], ['X-Organization-ID' => (string) $elsewhere->organization_id])
            ->assertNotFound()->assertJsonPath('code', 'BOOKING_NOT_FOUND');
        $this->assertSame(0, BookingAttendance::withoutGlobalScopes()->count() + BookingAttendanceAdjustment::withoutGlobalScopes()->count());
    }

    public function test_br_137_an_adjustment_never_changes_money_court_or_an_unconfirmed_booking(): void
    {
        $held = $this->booking($this->court, $this->at('10:00'), status: 'held');
        $booking = $this->booking($this->court, $this->at('12:00'));
        $other = $this->activeCourt($this->court->venue);
        $this->travelTo($this->at('13:00'));

        $this->adjust($this->owner, $held, 'no_show')->assertStatus(422)->assertJsonPath('code', 'BOOKING_NOT_CONFIRMED');
        $this->actingAs($this->owner)->postJson('/api/attendance/adjustments', [
            'code' => $booking->code, 'status' => 'no_show', 'reason' => 'Khách không đến',
            'total_amount' => 1, 'court_id' => $other->id, 'status_of_payment' => 'refunded',
        ], ['X-Organization-ID' => (string) $booking->organization_id])->assertCreated();

        $fresh = $booking->fresh();
        $this->assertSame([100000, $this->court->id, 'confirmed'], [(int) $fresh->total_amount, (int) $fresh->court_id, $fresh->status]);
        $this->assertSame('held', $held->fresh()->status);
    }

    public function test_br_135_each_status_carries_exactly_its_times_and_a_reason(): void
    {
        $booking = $this->booking($this->court, $this->at('10:00'));
        $this->travelTo($this->at('12:00'));

        $this->adjust($this->owner, $booking, 'completed', ['checked_in_at' => '2026-11-03T10:00:00+07:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('checked_out_at');
        $this->adjust($this->owner, $booking, 'completed', ['checked_in_at' => '2026-11-03T11:00:00+07:00', 'checked_out_at' => '2026-11-03T10:30:00+07:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('checked_out_at');
        $this->adjust($this->owner, $booking, 'in_use', ['checked_in_at' => '2026-11-03T12:30:00+07:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('checked_in_at');
        $this->adjust($this->owner, $booking, 'no_show', ['checked_in_at' => '2026-11-03T10:00:00+07:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('checked_in_at');
        $this->adjust($this->owner, $booking, 'no_show', reason: '')->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->assertSame(0, BookingAttendance::withoutGlobalScopes()->count() + BookingAttendanceAdjustment::withoutGlobalScopes()->count());
    }

    public function test_br_133_d8_an_adjusted_no_show_needs_the_booked_end_and_no_open_case(): void
    {
        $booking = $this->booking($this->court, $this->at('10:00'));
        $this->travelTo($this->at('10:59'));
        $this->adjust($this->owner, $booking, 'no_show')->assertStatus(422)->assertJsonPath('code', 'NO_SHOW_TOO_EARLY');

        $this->travelTo($this->at('11:00'));
        $case = AffectedReservationCase::create([
            'reservation_id' => $booking->id, 'venue_id' => $this->court->venue_id, 'court_id' => $this->court->id,
            'source_type' => 'operational_stop', 'source_id' => 1, 'status' => 'pending',
        ]);
        $this->adjust($this->owner, $booking, 'no_show')->assertStatus(409)->assertJsonPath('code', 'AFFECTED_CASE_OPEN');

        $case->update(['status' => 'kept']);
        $this->adjust($this->owner, $booking, 'no_show')->assertCreated()->assertJsonPath('reservation.attendance.no_show_by', $this->owner->name);
    }

    /** @param  array<string, string>  $times */
    private function adjust(User $user, Reservation $booking, string $status, array $times = [], string $reason = 'Khách đã đến, nhân viên ghi nhầm'): TestResponse
    {
        return $this->actingAs($user)->postJson('/api/attendance/adjustments', ['code' => $booking->code, 'status' => $status, 'reason' => $reason, ...$times], [
            'X-Organization-ID' => (string) $booking->organization_id,
        ]);
    }

    private function history(User $user, Reservation $booking): TestResponse
    {
        return $this->actingAs($user)->getJson("/api/attendance/{$booking->code}/adjustments", ['X-Organization-ID' => (string) $booking->organization_id]);
    }
}
