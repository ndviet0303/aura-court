<?php

namespace Tests\Feature\Module05;

use App\Models\BookingAttendance;
use App\Models\Court;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsVenues;
use Tests\Concerns\RecordsOwnerPayments;
use Tests\TestCase;

/** UC-5.11, FR-95, BR-89, BR-108..BR-113, BR-118: five separate metrics, each with its own time and unique booking count. */
class OwnerFinanceReportTest extends TestCase
{
    use BuildsVenues;
    use RecordsOwnerPayments;
    use RefreshDatabase;

    private Venue $venue;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-12-05 10:00', 'Asia/Ho_Chi_Minh'));
        $this->venue = $this->openVenue();
        $this->court = $this->activeCourt($this->venue);
    }

    public function test_fr_95_br_109_br_113_the_five_metrics_stay_separate_and_have_no_revenue_total(): void
    {
        $at = $this->vn('2026-11-10 09:00');
        $done = $this->booking($this->court, 200000, $at);
        $this->receipt($this->venue, 200000, $at, $done);
        $this->attendance($done, BookingAttendance::STATUS_CHECKED_OUT, $this->vn('2026-11-12 18:00'), $this->vn('2026-11-12 19:00'));

        $metrics = $this->report(['from' => '2026-11-01', 'to' => '2026-11-30'])->json('data.metrics');

        $this->assertSame(['received', 'completed', 'unfinished', 'no_show', 'incident'], array_keys($metrics));
        $this->assertSame([200000, 1], [$metrics['received']['amount'], $metrics['received']['count']]);
        $this->assertSame([200000, 1], [$metrics['completed']['amount'], $metrics['completed']['count']]);
        $this->assertSame([0, 0], [$metrics['unfinished']['amount'], $metrics['unfinished']['count']]);
        $this->assertSame($this->vn('2026-11-12 19:00')->utc()->toISOString(), $metrics['completed']['bookings'][0]['at']);
    }

    public function test_br_108_the_period_is_vietnam_calendar_days_half_open(): void
    {
        $inside = [$this->vn('2026-11-01 00:00'), $this->vn('2026-11-30 23:59:59')];
        $outside = [$this->vn('2026-10-31 23:59:59'), $this->vn('2026-12-01 00:00')];
        foreach ([...$inside, ...$outside] as $at) {
            $this->receipt($this->venue, 100000, $at, $this->booking($this->court, 100000, $at));
        }

        $metrics = $this->report(['from' => '2026-11-01', 'to' => '2026-11-30'])->json('data.metrics');

        $this->assertSame([200000, 2], [$metrics['received']['amount'], $metrics['received']['count']]);
        $this->assertSame([200000, 2], [$metrics['unfinished']['amount'], $metrics['unfinished']['count']]);
    }

    public function test_br_109_received_is_the_transferred_amount_by_its_time_and_counts_only_allocated_transfers(): void
    {
        $booking = $this->booking($this->court, 200000, $this->vn('2026-11-10 09:00'));
        $this->receipt($this->venue, 200000, $this->vn('2026-11-10 09:00'), $booking);
        $this->receipt($this->venue, 180000, $this->vn('2026-11-10 09:01'), $this->booking($this->court, 200000), status: 'exception');
        $this->receipt($this->venue, 90000, $this->vn('2026-11-10 09:02'), status: 'unmatched');

        $received = $this->report(['from' => '2026-11-01', 'to' => '2026-11-30'])->json('data.metrics.received');

        $this->assertSame([200000, 1], [$received['amount'], $received['count']]);
        $this->assertSame($booking->code, $received['bookings'][0]['code']);
    }

    public function test_br_110_unfinished_is_paid_in_the_period_and_still_confirmed_or_in_use_when_viewed(): void
    {
        $at = $this->vn('2026-11-10 09:00');
        $waiting = $this->booking($this->court, 100000, $at);
        $playing = $this->booking($this->court, 120000, $at, 'in_use');
        $later = $this->booking($this->court, 150000, $at);
        $this->booking($this->court, 170000, $at, 'cancelled', ['cancelled_at' => $this->wallTime($this->vn('2026-11-11 09:00'))]);
        $this->booking($this->court, 190000, null, 'held');

        $unfinished = $this->report(['from' => '2026-11-01', 'to' => '2026-11-30'])->json('data.metrics.unfinished');
        $this->assertSame([370000, 3], [$unfinished['amount'], $unfinished['count']]);

        // The status is read when the report is viewed: checking out moves the booking out of U.
        $this->attendance($later, BookingAttendance::STATUS_CHECKED_OUT, $this->vn('2026-12-02 18:00'));
        $unfinished = $this->report(['from' => '2026-11-01', 'to' => '2026-11-30'])->json('data.metrics.unfinished');
        $this->assertEqualsCanonicalizing([$waiting->code, $playing->code], array_column($unfinished['bookings'], 'code'));
    }

    public function test_br_111_no_show_is_counted_by_its_record_time_and_never_as_completed(): void
    {
        $booking = $this->booking($this->court, 100000, $this->vn('2026-10-20 09:00'));
        $this->attendance($booking, BookingAttendance::STATUS_NO_SHOW, $this->vn('2026-11-03 19:00'));

        $metrics = $this->report(['from' => '2026-11-01', 'to' => '2026-11-30'])->json('data.metrics');

        $this->assertSame([100000, 1], [$metrics['no_show']['amount'], $metrics['no_show']['count']]);
        $this->assertSame(0, $metrics['completed']['count']);
        $this->assertSame(0, $metrics['unfinished']['count']);
    }

    public function test_br_112_incident_counts_paid_bookings_ended_by_an_operational_incident_by_their_end_time(): void
    {
        $ended = ['cancelled_at' => $this->wallTime($this->vn('2026-11-15 08:00')), 'metadata' => ['release_reason' => 'operator_affected_booking']];
        $incident = $this->booking($this->court, 100000, $this->vn('2026-10-20 09:00'), 'cancelled', $ended);
        // Ended before payment, or cancelled for another reason: not an incident end of a paid booking.
        $this->booking($this->court, 100000, null, 'cancelled', $ended);
        $this->booking($this->court, 100000, $this->vn('2026-10-20 09:00'), 'cancelled', ['metadata' => ['release_reason' => 'customer_cancelled']] + $ended);
        // Moved or still being handled: stays confirmed.
        $this->booking($this->court, 100000, $this->vn('2026-10-20 09:00'));

        $metrics = $this->report(['from' => '2026-11-01', 'to' => '2026-11-30'])->json('data.metrics');

        $this->assertSame([100000, 1], [$metrics['incident']['amount'], $metrics['incident']['count']]);
        $this->assertSame($incident->code, $metrics['incident']['bookings'][0]['code']);
        $this->assertSame(0, $metrics['completed']['count']);
    }

    public function test_br_113_a_zero_booking_is_counted_and_each_booking_code_once(): void
    {
        $at = $this->vn('2026-11-10 09:00');
        $free = $this->booking($this->court, 0, $at);
        $this->attendance($free, BookingAttendance::STATUS_CHECKED_OUT, $at);
        $twice = $this->booking($this->court, 200000, $at);
        $this->receipt($this->venue, 100000, $at, $twice);
        $this->receipt($this->venue, 100000, $at->copy()->addMinute(), $twice);

        $metrics = $this->report(['from' => '2026-11-01', 'to' => '2026-11-30'])->json('data.metrics');

        $this->assertSame([0, 1], [$metrics['completed']['amount'], $metrics['completed']['count']]);
        $this->assertSame([200000, 1], [$metrics['received']['amount'], $metrics['received']['count']]);
    }

    public function test_fr_95_the_report_filters_by_venue_and_court(): void
    {
        $at = $this->vn('2026-11-10 09:00');
        $otherCourt = $this->activeCourt($this->venue);
        $otherVenue = $this->openVenue(['organization_id' => $this->venue->organization_id]);
        $this->booking($this->court, 100000, $at);
        $this->booking($otherCourt, 200000, $at);
        $this->booking($this->activeCourt($otherVenue), 400000, $at);
        $this->booking($this->activeCourt($this->openVenue()), 800000, $at);

        $month = ['from' => '2026-11-01', 'to' => '2026-11-30'];
        $this->assertSame(700000, $this->report($month)->json('data.metrics.unfinished.amount'));
        $this->assertSame(300000, $this->report($month + ['venue_id' => $this->venue->id])->json('data.metrics.unfinished.amount'));
        $this->assertSame(200000, $this->report($month + ['venue_id' => $this->venue->id, 'court_id' => $otherCourt->id])->json('data.metrics.unfinished.amount'));
    }

    public function test_br_89_staff_and_other_owners_cannot_read_the_report(): void
    {
        $base = "/api/partner/organizations/{$this->venue->organization_id}/finance/report?from=2026-11-01&to=2026-11-30";
        $foreign = $this->activeCourt($this->openVenue());

        $this->actingAs($this->staffOf($this->venue))->getJson($base)->assertForbidden();
        $this->actingAs($this->ownerOf($foreign->venue->organization))->getJson($base)->assertForbidden();
        $owner = $this->ownerOf($this->venue->organization);
        $this->actingAs($owner)->getJson("{$base}&venue_id={$foreign->venue_id}")->assertForbidden();
        $this->actingAs($owner)->getJson("{$base}&court_id={$foreign->id}")->assertForbidden();
        $this->actingAs($owner)->getJson(str_replace('to=2026-11-30', 'to=2026-10-30', $base))->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    /** @param  array<string, mixed>  $query */
    private function report(array $query)
    {
        return $this->actingAs($this->ownerOf($this->venue->organization))
            ->getJson("/api/partner/organizations/{$this->venue->organization_id}/finance/report?".http_build_query($query))
            ->assertOk();
    }

    private function vn(string $at): Carbon
    {
        return Carbon::parse($at, 'Asia/Ho_Chi_Minh');
    }
}
