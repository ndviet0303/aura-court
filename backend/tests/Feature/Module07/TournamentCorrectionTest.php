<?php

namespace Tests\Feature\Module07;

use App\Models\Court;
use App\Models\Tournament;
use App\Models\TournamentEvent;
use App\Models\TournamentFixture;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Module07\Concerns\RunsTournaments;
use Tests\TestCase;

/** FR-130, BR-157, UC-7.33: only the owner corrects a saved public result, with a reason and a before/after history. */
class TournamentCorrectionTest extends TestCase
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
        $this->tournament = $this->drawn($this->tournament($this->court, ['status' => 'open']), 4);
    }

    private function semi(int $position): TournamentFixture
    {
        return TournamentFixture::where('round', 1)->where('position', $position)->firstOrFail();
    }

    public function test_fr_130_the_owner_corrects_with_a_reason_and_the_next_round_follows(): void
    {
        $semi = $this->semi(1);
        $this->actingAs($this->owner)->postJson("/api/tournament-fixtures/{$semi->id}/result", ['games' => [[21, 10], [21, 12]]])->assertOk();
        $url = "/api/tournament-fixtures/{$semi->id}/correction";

        $this->postJson($url, ['games' => [[10, 21], [12, 21]]])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson($url, ['games' => [[10, 21], [12, 21]], 'reason' => 'Trọng tài ghi nhầm bên'])
            ->assertOk()->assertJsonPath('fixture.winner_entry_id', $semi->entry_two_id);

        $this->assertSame($semi->entry_two_id, TournamentFixture::where('round', 2)->value('entry_one_id'));
        $event = TournamentEvent::where('type', 'result.corrected')->firstOrFail();
        $this->assertSame('Trọng tài ghi nhầm bên', $event->reason);
        $this->assertSame($this->owner->id, $event->actor_id);
        $this->assertSame([$semi->entry_one_id, $semi->entry_two_id], [$event->payload['before']['winner_entry_id'], $event->payload['after']['winner_entry_id']]);
        $this->assertSame([[21, 10], [21, 12]], $event->payload['before']['score']['games']);
        $this->getJson("/api/managed/tournaments/{$this->tournament->uuid}")->assertJsonPath('events.0.type', 'result.corrected');
    }

    public function test_br_157_a_result_whose_next_fixture_was_played_is_not_corrected(): void
    {
        $this->actingAs($this->owner);
        foreach ([$this->semi(1), $this->semi(2)] as $semi) {
            $this->postJson("/api/tournament-fixtures/{$semi->id}/result", ['games' => [[21, 10], [21, 12]]])->assertOk();
        }
        $final = TournamentFixture::where('round', 2)->firstOrFail();
        $this->postJson("/api/tournament-fixtures/{$final->id}/result", ['games' => [[21, 10], [21, 12]]])->assertOk()->assertJsonPath('tournament.status', 'finished');

        $this->postJson("/api/tournament-fixtures/{$this->semi(1)->id}/correction", ['games' => [[10, 21], [12, 21]], 'reason' => 'Sai'])
            ->assertConflict()->assertJsonPath('code', 'CORRECTION_BLOCKED');
        $this->postJson("/api/tournament-fixtures/{$final->id}/correction", ['games' => [[10, 21], [12, 21]], 'reason' => 'Sai'])->assertOk();
    }

    public function test_br_157_officials_do_not_correct_and_unplayed_fixtures_have_nothing_to_correct(): void
    {
        $semi = $this->semi(1);
        $official = $this->staffOf($this->court->venue);
        $this->grant($this->tournament, $official);
        $this->actingAs($official)->postJson("/api/tournament-fixtures/{$semi->id}/result", ['games' => [[21, 10], [21, 12]]])->assertOk();

        $this->postJson("/api/tournament-fixtures/{$semi->id}/correction", ['games' => [[10, 21], [12, 21]], 'reason' => 'Sai'])->assertForbidden();
        $this->actingAs($this->owner)->postJson("/api/tournament-fixtures/{$this->semi(2)->id}/correction", ['games' => [[10, 21], [12, 21]], 'reason' => 'Sai'])
            ->assertConflict()->assertJsonPath('code', 'FIXTURE_NOT_READY');
    }

    public function test_br_157_an_internal_tournament_has_no_correction(): void
    {
        $internal = $this->drawn($this->internalTournament($this->bookableCourt()), 2);
        $fixture = TournamentFixture::where('tournament_id', $internal->id)->firstOrFail();
        $representative = $internal->grants()->first()->user;
        $this->actingAs($representative)->postJson("/api/tournament-fixtures/{$fixture->id}/result", ['games' => [[21, 10], [21, 12]]])->assertOk();

        $this->postJson("/api/tournament-fixtures/{$fixture->id}/correction", ['games' => [[10, 21], [12, 21]], 'reason' => 'Sai'])->assertForbidden();
        $this->actingAs($this->ownerOf($internal->venue->organization))
            ->postJson("/api/tournament-fixtures/{$fixture->id}/correction", ['games' => [[10, 21], [12, 21]], 'reason' => 'Sai'])->assertForbidden();
    }
}
