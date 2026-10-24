<?php

namespace Tests\Feature\Module07;

use App\Models\Court;
use App\Models\TournamentEntry;
use App\Models\TournamentFixture;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Module07\Concerns\RunsTournaments;
use Tests\TestCase;

/** FR-129, BR-155, BR-156, UC-7.32: results by sport, walkovers, group tables and the knockout that follows. */
class TournamentResultTest extends TestCase
{
    use RefreshDatabase;
    use RunsTournaments;

    private Court $court;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-03 09:00:00');
        $this->court = $this->payableCourt();
        $this->owner = $this->ownerOf($this->court->venue->organization);
    }

    private function fixtureBetween(string $one, string $two): TournamentFixture
    {
        $ids = TournamentEntry::whereIn('name', [$one, $two])->pluck('id');

        return TournamentFixture::whereIn('entry_one_id', $ids)->whereIn('entry_two_id', $ids)->firstOrFail();
    }

    /** @param  array<string, mixed>  $score  as the request takes it, sides in the order given */
    private function record(string $one, string $two, array $score): void
    {
        $fixture = $this->fixtureBetween($one, $two);
        if (TournamentEntry::where('name', $one)->value('id') !== $fixture->entry_one_id) {
            $score = array_map(fn (array $pair): array => array_reverse($pair), $score);
        }
        $this->postJson("/api/tournament-fixtures/{$fixture->id}/result", $score)->assertOk();
    }

    public function test_br_155_badminton_has_no_drawn_game_and_ends_at_the_games_to_win(): void
    {
        $tournament = $this->drawn($this->tournament($this->court, ['status' => 'open']), 2);
        $final = TournamentFixture::firstOrFail();
        $this->actingAs($this->owner);
        $url = "/api/tournament-fixtures/{$final->id}/result";

        $this->postJson($url, ['games' => [[21, 21], [21, 15]]])->assertUnprocessable()->assertJsonPath('code', 'RESULT_INVALID');
        $this->postJson($url, ['games' => [[21, 15]]])->assertUnprocessable()->assertJsonPath('code', 'RESULT_INVALID');
        $this->postJson($url, ['games' => [[21, 15], [21, 18], [21, 3]]])->assertUnprocessable()->assertJsonPath('code', 'RESULT_INVALID');
        $this->postJson($url, ['games' => [[21, 15], [18, 21], [19, 21]]])
            ->assertOk()->assertJsonPath('fixture.winner_entry_id', $final->entry_two_id)->assertJsonPath('fixture.score.games.2', [19, 21])
            ->assertJsonPath('tournament.status', 'finished');
        $this->postJson($url, ['games' => [[21, 15], [21, 18]]])->assertConflict()->assertJsonPath('code', 'RESULT_EXISTS');
        $this->assertSame('finished', $tournament->fresh()->status);
    }

    public function test_fr_129_the_knockout_winner_moves_to_the_next_round(): void
    {
        $this->drawn($this->tournament($this->court, ['status' => 'open']), 4);
        $semi = TournamentFixture::where('round', 1)->where('position', 2)->firstOrFail();

        $this->actingAs($this->owner)->postJson("/api/tournament-fixtures/{$semi->id}/result", ['games' => [[21, 10], [21, 12]]])
            ->assertOk()->assertJsonPath('tournament.status', 'in_progress');

        $this->assertSame($semi->entry_one_id, TournamentFixture::where('round', 2)->value('entry_two_id'));
    }

    public function test_br_155_a_drawn_football_knockout_needs_a_shoot_out(): void
    {
        $this->drawn($this->tournament($this->court, ['status' => 'open', 'sport' => 'football', 'games_to_win' => null]), 2);
        $final = TournamentFixture::firstOrFail();
        $this->actingAs($this->owner);
        $url = "/api/tournament-fixtures/{$final->id}/result";

        $this->postJson($url, ['goals' => [1, 1]])->assertUnprocessable()->assertJsonPath('code', 'RESULT_INVALID');
        $this->postJson($url, ['goals' => [2, 1], 'shootout' => [4, 3]])->assertUnprocessable()->assertJsonPath('code', 'RESULT_INVALID');
        $this->postJson($url, ['goals' => [1, 1], 'shootout' => [3, 4]])
            ->assertOk()->assertJsonPath('fixture.winner_entry_id', $final->entry_two_id)->assertJsonPath('fixture.score.shootout', [3, 4]);
    }

    public function test_br_155_group_points_follow_the_setup_and_level_units_share_a_rank(): void
    {
        $tournament = $this->drawn($this->tournament($this->court, [
            'status' => 'open', 'sport' => 'football', 'games_to_win' => null, 'format' => 'group', 'group_count' => 1, 'advance_per_group' => 0,
            'points' => ['win' => 2, 'draw' => 1, 'loss' => 0], 'tiebreakers' => [],
        ]), 4);
        $this->actingAs($this->owner);

        $this->record('Đội 01', 'Đội 02', ['goals' => [2, 0]]);
        $this->record('Đội 03', 'Đội 04', ['goals' => [2, 0]]);
        $this->record('Đội 01', 'Đội 03', ['goals' => [1, 1]]);
        $this->record('Đội 02', 'Đội 04', ['goals' => [0, 0]]);
        $this->record('Đội 01', 'Đội 04', ['goals' => [0, 3]]);
        $this->record('Đội 02', 'Đội 03', ['goals' => [1, 0]]);

        $table = collect($this->getJson("/api/tournaments/{$tournament->uuid}")->assertOk()->json('standings.A'))->keyBy('entry.name');
        $this->assertSame([3, 3, 3, 3], $table->pluck('points')->values()->all());
        $this->assertSame([1, 1, 1, 1], $table->pluck('rank')->values()->all());
        $this->assertTrue($table['Đội 01']['tied']);
        $this->assertSame([1, 1, 1], [$table['Đội 01']['wins'], $table['Đội 01']['draws'], $table['Đội 01']['losses']]);
        $this->assertSame('finished', $tournament->fresh()->status);
    }

    public function test_br_155_tiebreakers_order_units_level_on_points(): void
    {
        $tournament = $this->drawn($this->tournament($this->court, [
            'status' => 'open', 'sport' => 'football', 'games_to_win' => null, 'format' => 'group', 'group_count' => 1, 'advance_per_group' => 0,
            'tiebreakers' => ['score_diff', 'score_for'],
        ]), 3);
        $this->actingAs($this->owner);

        $this->record('Đội 01', 'Đội 02', ['goals' => [3, 0]]);
        $this->record('Đội 02', 'Đội 03', ['goals' => [1, 0]]);
        $this->record('Đội 03', 'Đội 01', ['goals' => [1, 0]]);

        $table = $this->getJson("/api/tournaments/{$tournament->uuid}")->json('standings.A');
        $this->assertSame(['Đội 01', 'Đội 03', 'Đội 02'], array_column(array_column($table, 'entry'), 'name'));
        $this->assertSame([1, 2, 3], array_column($table, 'rank'));
    }

    public function test_br_156_a_walkover_is_a_win_and_a_loss_with_no_score(): void
    {
        $tournament = $this->drawn($this->tournament($this->court, [
            'status' => 'open', 'sport' => 'football', 'games_to_win' => null, 'format' => 'group', 'group_count' => 1, 'advance_per_group' => 0,
        ]), 2);
        $fixture = TournamentFixture::firstOrFail();
        $this->actingAs($this->owner);
        $url = "/api/tournament-fixtures/{$fixture->id}/walkover";

        $this->postJson($url, ['absent_entry_id' => $fixture->entry_one_id, 'goals' => [0, 3]])->assertUnprocessable()->assertJsonValidationErrors('goals');
        $this->postJson($url, ['absent_entry_id' => TournamentEntry::max('id') + 1])->assertUnprocessable()->assertJsonPath('code', 'RESULT_INVALID');
        $this->postJson($url, ['absent_entry_id' => $fixture->entry_one_id])
            ->assertOk()->assertJsonPath('fixture.forfeit_entry_id', $fixture->entry_one_id)->assertJsonPath('fixture.winner_entry_id', $fixture->entry_two_id)
            ->assertJsonPath('fixture.score', null);

        $table = collect($this->getJson("/api/tournaments/{$tournament->uuid}")->json('standings.A'))->keyBy('entry.id');
        $this->assertSame([1, 3, 0], [$table[$fixture->entry_two_id]['wins'], $table[$fixture->entry_two_id]['points'], $table[$fixture->entry_two_id]['score_for']]);
        $this->assertSame(1, $table[$fixture->entry_one_id]['losses']);
    }

    public function test_br_154_group_qualifiers_fill_the_knockout_winner_against_the_other_runner_up(): void
    {
        $tournament = $this->drawn($this->tournament($this->court, [
            'status' => 'open', 'format' => 'group', 'group_count' => 2, 'advance_per_group' => 2,
        ]), 4);
        $this->actingAs($this->owner);
        foreach (TournamentFixture::where('stage', 'group')->get() as $fixture) {
            $this->postJson("/api/tournament-fixtures/{$fixture->id}/result", ['games' => [[21, 10], [21, 12]]])->assertOk();
        }

        $winners = TournamentFixture::where('stage', 'group')->pluck('winner_entry_id', 'group');
        $semis = TournamentFixture::where('stage', 'knockout')->where('round', 1)->orderBy('position')->get();
        $this->assertCount(2, $semis);
        $this->assertSame([$winners['A'], $winners['B']], $semis->pluck('entry_one_id')->all());
        foreach ($semis as $semi) {
            $this->assertNotSame(TournamentEntry::find($semi->entry_one_id)->group, TournamentEntry::find($semi->entry_two_id)->group);
        }
        $this->assertSame('in_progress', $tournament->fresh()->status);
    }

    public function test_uc_7_32_an_official_records_results_and_a_stranger_cannot(): void
    {
        $tournament = $this->drawn($this->tournament($this->court, ['status' => 'open']), 2);
        $fixture = TournamentFixture::firstOrFail();
        $official = $this->staffOf($this->court->venue);
        $this->grant($tournament, $official);

        $this->actingAs(User::factory()->create())->postJson("/api/tournament-fixtures/{$fixture->id}/result", ['games' => [[21, 10], [21, 12]]])->assertForbidden();
        $this->actingAs($official)->postJson("/api/tournament-fixtures/{$fixture->id}/result", ['games' => [[21, 10], [21, 12]]])->assertOk();
    }
}
