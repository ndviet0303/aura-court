<?php

namespace Tests\Feature\Module02;

use App\Models\Court;
use App\Models\User;
use App\Models\VenueOperatingSchedule;
use App\Services\Reservation\VenueOperatingCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsVenues;
use Tests\TestCase;

/** FR-28, FR-32, BR-31; design D6. UC-2.5, UC-2.10. */
class CourtScheduleTest extends TestCase
{
    use BuildsVenues;
    use RefreshDatabase;

    /** @param  list<array{0: string, 1: string}>  $intervals  every day the same */
    private function week(array $intervals): array
    {
        $days = [];
        foreach (range(1, 7) as $day) {
            $days[(string) $day] = ['closed' => false, 'intervals' => array_map(fn ($i) => ['start' => $i[0], 'end' => $i[1]], $intervals)];
        }

        return $days;
    }

    private function save(User $actor, Court $court, array $weeklyHours, bool $forCourt = true)
    {
        return $this->actingAs($actor)->putJson(
            "/api/partner/organizations/{$court->venue->organization_id}/venues/{$court->venue_id}/operating-schedule",
            array_filter(['court_id' => $forCourt ? $court->id : null, 'timezone' => 'Asia/Ho_Chi_Minh', 'weekly_hours' => $weeklyHours, 'acknowledge_affected' => true], fn ($v) => $v !== null),
        );
    }

    public function test_br_31_court_schedule_lies_inside_the_venue_hours(): void
    {
        $court = $this->bookableCourt();
        $owner = $this->ownerOf($court->venue->organization);

        $this->save($owner, $court, $this->week([['05:00', '07:00']]))->assertStatus(422);
        $this->save($owner, $court, $this->week([['21:00', '23:00']]))->assertStatus(422);
        $this->save($owner, $court, $this->week([['06:00', '22:00']]))->assertOk();
    }

    public function test_br_31_intervals_may_touch_but_not_overlap(): void
    {
        $court = $this->bookableCourt();
        $owner = $this->ownerOf($court->venue->organization);

        $this->save($owner, $court, $this->week([['08:00', '10:00'], ['09:00', '11:00']]))->assertStatus(422);
        $this->save($owner, $court, $this->week([['10:00', '08:00']]))->assertStatus(422);
        $this->save($owner, $court, $this->week([['08:00', '10:00'], ['10:00', '12:00']]))->assertOk();

        $this->actingAs($owner)->getJson("/api/partner/organizations/{$court->venue->organization_id}/venues/{$court->venue_id}/courts/{$court->id}")
            ->assertOk()->assertJsonPath('data.service_schedule.weekly_hours.1.intervals.1.start', '10:00');
    }

    public function test_fr_28_open_day_needs_an_interval_and_a_closed_day_needs_none(): void
    {
        $court = $this->bookableCourt();
        $owner = $this->ownerOf($court->venue->organization);
        $week = $this->week([['06:00', '22:00']]);

        $this->save($owner, $court, ['1' => ['closed' => false, 'intervals' => []]] + $week, false)->assertStatus(422)->assertJsonValidationErrors('weekly_hours.1.intervals');
        $this->save($owner, $court, ['7' => ['closed' => true, 'intervals' => []]] + $week, false)->assertOk();
    }

    public function test_br_25_only_the_owner_sets_schedules_for_courts_of_that_venue(): void
    {
        $court = $this->bookableCourt();
        $foreign = $this->activeCourt($this->openVenue(['organization_id' => $court->venue->organization_id]));
        $owner = $this->ownerOf($court->venue->organization);

        $this->save($this->staffOf($court->venue), $court, $this->week([['06:00', '22:00']]))->assertForbidden();
        $this->save($this->ownerOf($this->openVenue()->organization), $court, $this->week([['06:00', '22:00']]))->assertForbidden();
        $this->actingAs($owner)->putJson("/api/partner/organizations/{$court->venue->organization_id}/venues/{$court->venue_id}/operating-schedule", [
            'court_id' => $foreign->id, 'timezone' => 'Asia/Ho_Chi_Minh', 'weekly_hours' => $this->week([['06:00', '22:00']]),
        ])->assertForbidden();
    }

    public function test_uc_2_7_a1_a_court_without_its_own_schedule_is_unset_and_closed_not_inherited(): void
    {
        $court = $this->bookableCourt();
        $court->forceFill(['status' => Court::STATUS_INACTIVE])->save();
        VenueOperatingSchedule::query()->where('scope_key', 'court:'.$court->id)->delete();
        $owner = $this->ownerOf($court->venue->organization);
        $venueUrl = "/api/partner/organizations/{$court->venue->organization_id}/venues/{$court->venue_id}";

        $this->actingAs($owner)->getJson("{$venueUrl}/courts/{$court->id}")->assertOk()
            ->assertJsonPath('data.service_schedule', null)
            ->assertJsonMissingPath('data.service_schedule_inherited');
        $this->actingAs($owner)->getJson("{$venueUrl}/operating-schedule?court_id={$court->id}")->assertOk()->assertJsonPath('data', null);
        $tomorrow = now('Asia/Ho_Chi_Minh')->addDay()->setTime(9, 0);
        $this->assertFalse(app(VenueOperatingCalendar::class)->isOpen($court, $tomorrow->copy()->utc(), $tomorrow->copy()->addHour()->utc()));
        $codes = collect($this->actingAs($owner)->postJson("/api/admin/courts/{$court->id}/open")->assertStatus(422)->json('missing_items'))->pluck('code');
        $this->assertContains('service_schedule', $codes);
    }

    public function test_uc_2_7_a1_backfill_copies_the_venue_hours_into_courts_without_a_schedule(): void
    {
        $court = $this->bookableCourt();
        $kept = $this->activeCourt($court->venue);
        VenueOperatingSchedule::query()->where('scope_key', 'court:'.$court->id)->delete();

        (require database_path('migrations/2026_10_28_200001_backfill_court_service_schedules.php'))->up();

        $copy = VenueOperatingSchedule::query()->where('scope_key', 'court:'.$court->id)->sole();
        $this->assertSame([$court->id, '06:00', '22:00'], [(int) $copy->court_id, $copy->weekly_hours['1']['intervals'][0]['start'], $copy->weekly_hours['1']['intervals'][0]['end']]);
        $this->assertSame('23:59', VenueOperatingSchedule::query()->where('scope_key', 'court:'.$kept->id)->sole()->weekly_hours['1']['intervals'][0]['end']);
    }
}
