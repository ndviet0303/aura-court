<?php

namespace Tests\Feature;

use App\Models\MatchInvitation;
use App\Models\MatchPost;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchInvitationAndResponseTest extends TestCase
{
    use RefreshDatabase;

    private Organization $tenant;

    private User $host;

    private User $player1;

    private User $player2;

    private User $stranger;

    private MatchPost $match;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Organization::create([
            'name' => 'Aura Matchmaking Arena',
            'slug' => 'aura-match-'.uniqid(),
            'status' => 'active',
        ]);

        config([
            'features.flags.social_matchmaking_tournaments' => true,
            'features.pilot_organizations' => [(string) $this->tenant->id],
        ]);

        $this->host = User::factory()->create(['name' => 'Host User', 'email' => 'host@test.vn']);
        $this->player1 = User::factory()->create(['name' => 'Player One', 'email' => 'p1@test.vn']);
        $this->player2 = User::factory()->create(['name' => 'Player Two', 'email' => 'p2@test.vn']);
        $this->stranger = User::factory()->create(['name' => 'Stranger User', 'email' => 'stranger@test.vn']);

        foreach ([$this->host, $this->player1, $this->player2, $this->stranger] as $u) {
            OrganizationMember::create([
                'organization_id' => $this->tenant->id,
                'user_id' => $u->id,
                'role' => OrganizationMember::ROLE_CUSTOMER,
                'status' => 'active',
            ]);
        }

        $this->match = MatchPost::create([
            'organization_id' => $this->tenant->id,
            'creator_id' => $this->host->id,
            'title' => 'Trận giao lưu đôi nam nữ 2v2',
            'visibility' => 'invite_only',
            'starts_at' => now()->addDay()->setHour(18)->setMinute(0),
            'ends_at' => now()->addDay()->setHour(20)->setMinute(0),
            'required_participants' => 4,
            'joined_count' => 1,
            'status' => 'open',
        ]);
    }

    private function headers(string $prefix = 'match-'): array
    {
        return [
            'X-Organization-Id' => (string) $this->tenant->id,
            'Idempotency-Key' => uniqid($prefix),
        ];
    }

    public function test_batch_match_invitations_with_individual_failure_reporting(): void
    {
        // Host invites Player 1 (valid), non-existent ID 99999 (invalid), and Host himself (invalid)
        $response = $this->actingAs($this->host)
            ->withHeaders($this->headers('batch-inv-'))
            ->postJson("/api/v2/social/matches/{$this->match->id}/invite", [
                'invitee_ids' => [
                    $this->player1->id,
                    99999,
                    $this->host->id,
                ],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('summary.total', 3);
        $response->assertJsonPath('summary.invited', 1);
        $response->assertJsonPath('summary.failed', 2);

        $results = collect($response->json('results'));

        // Player 1 succeeded
        $p1Result = $results->firstWhere('invitee_id', $this->player1->id);
        $this->assertNotNull($p1Result);
        $this->assertSame('invited', $p1Result['status']);
        $this->assertArrayHasKey('invitation_id', $p1Result);

        // Non-existent user failed
        $missingResult = $results->firstWhere('invitee_id', 99999);
        $this->assertNotNull($missingResult);
        $this->assertSame('failed', $missingResult['status']);
        $this->assertStringContainsString('not found', $missingResult['reason']);

        // Host self-invite failed
        $selfResult = $results->firstWhere('invitee_id', $this->host->id);
        $this->assertNotNull($selfResult);
        $this->assertSame('failed', $selfResult['status']);
        $this->assertStringContainsString('creator', $selfResult['reason']);

        // Database has invitation only for Player 1
        $this->assertDatabaseHas('match_invitations', [
            'match_post_id' => $this->match->id,
            'invitee_id' => $this->player1->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('match_invitations', [
            'match_post_id' => $this->match->id,
            'invitee_id' => $this->host->id,
        ]);
    }

    public function test_batch_invitations_report_failure_when_match_is_full(): void
    {
        // Fill match
        $this->match->update([
            'joined_count' => 4,
            'status' => 'full',
        ]);

        $response = $this->actingAs($this->host)
            ->withHeaders($this->headers('full-inv-'))
            ->postJson("/api/v2/social/matches/{$this->match->id}/invite", [
                'invitee_ids' => [$this->player1->id],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('summary.failed', 1);
        $response->assertJsonPath('results.0.status', 'failed');
        $this->assertStringContainsString('full', $response->json('results.0.reason'));
    }

    public function test_addressee_can_accept_match_invitation_and_join(): void
    {
        $invitation = MatchInvitation::create([
            'organization_id' => $this->tenant->id,
            'match_post_id' => $this->match->id,
            'invited_by' => $this->host->id,
            'invitee_id' => $this->player1->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->player1)
            ->withHeaders($this->headers('resp-acc-'))
            ->postJson("/api/v2/social/invitations/{$invitation->id}/respond", [
                'status' => 'accepted',
            ]);

        $response->assertOk();
        $response->assertJsonPath('invitation.status', 'accepted');

        $this->assertDatabaseHas('match_participations', [
            'match_post_id' => $this->match->id,
            'user_id' => $this->player1->id,
            'status' => 'joined',
        ]);

        $this->assertSame(2, $this->match->fresh()->joined_count);
    }

    public function test_addressee_can_decline_match_invitation(): void
    {
        $invitation = MatchInvitation::create([
            'organization_id' => $this->tenant->id,
            'match_post_id' => $this->match->id,
            'invited_by' => $this->host->id,
            'invitee_id' => $this->player1->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->player1)
            ->withHeaders($this->headers('resp-dec-'))
            ->postJson("/api/v2/social/invitations/{$invitation->id}/respond", [
                'status' => 'declined',
            ]);

        $response->assertOk();
        $response->assertJsonPath('invitation.status', 'declined');

        $this->assertDatabaseMissing('match_participations', [
            'match_post_id' => $this->match->id,
            'user_id' => $this->player1->id,
        ]);

        $this->assertSame(1, $this->match->fresh()->joined_count);
    }

    public function test_unauthorized_user_cannot_respond_to_invitation(): void
    {
        $invitation = MatchInvitation::create([
            'organization_id' => $this->tenant->id,
            'match_post_id' => $this->match->id,
            'invited_by' => $this->host->id,
            'invitee_id' => $this->player1->id,
            'status' => 'pending',
        ]);

        // Stranger attempts to accept invitation meant for player1
        $response = $this->actingAs($this->stranger)
            ->withHeaders($this->headers('unauth-resp-'))
            ->postJson("/api/v2/social/invitations/{$invitation->id}/respond", [
                'status' => 'accepted',
            ]);

        $response->assertStatus(403);

        // Invitation status remains pending and no participation was created
        $this->assertSame('pending', $invitation->fresh()->status);
        $this->assertDatabaseMissing('match_participations', [
            'match_post_id' => $this->match->id,
            'user_id' => $this->stranger->id,
        ]);
        $this->assertSame(1, $this->match->fresh()->joined_count);
    }
}
