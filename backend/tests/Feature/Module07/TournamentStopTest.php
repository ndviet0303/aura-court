<?php

namespace Tests\Feature\Module07;

use App\Models\Court;
use App\Models\OwnerPaymentAttempt;
use App\Models\TournamentEntry;
use App\Models\TournamentEvent;
use App\Models\TournamentFixture;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Module07\Concerns\RunsTournaments;
use Tests\TestCase;

/** FR-131, BR-156, UC-7.34: a stopped tournament keeps its data, plays no further and frees its court time. */
class TournamentStopTest extends TestCase
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

    public function test_fr_131_the_owner_stops_a_public_tournament_and_its_results_stay(): void
    {
        $tournament = $this->drawn($this->tournament($this->court, ['status' => 'open']), 4);
        [$played, $open] = TournamentFixture::where('round', 1)->orderBy('position')->get()->all();
        $this->actingAs($this->owner)->postJson("/api/tournament-fixtures/{$played->id}/result", ['games' => [[21, 10], [21, 12]]])->assertOk();

        $this->postJson("/api/managed/tournaments/{$tournament->uuid}/stop", ['reason' => 'Mưa bão'])
            ->assertOk()->assertJsonPath('tournament.status', 'stopped')->assertJsonPath('tournament.stop_reason', 'Mưa bão');

        $this->postJson("/api/tournament-fixtures/{$open->id}/result", ['games' => [[21, 10], [21, 12]]])->assertConflict()->assertJsonPath('code', 'TOURNAMENT_STOPPED');
        $this->assertSame(['completed', 'pending'], [$played->fresh()->status, $open->fresh()->status]);
        $this->assertNull(TournamentFixture::where('round', 2)->value('winner_entry_id'));
        $this->assertSame(['from' => 'in_progress'], TournamentEvent::where('type', 'tournament.stopped')->value('payload'));
        $this->getJson("/api/tournaments/{$tournament->uuid}")->assertOk()->assertJsonPath('tournament.status', 'stopped')->assertJsonCount(3, 'fixtures');
        $this->postJson("/api/managed/tournaments/{$tournament->uuid}/stop")->assertConflict()->assertJsonPath('code', 'TOURNAMENT_STOPPED');
    }

    public function test_br_156_stopping_ends_open_payments_and_frees_reserved_court_time(): void
    {
        $tournament = $this->tournament($this->court, ['status' => 'open']);
        $this->allocation($tournament, $this->court, 2, 8, 12);
        $entry = $this->entry($tournament, TournamentEntry::STATUS_APPROVED);
        $this->actingAs($entry->user)->postJson("/api/tournament-entries/{$entry->id}/payment")->assertOk();

        $this->actingAs($this->owner)->postJson("/api/managed/tournaments/{$tournament->uuid}/stop")->assertOk();

        $this->assertSame('expired', OwnerPaymentAttempt::value('status'));
        $this->checkout($this->customer(), $this->court, '2026-11-05 09:00', '2026-11-05 10:00', 100000)->assertCreated();
    }

    public function test_fr_131_the_representative_stops_an_internal_tournament_but_an_official_cannot_stop(): void
    {
        $internal = $this->internalTournament($this->bookableCourt());
        $public = $this->tournament($this->court, ['status' => 'open']);
        $official = $this->staffOf($this->court->venue);
        $this->grant($public, $official);

        $this->actingAs($official)->postJson("/api/managed/tournaments/{$public->uuid}/stop")->assertForbidden();
        $this->actingAs($internal->grants()->first()->user)->postJson("/api/managed/tournaments/{$internal->uuid}/stop", ['reason' => 'Hoãn'])
            ->assertOk()->assertJsonPath('tournament.status', 'stopped');
    }

    public function test_fr_131_a_finished_tournament_is_not_stopped(): void
    {
        $tournament = $this->drawn($this->tournament($this->court, ['status' => 'open']), 2);
        $this->actingAs($this->owner)->postJson('/api/tournament-fixtures/'.TournamentFixture::value('id').'/result', ['games' => [[21, 10], [21, 12]]])
            ->assertJsonPath('tournament.status', 'finished');

        $this->postJson("/api/managed/tournaments/{$tournament->uuid}/stop")->assertConflict()->assertJsonPath('code', 'TOURNAMENT_FINISHED');
    }
}
