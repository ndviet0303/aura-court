<?php

namespace Tests\Feature\Module07;

use App\Models\Court;
use App\Models\OwnerPaymentAttempt;
use App\Models\TournamentEntry;
use App\Models\TournamentEntryMember;
use App\Models\TournamentEvent;
use App\Models\TournamentFixture;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Module07\Concerns\RunsTournaments;
use Tests\TestCase;

/** FR-127, BR-153, BR-154, UC-7.28, UC-7.29: the roster is locked, then drawn into groups or a knockout bracket. */
class TournamentDrawTest extends TestCase
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

    public function test_br_153_a_public_roster_locks_on_paid_units_and_ends_open_payments(): void
    {
        $tournament = $this->tournament($this->court, ['status' => 'open']);
        $this->entry($tournament, TournamentEntry::STATUS_PAID);
        $approved = $this->entry($tournament, TournamentEntry::STATUS_APPROVED);
        $this->actingAs($approved->user)->postJson("/api/tournament-entries/{$approved->id}/payment")->assertOk();
        $this->actingAs($this->owner);

        $this->postJson("/api/managed/tournaments/{$tournament->uuid}/lock-roster")->assertUnprocessable()->assertJsonPath('code', 'ROSTER_TOO_SMALL');
        $this->entry($tournament, TournamentEntry::STATUS_PAID);
        $this->postJson("/api/managed/tournaments/{$tournament->uuid}/lock-roster")->assertOk()->assertJsonPath('tournament.status', 'roster_locked');

        $this->assertSame('expired', OwnerPaymentAttempt::value('status'));
        $this->postJson("/api/managed/tournaments/{$tournament->uuid}/lock-roster")->assertConflict()->assertJsonPath('code', 'ROSTER_LOCKED');
    }

    public function test_br_154_groups_play_a_single_round_robin_and_members_stay_as_registered(): void
    {
        $tournament = $this->tournament($this->court, ['status' => 'open', 'format' => 'group', 'group_count' => 2, 'advance_per_group' => 2]);
        $members = TournamentEntryMember::count();

        $this->drawn($tournament, 8);

        $this->assertSame('drawn', $tournament->status);
        foreach (['A', 'B'] as $group) {
            $this->assertSame(4, TournamentEntry::where('group', $group)->count());
            $this->assertSame(6, TournamentFixture::where('stage', 'group')->where('group', $group)->count());
        }
        $this->assertSame(0, TournamentFixture::where('stage', 'knockout')->count());
        $this->assertSame($members + 8, TournamentEntryMember::count());
        $pairs = TournamentFixture::get()->map(fn (TournamentFixture $fixture): string => collect([$fixture->entry_one_id, $fixture->entry_two_id])->sort()->implode('-'));
        $this->assertSame(12, $pairs->unique()->count());
    }

    public function test_br_154_a_knockout_uses_the_smallest_bracket_and_draws_the_byes(): void
    {
        $tournament = $this->drawn($this->tournament($this->court, ['status' => 'open']), 6);

        $this->assertSame([1 => 4, 2 => 2, 3 => 1], TournamentFixture::get()->countBy('round')->sortKeys()->all());
        $byes = TournamentFixture::where('status', 'bye')->get();
        $this->assertCount(2, $byes);
        foreach ($byes as $bye) {
            $this->assertSame($bye->entry_one_id, $bye->winner_entry_id);
        }
        $this->assertSame(2, TournamentFixture::where('round', 2)->get()->sum(fn (TournamentFixture $fixture): int => (int) ($fixture->entry_one_id !== null) + (int) ($fixture->entry_two_id !== null)));
        $this->getJson("/api/tournaments/{$tournament->uuid}")->assertJsonCount(7, 'fixtures');
    }

    public function test_br_153_a_redraw_is_allowed_only_before_any_result_and_before_play_starts(): void
    {
        $tournament = $this->drawn($this->tournament($this->court, ['status' => 'open']), 4);
        $this->actingAs($this->owner);

        $this->postJson("/api/managed/tournaments/{$tournament->uuid}/draw")->assertOk();
        $this->assertSame([false, true], TournamentEvent::where('type', 'draw')->orderBy('id')->get()->map(fn (TournamentEvent $event): bool => $event->payload['redraw'])->all());

        $fixture = TournamentFixture::where('round', 1)->first();
        $this->postJson("/api/tournament-fixtures/{$fixture->id}/result", ['games' => [[21, 15], [21, 18]]])->assertOk();
        $this->postJson("/api/managed/tournaments/{$tournament->uuid}/draw")->assertConflict()->assertJsonPath('code', 'DRAW_NOT_ALLOWED');

        $fresh = $this->drawn($this->tournament($this->court, ['status' => 'open']), 4);
        $this->travelTo($fresh->starts_at->copy()->addMinute());
        $this->postJson("/api/managed/tournaments/{$fresh->uuid}/draw")->assertConflict()->assertJsonPath('code', 'DRAW_NOT_ALLOWED');
    }

    public function test_uc_7_28_pre2_uc_7_29_pre3_lock_and_first_draw_only_before_the_start(): void
    {
        $open = $this->tournament($this->court, ['status' => 'open']);
        $this->entry($open, TournamentEntry::STATUS_PAID);
        $this->entry($open, TournamentEntry::STATUS_PAID);
        $locked = $this->tournament($this->court, ['status' => 'roster_locked']);
        $this->entry($locked, TournamentEntry::STATUS_PAID);
        $this->entry($locked, TournamentEntry::STATUS_PAID);
        $this->travelTo($open->starts_at->copy()->addMinute());
        $this->actingAs($this->owner);

        $this->getJson("/api/managed/tournaments/{$open->uuid}")->assertJsonPath('tournament.permissions.lock', false);
        $this->postJson("/api/managed/tournaments/{$open->uuid}/lock-roster")->assertConflict()->assertJsonPath('code', 'TOURNAMENT_STARTED');
        $this->getJson("/api/managed/tournaments/{$locked->uuid}")->assertJsonPath('tournament.permissions.draw', false);
        $this->postJson("/api/managed/tournaments/{$locked->uuid}/draw")->assertConflict()->assertJsonPath('code', 'DRAW_NOT_ALLOWED');
        $this->assertSame(0, TournamentFixture::count());
    }

    public function test_br_154_groups_need_two_units_each_and_enough_qualifiers(): void
    {
        $tournament = $this->tournament($this->court, ['status' => 'roster_locked', 'format' => 'group', 'group_count' => 2, 'advance_per_group' => 1]);
        foreach (range(1, 3) as $i) {
            $this->entry($tournament, TournamentEntry::STATUS_PAID);
        }

        $this->actingAs($this->owner)->postJson("/api/managed/tournaments/{$tournament->uuid}/draw")->assertConflict()->assertJsonPath('code', 'DRAW_NOT_ALLOWED');
        $this->assertSame(0, TournamentFixture::count());
    }

    public function test_uc_7_29_an_official_draws_but_a_stranger_cannot(): void
    {
        $tournament = $this->tournament($this->court, ['status' => 'roster_locked']);
        $this->entry($tournament, TournamentEntry::STATUS_PAID);
        $this->entry($tournament, TournamentEntry::STATUS_PAID);
        $official = $this->staffOf($this->court->venue);
        $this->grant($tournament, $official);

        $this->actingAs(User::factory()->create())->postJson("/api/managed/tournaments/{$tournament->uuid}/draw")->assertForbidden();
        $this->actingAs($official)->postJson("/api/managed/tournaments/{$tournament->uuid}/draw")->assertOk()->assertJsonPath('tournament.status', 'drawn')->assertJsonCount(1, 'fixtures');
    }
}
