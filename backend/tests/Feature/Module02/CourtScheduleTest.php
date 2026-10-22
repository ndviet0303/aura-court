<?php

namespace Tests\Feature\Module02;

use App\Models\Court;
use App\Models\User;
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
            ->assertOk()->assertJsonPath('data.service_schedule_inherited', false)
            ->assertJsonPath('data.service_schedule.weekly_hours.1.intervals.1.start', '10:00');
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
}
