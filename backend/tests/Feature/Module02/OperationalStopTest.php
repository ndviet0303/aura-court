<?php

namespace Tests\Feature\Module02;

use App\Domain\Venue\Actions\ApplyStartedOperationalStops;
use App\Models\Court;
use App\Models\OperationalStop;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsVenues;
use Tests\Feature\Module02\Concerns\SeedsBookings;
use Tests\TestCase;

/** FR-36, FR-39..FR-42, BR-34..BR-37, BR-41; design D4, D5. UC-2.14, UC-2.19, UC-2.20, UC-2.21, UC-2.22. */
class OperationalStopTest extends TestCase
{
    use BuildsVenues;
    use RefreshDatabase;
    use SeedsBookings;

    private const REASON = 'Thay lưới và sơn lại sân';

    private function court(User $actor, Court $court, string $command, array $body = []): TestResponse
    {
        return $this->actingAs($actor)->postJson("/api/admin/courts/{$court->id}/{$command}", $body);
    }

    private function venue(User $actor, Venue $venue, string $command, array $body = []): TestResponse
    {
        return $this->actingAs($actor)->postJson("/api/partner/organizations/{$venue->organization_id}/venues/{$venue->id}/{$command}", $body);
    }

    /** @return array<string, bool> start H:i => available, tomorrow */
    private function availability(Court $court): array
    {
        $rows = $this->getJson("/api/availability?court_id={$court->id}&date={$this->tomorrowAt(0)->toDateString()}")->assertOk()->json();

        return collect($rows)->mapWithKeys(fn (array $row) => [substr($row['startsAt'], 11, 5) => $row['available']])->all();
    }

    private function at(CarbonInterface $time): string
    {
        return $time->toIso8601String();
    }

    public function test_br_35_stop_validation(): void
    {
        $court = $this->bookableCourt();
        $owner = $this->ownerOf($court->venue->organization);

        $this->court($owner, $court, 'pause', ['reason' => '  '.str_repeat('a', 9).'  ', 'kind' => 'maintenance'])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->court($owner, $court, 'pause', ['reason' => str_repeat('a', 1001), 'kind' => 'maintenance'])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->court($owner, $court, 'pause', ['reason' => self::REASON, 'kind' => 'maintenance', 'starts_at' => $this->at(now()->subMinutes(5))])->assertStatus(422)->assertJsonValidationErrors('starts_at');
        $this->court($owner, $court, 'pause', ['reason' => self::REASON, 'kind' => 'maintenance', 'starts_at' => $this->at($this->tomorrowAt(12)), 'expected_end_at' => $this->at($this->tomorrowAt(12))])->assertStatus(422)->assertJsonValidationErrors('expected_end_at');
        $this->court($owner, $court, 'pause', ['reason' => self::REASON])->assertStatus(422)->assertJsonValidationErrors('kind');
        $this->court($owner, $court, 'close', ['reason' => self::REASON, 'expected_end_at' => $this->at($this->tomorrowAt(12))])->assertStatus(422)->assertJsonValidationErrors('expected_end_at');
        $this->venue($owner, $court->venue, 'pause', ['reason' => self::REASON, 'kind' => 'maintenance'])->assertStatus(422)->assertJsonValidationErrors('kind');
        $this->assertSame(0, OperationalStop::count());

        $this->court($owner, $court, 'pause', ['reason' => str_repeat('a', 10), 'kind' => 'maintenance', 'starts_at' => $this->at(now()->subSeconds(30))])->assertOk();
    }

    public function test_br_36_a_scheduled_stop_blocks_bookings_from_its_start_and_flips_the_status_when_it_starts(): void
    {
        $court = $this->bookableCourt();
        $owner = $this->ownerOf($court->venue->organization);

        $this->court($owner, $court, 'pause', ['reason' => self::REASON, 'kind' => 'maintenance', 'starts_at' => $this->at($this->tomorrowAt(12)), 'expected_end_at' => $this->at($this->tomorrowAt(14))])->assertOk();

        $this->assertSame(Court::STATUS_ACTIVE, $court->fresh()->status, 'the status waits for the start');
        $slots = $this->availability($court);
        $this->assertSame([true, true, false, false], [$slots['11:00'], $slots['11:30'], $slots['12:00'], $slots['15:00']], 'blocked from the start, ignoring the expected end');

        app(ApplyStartedOperationalStops::class)->execute();
        $this->assertSame(Court::STATUS_ACTIVE, $court->fresh()->status);
        $this->travelTo($this->tomorrowAt(12, 1));
        app(ApplyStartedOperationalStops::class)->execute();
        $this->assertSame(Court::STATUS_PAUSED, $court->fresh()->status);
        $this->travelTo($this->tomorrowAt(15));
        app(ApplyStartedOperationalStops::class)->execute();
        $this->assertSame(Court::STATUS_PAUSED, $court->fresh()->status, 'the expected end never reopens the court');
    }

    public function test_br_36_new_bookings_overlapping_a_scheduled_stop_are_refused_and_touching_ones_pass(): void
    {
        $court = $this->bookableCourt();
        $this->court($this->ownerOf($court->venue->organization), $court, 'pause', ['reason' => self::REASON, 'kind' => 'business', 'starts_at' => $this->at($this->tomorrowAt(12))])->assertOk();
        $customer = User::factory()->create();
        $book = fn (int $hour) => $this->actingAs($customer)
            ->withHeaders(['Idempotency-Key' => "stop-{$hour}"])
            ->postJson('/api/reservations', ['court_id' => $court->id, 'starts_at' => $this->at($this->tomorrowAt($hour)), 'ends_at' => $this->at($this->tomorrowAt($hour + 1)), 'expected_total' => 100000]);

        $book(12)->assertStatus(409);
        $book(11)->assertCreated();
    }

