<?php

namespace Tests\Feature\Module07;

use App\Models\Court;
use App\Models\Tournament;
use App\Models\TournamentCourtAllocation;
use App\Models\TournamentFixture;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Module07\Concerns\RunsTournaments;
use Tests\TestCase;

/** FR-128, BR-152, UC-7.30, UC-7.31: court time reserved for a tournament, and fixtures scheduled inside it. */
class TournamentScheduleTest extends TestCase
{
    use RefreshDatabase;
    use RunsTournaments;

    private Court $court;

    private User $owner;

    private Tournament $tournament;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-03 09:00:00');
        $this->court = $this->payableCourt();
        $this->owner = $this->ownerOf($this->court->venue->organization);
        $this->tournament = $this->tournament($this->court, ['status' => 'open']);
    }

    /** @return array<string, mixed> */
    private function courtTime(string $start, string $end, ?Court $court = null): array
    {
        return ['court_id' => ($court ?? $this->court)->id, 'starts_at' => $start, 'ends_at' => $end];
    }

    public function test_br_152_reserving_never_overrides_a_confirmed_booking_or_a_live_hold(): void
    {
        $this->booking($this->court, Carbon::parse('2026-11-05 14:00'));
        $this->booking($this->court, Carbon::parse('2026-11-05 17:00'), status: 'held');
        $this->actingAs($this->owner);
        $url = "/api/managed/tournaments/{$this->tournament->uuid}/allocations";

        $this->postJson($url, $this->courtTime('2026-11-05 13:00', '2026-11-05 16:00'))->assertConflict()->assertJsonPath('code', 'ALLOCATION_CONFLICT');
        $this->postJson($url, $this->courtTime('2026-11-05 16:30', '2026-11-05 18:00'))->assertConflict()->assertJsonPath('code', 'ALLOCATION_CONFLICT');
        $this->postJson($url, $this->courtTime('2026-11-05 08:00', '2026-11-05 12:00', $this->bookableCourt()))->assertUnprocessable()->assertJsonValidationErrors('court_id');
        $this->postJson($url, $this->courtTime('2026-11-05 08:00', '2026-11-05 12:00'))->assertCreated()->assertJsonPath('allocation.court.id', $this->court->id);
        $this->postJson("/api/managed/tournaments/{$this->tournament($this->court, ['status' => 'open'])->uuid}/allocations", $this->courtTime('2026-11-05 11:00', '2026-11-05 13:00'))
            ->assertConflict()->assertJsonPath('code', 'ALLOCATION_CONFLICT');
    }

    public function test_br_152_ordinary_booking_cannot_take_reserved_court_time_and_shows_it_busy(): void
    {
        $this->allocation($this->tournament, $this->court, 2, 8, 12);

        $this->checkout($this->customer(), $this->court, '2026-11-05 11:00', '2026-11-05 12:00', 100000)->assertConflict();
        $this->checkout($this->customer(), $this->court, '2026-11-05 12:00', '2026-11-05 13:00', 100000)->assertCreated();
        $busy = collect($this->getJson("/api/availability?court_id={$this->court->id}&date=2026-11-05")->assertOk()->json())
            ->reject(fn (array $slot): bool => $slot['available'])->map(fn (array $slot): string => substr($slot['startsAt'], 11, 5))->values()->all();
        $this->assertSame(['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30'], $busy);
    }

    public function test_br_152_fixtures_fit_inside_reserved_time_and_never_share_a_court(): void
    {
        $this->allocation($this->tournament, $this->court, 10, 8, 12);
        $this->drawn($this->tournament, 4);
        [$first, $second] = TournamentFixture::where('round', 1)->orderBy('position')->get()->all();
        $this->actingAs($this->owner);

        $this->putJson("/api/tournament-fixtures/{$first->id}/schedule", $this->courtTime('2026-11-13 11:00', '2026-11-13 13:00'))
            ->assertUnprocessable()->assertJsonPath('code', 'FIXTURE_OUTSIDE_COURTS');
        $this->putJson("/api/tournament-fixtures/{$first->id}/schedule", $this->courtTime('2026-11-13 08:00', '2026-11-13 09:00'))
            ->assertOk()->assertJsonPath('fixture.court.id', $this->court->id);
        $this->putJson("/api/tournament-fixtures/{$second->id}/schedule", $this->courtTime('2026-11-13 08:30', '2026-11-13 09:30'))
            ->assertConflict()->assertJsonPath('code', 'FIXTURE_CLASH');
        $this->putJson("/api/tournament-fixtures/{$second->id}/schedule", $this->courtTime('2026-11-13 09:00', '2026-11-13 10:00'))->assertOk();
    }

    public function test_uc_7_30_court_time_with_a_scheduled_fixture_is_not_released(): void
    {
        $used = $this->allocation($this->tournament, $this->court, 10, 8, 12);
        $spare = $this->allocation($this->tournament, $this->court, 11, 8, 12);
        $this->drawn($this->tournament, 2);
        TournamentFixture::first()->update(['court_id' => $this->court->id, 'starts_at' => $used->starts_at, 'ends_at' => $used->starts_at->copy()->addHour()]);
        $this->actingAs($this->owner);

        $this->deleteJson("/api/managed/tournaments/{$this->tournament->uuid}/allocations/{$used->id}")->assertConflict()->assertJsonPath('code', 'ALLOCATION_IN_USE');
        $this->deleteJson("/api/managed/tournaments/{$this->tournament->uuid}/allocations/{$spare->id}")->assertNoContent();
        $this->assertSame([$used->id], TournamentCourtAllocation::pluck('id')->all());
    }

    public function test_br_152_an_internal_fixture_plays_inside_the_renters_booking(): void
    {
        $court = $this->bookableCourt();
        $tournament = $this->internalTournament($court);
        $representative = $tournament->grants()->first()->user;
        $this->drawn($tournament, 2);
        $fixture = TournamentFixture::first();
        $this->actingAs($representative);

        $this->putJson("/api/tournament-fixtures/{$fixture->id}/schedule", $this->courtTime('2026-11-06 07:00', '2026-11-06 08:00', $court))
            ->assertUnprocessable()->assertJsonPath('code', 'FIXTURE_OUTSIDE_COURTS');
        $this->putJson("/api/tournament-fixtures/{$fixture->id}/schedule", $this->courtTime('2026-11-06 09:00', '2026-11-06 10:00', $court))->assertOk();
    }

    public function test_uc_7_31_scheduling_waits_for_the_draw(): void
    {
        $fixture = TournamentFixture::create(['tournament_id' => $this->tournament->id, 'stage' => 'knockout', 'group' => '', 'round' => 1, 'position' => 1, 'status' => 'pending']);

        $this->actingAs($this->owner)->putJson("/api/tournament-fixtures/{$fixture->id}/schedule", $this->courtTime('2026-11-13 08:00', '2026-11-13 09:00'))
            ->assertConflict()->assertJsonPath('code', 'FIXTURE_NOT_READY');
    }
}
