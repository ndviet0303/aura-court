<?php

namespace Tests\Feature\Module07;

use App\Models\Court;
use App\Models\TournamentEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Module07\Concerns\RunsTournaments;
use Tests\TestCase;

/** FR-122, BR-148, UC-7.20: a published public tournament for everyone; drafts and internal ones only for those in them. */
class TournamentVisibilityTest extends TestCase
{
    use RefreshDatabase;
    use RunsTournaments;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-03 09:00:00');
        $this->court = $this->payableCourt();
    }

    public function test_fr_122_a_guest_sees_a_published_tournament_and_its_units_without_their_accounts(): void
    {
        $tournament = $this->tournament($this->court, ['status' => 'open', 'name' => 'Giải mở rộng']);
        $paid = $this->entry($tournament, TournamentEntry::STATUS_PAID, name: 'Đội Sấm Sét');
        $this->entry($tournament, TournamentEntry::STATUS_SUBMITTED, name: 'Chờ duyệt');

        $response = $this->getJson("/api/tournaments/{$tournament->uuid}")->assertOk()
            ->assertJsonPath('tournament.name', 'Giải mở rộng')->assertJsonPath('tournament.my_role', null)
            ->assertJsonCount(1, 'entries')->assertJsonPath('entries.0.name', 'Đội Sấm Sét')
            ->assertJsonPath('entries.0.members.0.full_name', $paid->members->first()->full_name);

        $this->assertSame(['id', 'name', 'group', 'seed', 'members'], array_keys($response->json('entries.0')));
        $this->assertSame(['full_name', 'role'], array_keys($response->json('entries.0.members.0')));
        $this->assertStringNotContainsString($paid->user->email, $response->getContent());
    }

    public function test_fr_122_the_list_shows_published_public_tournaments_by_sport(): void
    {
        $this->tournament($this->court, ['status' => 'open', 'name' => 'Cầu lông']);
        $this->tournament($this->court, ['status' => 'open', 'name' => 'Bóng đá', 'sport' => 'football', 'games_to_win' => null]);
        $this->tournament($this->court, ['name' => 'Nháp']);
        $this->internalTournament($this->court, ['name' => 'Nội bộ']);

        $this->getJson('/api/tournaments')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/tournaments?sport=football')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Bóng đá');
    }

    public function test_br_148_an_internal_tournament_is_seen_only_by_its_renter_managers_and_venue_operators(): void
    {
        $tournament = $this->internalTournament($this->court);
        $renter = $tournament->rentals()->first()->reservation->user;
        $url = "/api/tournaments/{$tournament->uuid}";

        $this->getJson($url)->assertNotFound();
        $this->actingAs(User::factory()->create())->getJson($url)->assertNotFound();
        foreach ([[$renter, 'representative'], [$this->staffOf($this->court->venue), null], [$this->ownerOf($this->court->venue->organization), 'owner']] as [$user, $role]) {
            $this->app['auth']->forgetGuards();
            $this->actingAs($user)->getJson($url)->assertOk()->assertJsonPath('tournament.my_role', $role);
        }
    }

    public function test_fr_122_a_stranger_does_not_manage_and_a_draft_stays_hidden(): void
    {
        $draft = $this->tournament($this->court);
        $stranger = User::factory()->create();

        $this->getJson("/api/tournaments/{$draft->uuid}")->assertNotFound();
        $this->actingAs($stranger)->getJson("/api/managed/tournaments/{$draft->uuid}")->assertNotFound();
        $this->getJson('/api/managed/tournaments')->assertOk()->assertJsonCount(0, 'data');
    }
}