    public function test_br_37_a_court_stop_touches_only_that_court_and_a_venue_stop_keeps_court_statuses(): void
    {
        $court = $this->bookableCourt();
        $sibling = $this->activeCourt($court->venue);
        $this->priceCourt($sibling);
        $owner = $this->ownerOf($court->venue->organization);

        $this->court($owner, $court, 'pause', ['reason' => self::REASON, 'kind' => 'maintenance', 'acknowledge_affected' => true])->assertOk();
        $this->assertSame([Court::STATUS_PAUSED, Court::STATUS_ACTIVE], [$court->fresh()->status, $sibling->fresh()->status]);
        $this->assertTrue($this->availability($sibling)['10:00']);

        $this->venue($owner, $court->venue, 'pause', ['reason' => self::REASON, 'acknowledge_affected' => true])->assertOk();
        $this->assertSame([Venue::STATUS_PAUSED, Court::STATUS_PAUSED, Court::STATUS_ACTIVE], [$court->venue->fresh()->status, $court->fresh()->status, $sibling->fresh()->status]);
        $this->getJson("/api/availability?court_id={$sibling->id}&date={$this->tomorrowAt(0)->toDateString()}")->assertNotFound();
        $this->getJson('/api/courts')->assertJsonMissing(['id' => $sibling->id]);
    }

    public function test_br_34_staff_record_and_end_only_maintenance_pauses(): void
    {
        $court = $this->bookableCourt();
        $venue = $court->venue;
        $staff = $this->staffOf($venue);
        $owner = $this->ownerOf($venue->organization);

        $this->court($staff, $court, 'pause', ['reason' => self::REASON, 'kind' => 'business'])->assertForbidden();
        $this->court($staff, $court, 'close', ['reason' => self::REASON])->assertForbidden();
        $this->venue($staff, $venue, 'pause', ['reason' => self::REASON])->assertForbidden();
        $this->venue($staff, $venue, 'close', ['reason' => self::REASON])->assertForbidden();
        $this->court($this->staffOf($this->openVenue(['organization_id' => $venue->organization_id])), $court, 'pause', ['reason' => self::REASON, 'kind' => 'maintenance'])->assertForbidden();

        $this->court($staff, $court, 'pause', ['reason' => self::REASON, 'kind' => 'maintenance'])->assertOk();
        $this->court($staff, $court, 'resume')->assertOk();
        $this->assertSame(Court::STATUS_ACTIVE, $court->fresh()->status);

        // FR-42: after a business pause only the owner resumes.
        $this->court($owner, $court, 'pause', ['reason' => self::REASON, 'kind' => 'business'])->assertOk();
        $this->court($staff, $court, 'resume')->assertForbidden();
        $this->assertSame(Court::STATUS_PAUSED, $court->fresh()->status);
        $this->court($owner, $court, 'resume')->assertOk();
        $this->assertSame(Court::STATUS_ACTIVE, $court->fresh()->status);
    }

    public function test_fr_39_resume_needs_service_time_and_prices(): void
    {
        $venue = $this->openVenue();
        $court = $this->activeCourt($venue);
        $owner = $this->ownerOf($venue->organization);
        $this->court($owner, $court, 'pause', ['reason' => self::REASON, 'kind' => 'maintenance'])->assertOk();

        $this->court($owner, $court, 'resume')->assertStatus(422)->assertJsonFragment(['code' => 'price_coverage_gap']);
        $this->assertSame(Court::STATUS_PAUSED, $court->fresh()->status);
        $this->priceCourt($court);
        $this->court($owner, $court, 'resume')->assertOk();
        $this->assertNotNull(OperationalStop::sole()->ended_at);
    }

    public function test_fr_41_closed_is_final_and_keeps_the_data(): void
    {
        $court = $this->bookableCourt();
        $sibling = $this->activeCourt($court->venue);
        $owner = $this->ownerOf($court->venue->organization);
        $this->booking($court, $this->tomorrowAt(10));

        $this->court($owner, $court, 'close', ['reason' => self::REASON, 'acknowledge_affected' => true])->assertOk();
        $this->assertSame(Court::STATUS_CLOSED, $court->fresh()->status);
        $this->court($owner, $court, 'resume')->assertStatus(422)->assertJsonValidationErrors('status');
        $this->court($owner, $court, 'open')->assertStatus(422)->assertJsonValidationErrors('status');
        $this->court($owner, $court, 'pause', ['reason' => self::REASON, 'kind' => 'business'])->assertStatus(422);
        $this->assertSame(Court::STATUS_CLOSED, $court->fresh()->status);
        $this->assertSame(1, Reservation::query()->where('court_id', $court->id)->count());

        $venue = $court->venue;
        $this->venue($owner, $venue, 'close', ['reason' => self::REASON, 'acknowledge_affected' => true])->assertOk();
        $this->assertSame([Venue::STATUS_CLOSED, Court::STATUS_ACTIVE], [$venue->fresh()->status, $sibling->fresh()->status]);
        $this->venue($owner, $venue, 'resume')->assertStatus(422)->assertJsonValidationErrors('status');
        $this->venue($owner, $venue, 'open')->assertStatus(422)->assertJsonValidationErrors('status');
        $this->assertSame(Venue::STATUS_CLOSED, $venue->fresh()->status);
        $this->getJson('/api/courts')->assertJsonMissing(['id' => $sibling->id]);
    }
}
