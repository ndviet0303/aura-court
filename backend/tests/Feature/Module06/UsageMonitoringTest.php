<?php

namespace Tests\Feature\Module06;

use App\Domain\Attendance\Actions\SendUsageTimeReminders;
use App\Models\BookingAttendance;
use App\Models\Court;
use App\Models\OutboxMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Feature\Module06\Concerns\OperatesBookings;
use Tests\TestCase;

/** UC-6.3, FR-104, FR-105, BR-131: bookings in use, flagged 10 minutes before their booked end and once past it. */
class UsageMonitoringTest extends TestCase
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

    public function test_fr_104_in_use_lists_checked_in_bookings_by_booked_end_with_their_flag(): void
    {
        $second = $this->activeCourt($this->court->venue);
        $overdue = $this->booking($this->court, $this->at('09:00'));
        $endingSoon = $this->booking($this->court, $this->at('10:00'));
        $long = $this->booking($second, $this->at('10:00'), hours: 2);
        $done = $this->booking($second, $this->at('12:00'));
        $this->booking($this->court, $this->at('11:00'));
        $this->travelTo($this->at('09:00'));
        $this->attend($this->staff, 'check-in', $overdue)->assertCreated();
        $this->travelTo($this->at('10:00'));
        $this->attend($this->staff, 'check-in', $endingSoon)->assertCreated();
        $this->attend($this->staff, 'check-in', $long)->assertCreated();
        $this->travelTo($this->at('10:49:59'));
        $this->inUse()->assertJsonPath('reservations.*.code', [$overdue->code, $endingSoon->code, $long->code])
            ->assertJsonPath('reservations.*.usage_flag', ['overdue', null, null]);
        $this->travelTo($this->at('10:50'));
        $this->inUse()->assertJsonPath('reservations.*.usage_flag', ['overdue', 'ending_soon', null]);
        $this->travelTo($this->at('11:00'));
        $this->inUse()->assertJsonPath('reservations.*.usage_flag', ['overdue', 'overdue', null]);

        $this->travelTo($this->at('11:55'));
        $this->attend($this->staff, 'check-in', $done)->assertCreated();
        $this->attend($this->staff, 'check-out', $done)->assertOk();
        $this->attend($this->staff, 'check-out', $long)->assertOk();
        $this->inUse()->assertJsonPath('reservations.*.code', [$overdue->code, $endingSoon->code]);
    }

    public function test_fr_105_br_131_each_reminder_point_reaches_the_owner_and_assigned_staff_once(): void
    {
        $owner = $this->ownerOf($this->court->venue->organization);
        $this->staffOf($this->openVenue(['organization_id' => $this->court->venue->organization_id]));
        $booking = $this->booking($this->court, $this->at('10:00'));
        $this->travelTo($this->at('10:00'));
        $this->attend($this->staff, 'check-in', $booking)->assertCreated();

        $this->travelTo($this->at('10:49'));
        $this->remind();
        $this->assertCount(0, $this->reminders());
        foreach (['10:50', '10:51', '11:00', '11:30'] as $time) {
            $this->travelTo($this->at($time));
            $this->remind();
        }

        $reminders = $this->reminders();
        $this->assertEqualsCanonicalizing(
            [['ending_soon', $owner->id], ['ending_soon', $this->staff->id], ['overdue', $owner->id], ['overdue', $this->staff->id]],
            $reminders->map(fn (OutboxMessage $m) => [$m->payload['kind'], $m->payload['user_id']])->all(),
        );
        $first = $reminders->first();
        $this->assertSame(['database'], $first->payload['channels']);
        $this->assertSame("/admin/operations?venue={$this->court->venue_id}", $first->payload['action_url']);
        $this->assertStringContainsString($this->court->code, $first->payload['title']);
        $this->assertStringContainsString($booking->code, $first->payload['body']);
        $this->assertStringContainsString('11:00 03/11/2026', $first->payload['body']);
        $this->assertSame(BookingAttendance::STATUS_CHECKED_IN, BookingAttendance::value('status'), 'A reminder never checks the booking out.');
    }

    public function test_br_131_a_missed_run_sends_only_the_overdue_reminder_and_a_checked_out_booking_none(): void
    {
        $this->ownerOf($this->court->venue->organization);
        $late = $this->booking($this->court, $this->at('10:00'));
        $returned = $this->booking($this->activeCourt($this->court->venue), $this->at('10:00'));
        $this->travelTo($this->at('10:00'));
        $this->attend($this->staff, 'check-in', $late)->assertCreated();
        $this->attend($this->staff, 'check-in', $returned)->assertCreated();
        $this->travelTo($this->at('10:40'));
        $this->attend($this->staff, 'check-out', $returned)->assertOk();

        $this->travelTo($this->at('11:05'));
        $this->remind();

        $this->assertSame(['overdue'], $this->reminders()->pluck('payload.kind')->unique()->values()->all());
        $this->assertSame([$late->code], $this->reminders()->pluck('payload.reservation_code')->unique()->values()->all());
    }

    private function inUse()
    {
        return $this->actingAs($this->staff)->getJson(
            "/api/operations/venues/{$this->court->venue_id}/in-use",
            ['X-Organization-ID' => (string) $this->court->venue->organization_id],
        )->assertOk();
    }

    private function remind(): void
    {
        app(SendUsageTimeReminders::class)->execute();
    }

    /** @return Collection<int, OutboxMessage> */
    private function reminders(): Collection
    {
        return OutboxMessage::query()->withoutGlobalScopes()->where('event_type', 'attendance.reminder')->orderBy('id')->get();
    }
}
