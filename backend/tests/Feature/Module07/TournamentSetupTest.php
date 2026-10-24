<?php

namespace Tests\Feature\Module07;

use App\Models\Court;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Module07\Concerns\RunsTournaments;
use Tests\TestCase;

/** FR-120, BR-149, BR-152, UC-7.16, UC-7.18: the owner sets up, staffs and publishes a public tournament. */
class TournamentSetupTest extends TestCase
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

    public function test_fr_120_the_owner_creates_a_draft_that_only_managers_see(): void
    {
        $id = $this->actingAs($this->owner)->postJson('/api/managed/tournaments', $this->setupBody(['venue_id' => $this->court->venue_id]))
            ->assertCreated()
            ->assertJsonPath('tournament.status', 'draft')
            ->assertJsonPath('tournament.type', 'public')
            ->assertJsonPath('tournament.fee', 200000)
            ->assertJsonPath('tournament.my_role', 'owner')
            ->json('tournament.id');

        $this->getJson('/api/managed/tournaments')->assertJsonPath('data.0.id', $id);
        $this->actingAs(User::factory()->create())->getJson("/api/tournaments/{$id}")->assertNotFound();
        $this->getJson('/api/tournaments')->assertJsonCount(0, 'data');
    }

    public function test_br_149_setup_needs_every_field_of_its_sport_event_and_format(): void
    {
        $this->actingAs($this->owner);
        $venue = ['venue_id' => $this->court->venue_id];

        $this->postJson('/api/managed/tournaments', $venue + $this->setupBody(['games_to_win' => null, 'rules' => '', 'prizes' => null, 'fee' => 500]))
            ->assertUnprocessable()->assertJsonValidationErrors(['games_to_win', 'rules', 'prizes', 'fee']);
        $this->postJson('/api/managed/tournaments', $venue + $this->setupBody(['entry_type' => 'team', 'format' => 'group']))
            ->assertUnprocessable()->assertJsonValidationErrors(['team_main', 'team_reserve', 'group_count', 'advance_per_group']);
        $this->postJson('/api/managed/tournaments', $venue + $this->setupBody(['sport' => 'tennis', 'ends_at' => now()->addDays(9)->toIso8601String()]))
            ->assertUnprocessable()->assertJsonValidationErrors(['sport', 'ends_at']);
    }

    public function test_fr_120_only_the_venue_owner_creates_and_edits_and_only_while_draft(): void
    {
        $this->actingAs($this->staffOf($this->court->venue))
            ->postJson('/api/managed/tournaments', $this->setupBody(['venue_id' => $this->court->venue_id]))->assertForbidden();
        $open = $this->tournament($this->court, ['status' => 'open']);

        $this->actingAs($this->owner)->putJson("/api/managed/tournaments/{$open->uuid}", $this->setupBody())
            ->assertConflict()->assertJsonPath('code', 'TOURNAMENT_NOT_DRAFT');
    }

    public function test_br_149_br_152_publishing_needs_court_time_a_receiver_and_ordered_deadlines(): void
    {
        $draft = $this->tournament($this->court);
        $this->actingAs($this->owner);

        $this->postJson("/api/managed/tournaments/{$draft->uuid}/publish")->assertUnprocessable()->assertJsonPath('code', 'TOURNAMENT_NO_COURTS');
        $this->allocation($draft, $this->court);
        $draft->update(['payment_deadline' => now()->addDays(4)]);
        $this->postJson("/api/managed/tournaments/{$draft->uuid}/publish")->assertUnprocessable()->assertJsonPath('code', 'TOURNAMENT_DEADLINES');
        $draft->update(['payment_deadline' => now()->addDays(6)]);

        $this->postJson("/api/managed/tournaments/{$draft->uuid}/publish")->assertOk()->assertJsonPath('tournament.status', 'open')->assertJsonPath('tournament.can_register', true);
        $this->getJson('/api/tournaments')->assertJsonPath('data.0.id', $draft->uuid);
    }

    public function test_br_148_a_venue_without_a_verified_receiver_cannot_publish(): void
    {
        $court = $this->bookableCourt();
        $draft = $this->tournament($court);
        $this->allocation($draft, $court);

        $this->actingAs($this->ownerOf($court->venue->organization))->postJson("/api/managed/tournaments/{$draft->uuid}/publish")
            ->assertUnprocessable()->assertJsonPath('code', 'TOURNAMENT_NO_RECEIVER');
    }

    public function test_uc_7_18_officials_are_staff_of_the_venue_and_run_play_but_not_setup(): void
    {
        $tournament = $this->tournament($this->court, ['status' => 'open']);
        $staff = $this->staffOf($this->court->venue);
        $outsider = User::factory()->create();
        $this->actingAs($this->owner);

        $this->postJson("/api/managed/tournaments/{$tournament->uuid}/grants", ['role' => 'official', 'email' => $outsider->email])
            ->assertUnprocessable()->assertJsonPath('code', 'GRANT_INVALID');
        $this->postJson("/api/managed/tournaments/{$tournament->uuid}/grants", ['role' => 'representative', 'email' => $staff->email])
            ->assertUnprocessable()->assertJsonPath('code', 'GRANT_INVALID');
        $this->postJson("/api/managed/tournaments/{$tournament->uuid}/grants", ['role' => 'official', 'email' => $staff->email])->assertCreated();

        $this->actingAs($staff)->getJson("/api/managed/tournaments/{$tournament->uuid}")
            ->assertOk()->assertJsonPath('tournament.my_role', 'official')
            ->assertJsonPath('tournament.permissions.review', false)->assertJsonPath('tournament.permissions.stop', false);
        $this->postJson("/api/managed/tournaments/{$tournament->uuid}/stop")->assertForbidden();
        $this->postJson("/api/managed/tournaments/{$tournament->uuid}/allocations", [
            'court_id' => $this->court->id, 'starts_at' => now()->addDays(12)->toIso8601String(), 'ends_at' => now()->addDays(12)->addHours(2)->toIso8601String(),
        ])->assertForbidden();
    }
}
