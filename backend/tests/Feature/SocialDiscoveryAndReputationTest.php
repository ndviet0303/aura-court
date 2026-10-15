<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\MatchInvitation;
use App\Models\MatchPost;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\QualifyingActivity;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialDiscoveryAndReputationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $tenant;

    private User $creator;

    private User $playerA;

    private User $playerB;

    private User $stranger;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Organization::create([
            'name' => 'Aura Social League',
            'slug' => 'aura-league-'.uniqid(),
            'status' => 'active',
        ]);

        config([
            'features.flags.social_matchmaking_tournaments' => true,
            'features.pilot_organizations' => [(string) $this->tenant->id],
        ]);

        $this->creator = User::factory()->create(['name' => 'Creator User', 'email' => 'creator@test.vn']);
        $this->playerA = User::factory()->create(['name' => 'Player A', 'email' => 'playera@test.vn']);
        $this->playerB = User::factory()->create(['name' => 'Player B', 'email' => 'playerb@test.vn']);
        $this->stranger = User::factory()->create(['name' => 'Stranger User', 'email' => 'stranger@test.vn']);
        $this->moderator = User::factory()->create(['name' => 'Moderator Manager', 'email' => 'mod@test.vn']);

        foreach ([$this->creator, $this->playerA, $this->playerB, $this->stranger] as $u) {
            OrganizationMember::create([
                'organization_id' => $this->tenant->id,
                'user_id' => $u->id,
                'role' => OrganizationMember::ROLE_CUSTOMER,
                'status' => 'active',
            ]);
        }

        OrganizationMember::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->moderator->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);
    }

    private function headers(string $prefix = 'soc-'): array
    {
        return [
            'X-Organization-Id' => (string) $this->tenant->id,
            'Idempotency-Key' => uniqid($prefix),
        ];
    }

    public function test_player_profile_updates_and_retrieval_with_skill_bounds(): void
    {
        // 1. Get initial profile
        $getRes = $this->actingAs($this->playerA)
            ->withHeaders($this->headers('prof-get-'))
            ->getJson('/api/social/profile');
        $getRes->assertOk();
        $this->assertSame(1, $getRes->json('skill_level'));
        $this->assertSame(60, $getRes->json('reputation_score'));

        // 2. Skill level out of bounds fails validation
        $invalidRes = $this->actingAs($this->playerA)
            ->withHeaders($this->headers('prof-inv-'))
            ->putJson('/api/social/profile', [
                'skill_level' => 6, // max is 5
            ]);
        $invalidRes->assertStatus(422);

        // 3. Valid update
        $updateRes = $this->actingAs($this->playerA)
            ->withHeaders($this->headers('prof-ok-'))
            ->putJson('/api/social/profile', [
                'skill_level' => 4,
                'city' => 'Hà Nội',
                'visibility' => 'organization',
            ]);
        $updateRes->assertOk();
        $updateRes->assertJsonPath('profile.skill_level', 4);
        $updateRes->assertJsonPath('profile.city', 'Hà Nội');
    }

    public function test_discovery_filters_by_city_skill_range_and_excludes_full_matches(): void
    {
        // Match 1: Ha Noi, skill 2-4, 2/4 joined (Open)
        $m1 = MatchPost::create([
            'organization_id' => $this->tenant->id,
            'creator_id' => $this->creator->id,
            'title' => 'Kèo Hà Nội Trình Độ 2-4',
            'visibility' => 'public',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'city' => 'Hà Nội',
            'min_skill' => 2,
            'max_skill' => 4,
            'required_participants' => 4,
            'joined_count' => 2,
            'status' => 'open',
        ]);

        // Match 2: TP Ho Chi Minh, skill 1-3, 1/4 joined (Open)
        $m2 = MatchPost::create([
            'organization_id' => $this->tenant->id,
            'creator_id' => $this->creator->id,
            'title' => 'Kèo Sài Gòn Trình Độ 1-3',
            'visibility' => 'public',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'city' => 'TP. Hồ Chí Minh',
            'min_skill' => 1,
            'max_skill' => 3,
            'required_participants' => 4,
            'joined_count' => 1,
            'status' => 'open',
        ]);

        // Match 3: Ha Noi, 4/4 joined (Full)
        $m3 = MatchPost::create([
            'organization_id' => $this->tenant->id,
            'creator_id' => $this->creator->id,
            'title' => 'Kèo Hà Nội Đã Đủ Người',
            'visibility' => 'public',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'city' => 'Hà Nội',
            'min_skill' => 2,
            'max_skill' => 4,
            'required_participants' => 4,
            'joined_count' => 4,
            'status' => 'full',
        ]);

        // Query discovery with city 'Hà Nội': should return M1 only (M2 wrong city, M3 full)
        $resHanoi = $this->actingAs($this->playerA)
            ->withHeaders($this->headers('disc-hn-'))
            ->getJson('/api/social/matches?city='.urlencode('Hà Nội'));
        $resHanoi->assertOk();
        $matches = collect($resHanoi->json('matches'));
        $this->assertTrue($matches->contains('id', $m1->id));
        $this->assertFalse($matches->contains('id', $m2->id));
        $this->assertFalse($matches->contains('id', $m3->id));

        // Query discovery with skill filter min_skill=4: M1 matches (max_skill=4 >= 4), M2 excluded (max_skill=3 < 4)
        $resSkill = $this->actingAs($this->playerA)
            ->withHeaders($this->headers('disc-sk-'))
            ->getJson('/api/social/matches?min_skill=4');
        $resSkill->assertOk();
        $skillMatches = collect($resSkill->json('matches'));
        $this->assertTrue($skillMatches->contains('id', $m1->id));
        $this->assertFalse($skillMatches->contains('id', $m2->id));
    }

    public function test_join_capacity_enforcement_prevents_over_joining_when_full(): void
    {
        $match = MatchPost::create([
            'organization_id' => $this->tenant->id,
            'creator_id' => $this->creator->id,
            'title' => 'Trận đánh đơn 1v1',
            'visibility' => 'public',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(1),
            'required_participants' => 2,
            'joined_count' => 1,
            'status' => 'open',
        ]);

        // Player A joins -> reaches 2/2 (becomes full)
        $joinA = $this->actingAs($this->playerA)
            ->withHeaders($this->headers('join-a-'))
            ->postJson("/api/social/matches/{$match->id}/join");
        $joinA->assertCreated();

        $this->assertSame(2, $match->fresh()->joined_count);
        $this->assertSame('full', $match->fresh()->status);

        // Player B tries to join -> 409 Conflict (Match is full)
        $joinB = $this->actingAs($this->playerB)
            ->withHeaders($this->headers('join-b-'))
            ->postJson("/api/social/matches/{$match->id}/join");
        $joinB->assertStatus(409);
        $this->assertStringContainsString('full', $joinB->json('message'));
    }

    public function test_invite_only_access_control(): void
    {
        $inviteOnlyMatch = MatchPost::create([
            'organization_id' => $this->tenant->id,
            'creator_id' => $this->creator->id,
            'title' => 'Trận kín chỉ người được mời',
            'visibility' => 'invite_only',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'required_participants' => 4,
            'joined_count' => 1,
            'status' => 'open',
        ]);

        // 1. Not visible in public discovery
        $discRes = $this->actingAs($this->stranger)
            ->withHeaders($this->headers('disc-inv-'))
            ->getJson('/api/social/matches');
        $discRes->assertOk();
        $this->assertFalse(collect($discRes->json('matches'))->contains('id', $inviteOnlyMatch->id));

        // 2. Stranger gets 404 when attempting to view directly
        $strangerShow = $this->actingAs($this->stranger)
            ->withHeaders($this->headers('show-stranger-'))
            ->getJson("/api/social/matches/{$inviteOnlyMatch->id}");
        $strangerShow->assertStatus(404);

        // 3. Creator can view directly
        $creatorShow = $this->actingAs($this->creator)
            ->withHeaders($this->headers('show-creator-'))
            ->getJson("/api/social/matches/{$inviteOnlyMatch->id}");
        $creatorShow->assertOk();

        // 4. Invite Player A
        MatchInvitation::create([
            'organization_id' => $this->tenant->id,
            'match_post_id' => $inviteOnlyMatch->id,
            'invited_by' => $this->creator->id,
            'invitee_id' => $this->playerA->id,
            'status' => 'pending',
        ]);

        // Player A can now view the match
        $inviteeShow = $this->actingAs($this->playerA)
            ->withHeaders($this->headers('show-invitee-'))
            ->getJson("/api/social/matches/{$inviteOnlyMatch->id}");
        $inviteeShow->assertOk();

        // Stranger cannot join directly -> 404
        $strangerJoin = $this->actingAs($this->stranger)
            ->withHeaders($this->headers('join-stranger-'))
            ->postJson("/api/social/matches/{$inviteOnlyMatch->id}/join");
        $strangerJoin->assertStatus(404);
    }

    public function test_reputation_activity_and_dispute_lifecycle(): void
    {
        $venue = Venue::create([
            'organization_id' => $this->tenant->id,
            'name' => 'Aura Stadium',
            'slug' => 'aura-stadium-'.uniqid(),
            'status' => 'active',
        ]);
        $court = Court::create([
            'venue_id' => $venue->id,
            'name' => 'Court 1',
            'is_active' => true,
        ]);
        $res1 = Reservation::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->playerA->id,
            'court_id' => $court->id,
            'code' => 'RES-A-101',
            'status' => 'confirmed',
            'total_amount' => 100000,
        ]);
        $res2 = Reservation::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->playerA->id,
            'court_id' => $court->id,
            'code' => 'RES-A-102',
            'status' => 'confirmed',
            'total_amount' => 100000,
        ]);

        // 1. Staff/Manager records attendance (+2 reputation)
        $recRes = $this->actingAs($this->moderator)
            ->withHeaders($this->headers('rep-rec-'))
            ->postJson('/api/social/reputation/activity', [
                'user_id' => $this->playerA->id,
                'type' => 'attendance',
                'source_type' => 'reservation',
                'source_id' => $res1->id,
            ]);
        $recRes->assertOk();
        // Baseline 60 + 2 = 62
        $this->assertSame(62, $recRes->json('profile.reputation_score'));

        // 2. Staff/Manager records no_show (-15 reputation)
        $noShowRes = $this->actingAs($this->moderator)
            ->withHeaders($this->headers('rep-noshow-'))
            ->postJson('/api/social/reputation/activity', [
                'user_id' => $this->playerA->id,
                'type' => 'no_show',
                'source_type' => 'reservation',
                'source_id' => $res2->id,
            ]);
        $noShowRes->assertOk();
        // 60 + 2 - 15 = 47
        $this->assertSame(47, $noShowRes->json('profile.reputation_score'));

        $noShowActivity = QualifyingActivity::where('user_id', $this->playerA->id)
            ->where('type', 'no_show')
            ->firstOrFail();

        // 3. Unauthorized user (Stranger) tries to dispute Player A's activity -> 403
        $unauthDispute = $this->actingAs($this->stranger)
            ->withHeaders($this->headers('disp-unauth-'))
            ->postJson("/api/social/reputation/activities/{$noShowActivity->id}/dispute", [
                'reason' => 'Không phải lỗi của tôi',
            ]);
        $unauthDispute->assertStatus(403);

        // 4. Player A files legitimate dispute
        $disputeRes = $this->actingAs($this->playerA)
            ->withHeaders($this->headers('disp-ok-'))
            ->postJson("/api/social/reputation/activities/{$noShowActivity->id}/dispute", [
                'reason' => 'Tôi có mặt đúng giờ nhưng nhân viên quên quét check-in',
            ]);
        $disputeRes->assertCreated();
        $disputeId = $disputeRes->json('dispute.id');

        // 5. Manager resolves dispute as 'upheld' (player was in the right)
        // Upheld penalty applies to the original fault context, recording an audited resolution
        $resolveRes = $this->actingAs($this->moderator)
            ->withHeaders($this->headers('disp-resolve-'))
            ->postJson("/api/social/reputation/disputes/{$disputeId}/resolve", [
                'decision' => 'upheld',
                'resolution' => 'Đã kiểm tra camera, khách có mặt đúng giờ',
            ]);
        $resolveRes->assertOk();
        $resolveRes->assertJsonPath('dispute.decision', 'upheld');
        $resolveRes->assertJsonPath('dispute.status', 'resolved');

        // Moderation record was created
        $this->assertDatabaseHas('moderation_records', [
            'organization_id' => $this->tenant->id,
            'actor_id' => $this->moderator->id,
            'action' => 'reputation_dispute_resolved',
        ]);
    }
}
