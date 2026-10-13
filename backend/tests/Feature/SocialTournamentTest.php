<?php

namespace Tests\Feature;

use App\Models\MatchPost;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\QualifyingActivity;
use App\Models\User;
use App\Services\Social\ReputationService;
use App\Services\Tournament\TournamentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialTournamentTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_only_match_is_not_disclosed_to_a_non_invitee(): void
    {
        [$organization, $owner] = $this->tenant();
        $outsider = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $outsider->id, 'role' => 'customer', 'status' => 'active']);
        $post = MatchPost::create(['organization_id' => $organization->id, 'creator_id' => $owner->id, 'title' => 'Private match', 'visibility' => 'invite_only', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'required_participants' => 4, 'joined_count' => 1]);
        $this->actingAs($outsider)->withHeader('X-Organization-ID', (string) $organization->id)->getJson('/api/social/matches/'.$post->id)->assertNotFound();
    }

    public function test_reputation_uses_only_the_20_most_recent_qualifying_activities(): void
    {
        [$organization, $owner] = $this->tenant();
        for ($index = 0; $index < 21; $index++) {
            QualifyingActivity::create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'type' => 'attendance', 'score_delta' => 2, 'source_type' => 'test', 'source_id' => $index + 1, 'qualifies_at' => now()->subMinutes(21 - $index)]);
        }
        $profile = app(ReputationService::class)->recalculate($owner, $organization->id);
        $this->assertSame(100, $profile->reputation_score);
        $this->assertSame(20, QualifyingActivity::where('user_id', $owner->id)->count() - 1);
    }

    public function test_tournament_generation_is_deterministic_for_the_same_seed_order(): void
    {
        [$organization, $owner] = $this->tenant();
        $players = User::factory()->count(4)->create();
        foreach ($players as $player) {
            OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $player->id, 'role' => 'customer', 'status' => 'active']);
        }
        $first = app(TournamentService::class)->createAndGenerate($owner, $organization->id, 'A', 'single_elimination', $players->pluck('id')->all());
        $second = app(TournamentService::class)->createAndGenerate($owner, $organization->id, 'B', 'single_elimination', $players->pluck('id')->all());
        $firstOrder = $first->fixtures()->with(['participantOne', 'participantTwo'])->orderBy('round')->orderBy('position')->get()->map(fn ($fixture) => [$fixture->participantOne?->user_id, $fixture->participantTwo?->user_id])->all();
        $secondOrder = $second->fixtures()->with(['participantOne', 'participantTwo'])->orderBy('round')->orderBy('position')->get()->map(fn ($fixture) => [$fixture->participantOne?->user_id, $fixture->participantTwo?->user_id])->all();
        $this->assertSame($firstOrder, $secondOrder);
    }

    public function test_stale_tournament_score_version_is_rejected(): void
    {
        [$organization, $owner] = $this->tenant();
        $players = User::factory()->count(2)->create();
        foreach ($players as $player) {
            OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $player->id, 'role' => 'customer', 'status' => 'active']);
        }
        $tournament = app(TournamentService::class)->createAndGenerate($owner, $organization->id, 'Conflict', 'single_elimination', $players->pluck('id')->all());
        $fixture = $tournament->fixtures->firstWhere('status', 'pending');
        $this->actingAs($owner)->withHeaders(['X-Organization-ID' => (string) $organization->id, 'Idempotency-Key' => 'score-conflict'])->postJson("/api/tournaments/{$tournament->id}/fixtures/{$fixture->id}/score", ['expected_version' => 0, 'winner_id' => $fixture->participant_one_id, 'score' => ['winner' => 1]])->assertConflict();
    }

    private function tenant(): array
    {
        $organization = Organization::create(['name' => 'Social Tenant', 'slug' => 'social-'.uniqid()]);
        $owner = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);
        config(['features.pilot_organizations' => [(string) $organization->id], 'features.flags.social_matchmaking_tournaments' => true]);

        return [$organization, $owner];
    }
}
