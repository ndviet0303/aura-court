<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\CourtBlock;
use App\Models\MatchPost;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerHostedDropInSessionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private Venue $venueA;

    private Court $courtA;

    private User $ownerA;

    private User $managerA;

    private User $staffA;

    private User $ownerB;

    private User $player1;

    private User $player2;

    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::create([
            'name' => 'Sân Cầu Lông A',
            'slug' => 'san-cau-long-a-'.uniqid(),
            'status' => 'active',
        ]);

        $this->orgB = Organization::create([
            'name' => 'Sân Cầu Lông B',
            'slug' => 'san-cau-long-b-'.uniqid(),
            'status' => 'active',
        ]);

        config([
            'features.flags.social_matchmaking_tournaments' => true,
            'features.pilot_organizations' => [(string) $this->orgA->id, (string) $this->orgB->id],
        ]);

        $this->venueA = Venue::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Cơ sở A',
            'slug' => 'co-so-a-'.uniqid(),
            'address' => '123 Đường Cầu Lông, TP.HCM',
            'is_active' => true,
            'status' => 'active',
        ]);

        $this->courtA = Court::create([
            'venue_id' => $this->venueA->id,
            'name' => 'Sân 1',
            'slug' => 'san-1-'.uniqid(),
            'is_active' => true,
        ]);

        $this->ownerA = User::factory()->create(['name' => 'Chủ Cơ Sở A', 'email' => 'ownerA@test.vn']);
        $this->managerA = User::factory()->create(['name' => 'Quản Lý A', 'email' => 'managerA@test.vn']);
        $this->staffA = User::factory()->create(['name' => 'Nhân Viên A', 'email' => 'staffA@test.vn']);
        $this->ownerB = User::factory()->create(['name' => 'Chủ Cơ Sở B', 'email' => 'ownerB@test.vn']);
        $this->player1 = User::factory()->create(['name' => 'Khách 1', 'email' => 'player1@test.vn']);
        $this->player2 = User::factory()->create(['name' => 'Khách 2', 'email' => 'player2@test.vn']);
        $this->stranger = User::factory()->create(['name' => 'Người Lạ', 'email' => 'stranger@test.vn']);

        OrganizationMember::create(['organization_id' => $this->orgA->id, 'user_id' => $this->ownerA->id, 'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER, 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $this->orgA->id, 'user_id' => $this->managerA->id, 'role' => OrganizationMember::ROLE_VENUE_MANAGER, 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $this->orgA->id, 'user_id' => $this->staffA->id, 'role' => OrganizationMember::ROLE_STAFF, 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $this->orgB->id, 'user_id' => $this->ownerB->id, 'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER, 'status' => 'active']);

        foreach ([$this->player1, $this->player2, $this->stranger] as $p) {
            OrganizationMember::create(['organization_id' => $this->orgA->id, 'user_id' => $p->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);
        }
    }

    private function headersFor(User $user, ?Organization $org = null): array
    {
        $this->actingAs($user, 'sanctum');
        $org = $org ?? $this->orgA;

        return [
            'Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken,
            'X-Organization-Id' => (string) $org->id,
            'X-Organization-ID' => (string) $org->id,
            'Idempotency-Key' => 'idem-'.uniqid(),
            'X-Idempotency-Key' => 'idem-'.uniqid(),
            'Accept' => 'application/json',
        ];
    }

    private function auth(User $user, ?Organization $org = null)
    {
        return $this->actingAs($user, 'sanctum')->withHeaders($this->headersFor($user, $org));
    }

    public function test_owner_can_open_session_and_host_is_not_added_as_player(): void
    {
        $startsAt = now()->addDays(2)->setHour(18)->setMinute(0)->setSecond(0);
        $endsAt = now()->addDays(2)->setHour(20)->setMinute(0)->setSecond(0);

        $payload = [
            'court_id' => $this->courtA->id,
            'is_owner_hosted' => true,
            'title' => 'Giao lưu cầu lông tối thứ 5',
            'visibility' => 'public',
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'category' => 'giao lưu',
            'skill_levels' => ['TBY', 'TB'],
            'required_participants' => 8,
        ];
        $response = $this->auth($this->ownerA)->postJson('/api/v2/social/matches', $payload);
        $response->assertStatus(201);
        $matchData = $response->json('match');
        $this->assertTrue($matchData['is_owner_hosted']);
        $this->assertSame($this->courtA->id, $matchData['court_id']);
        $this->assertSame('giao lưu', $matchData['category']);
        $this->assertEquals(['TBY', 'TB'], $matchData['skill_levels']);
        // Host status alone SHALL NOT consume a place or add a person
        $this->assertSame(0, $matchData['joined_count']);

        $post = MatchPost::findOrFail($matchData['id']);
        $this->assertSame(0, $post->participations()->count());
        $this->assertSame(0, $post->joined_count);
        $this->assertTrue($post->isOwnerHosted());
    }

    public function test_regular_match_still_adds_creator_as_participant(): void
    {
        $payload = [
            'title' => 'Kèo giao lưu tự do của người chơi',
            'visibility' => 'public',
            'starts_at' => now()->addDays(2)->setHour(19)->toIso8601String(),
            'ends_at' => now()->addDays(2)->setHour(21)->toIso8601String(),
            'required_participants' => 4,
            'min_skill' => 2,
            'max_skill' => 4,
        ];

        $response = $this->postJson('/api/v2/social/matches', $payload, $this->headersFor($this->player1));
        $response->assertStatus(201);

        $matchData = $response->json('match');
        $this->assertFalse($matchData['is_owner_hosted']);
        $this->assertSame(1, $matchData['joined_count']);

        $post = MatchPost::findOrFail($matchData['id']);
        $this->assertSame(1, $post->participations()->count());
        $this->assertSame($this->player1->id, $post->participations()->first()->user_id);
    }

    public function test_unauthorized_users_cannot_create_or_manage_owner_hosted_session(): void
    {
        $startsAt = now()->addDays(2)->setHour(14)->toIso8601String();
        $endsAt = now()->addDays(2)->setHour(16)->toIso8601String();

        $payload = [
            'court_id' => $this->courtA->id,
            'is_owner_hosted' => true,
            'title' => 'Buổi thử nghiệm',
            'visibility' => 'public',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'category' => 'đánh vãng lai',
            'skill_levels' => ['newbie'],
            'required_participants' => 6,
        ];

        // 1. Customer attempts to create
        $resCust = $this->postJson('/api/v2/social/matches', $payload, $this->headersFor($this->player1));
        $resCust->assertStatus(403);

        // 2. Staff attempts to create
        $resStaff = $this->postJson('/api/v2/social/matches', $payload, $this->headersFor($this->staffA));
        $resStaff->assertStatus(403);

        // 3. Manager without owner role attempts to create
        $resMgr = $this->postJson('/api/v2/social/matches', $payload, $this->headersFor($this->managerA));
        $resMgr->assertStatus(403);

        // 4. Owner of facility B attempts to create for facility A's court
        $resOwnerB = $this->postJson('/api/v2/social/matches', $payload, $this->headersFor($this->ownerB, $this->orgB));
        $resOwnerB->assertStatus(403);
    }

    public function test_host_who_loses_ownership_cannot_manage_or_finalize_session(): void
    {
        $post = MatchPost::create([
            'organization_id' => $this->orgA->id,
            'creator_id' => $this->ownerA->id,
            'court_id' => $this->courtA->id,
            'title' => 'Buổi giao lưu thứ 6',
            'visibility' => 'public',
            'is_owner_hosted' => true,
            'category' => 'giao lưu',
            'skill_levels' => ['TB'],
            'starts_at' => now()->addDay()->setHour(18),
            'ends_at' => now()->addDay()->setHour(20),
            'required_participants' => 6,
            'joined_count' => 0,
            'status' => 'ended',
        ]);

        // Revoke ownerA's role in orgA
        OrganizationMember::where('organization_id', $this->orgA->id)
            ->where('user_id', $this->ownerA->id)
            ->update(['role' => OrganizationMember::ROLE_CUSTOMER]);

        // Try ending or saving bill
        $res = $this->postJson("/api/v2/social/matches/{$post->id}/bill/draft", [
            'actual_headcount' => 6,
            'court_duration_minutes' => 120,
            'court_hourly_rate' => 150000,
            'shuttlecock_count' => 8,
            'shuttlecock_unit_price' => 25000,
        ], $this->headersFor($this->ownerA));

        $res->assertStatus(403);
    }

    public function test_cannot_create_session_overlapping_with_maintenance_block(): void
    {
        $startsAt = now()->addDays(3)->setHour(14);
        $endsAt = now()->addDays(3)->setHour(16);

        // CourtBlock
        CourtBlock::create([
            'court_id' => $this->courtA->id,
            'starts_at' => $startsAt->copy()->subMinutes(30)->utc(),
            'ends_at' => $startsAt->copy()->addHour()->utc(),
            'reason' => 'Bảo trì thay đèn sân',
        ]);

        $payload = [
            'court_id' => $this->courtA->id,
            'is_owner_hosted' => true,
            'title' => 'Buổi giao lưu',
            'visibility' => 'public',
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'category' => 'giao lưu',
            'skill_levels' => ['TB'],
            'required_participants' => 4,
        ];

        $response = $this->postJson('/api/v2/social/matches', $payload, $this->headersFor($this->ownerA));
        $response->assertStatus(409);
    }

    public function test_cannot_create_session_overlapping_with_existing_booking(): void
    {
        $startsAt = now()->addDays(4)->setHour(18);
        $endsAt = now()->addDays(4)->setHour(20);

        // Confirmed booking
        $booking = Booking::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->player1->id,
            'court_id' => $this->courtA->id,
            'code' => 'BK-'.uniqid(),
            'status' => 'confirmed',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'total_amount' => 300000,
        ]);

        BookingSlot::create([
            'booking_id' => $booking->id,
            'court_id' => $this->courtA->id,
            'starts_at' => $startsAt->utc(),
            'ends_at' => $endsAt->utc(),
            'unit_price' => 150000,
            'status' => 'confirmed',
        ]);
        $payload = [
            'court_id' => $this->courtA->id,
            'is_owner_hosted' => true,
            'title' => 'Buổi giao lưu trùng giờ',
            'visibility' => 'public',
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'category' => 'giao lưu',
            'skill_levels' => ['TB'],
            'required_participants' => 4,
        ];

        $response = $this->postJson('/api/v2/social/matches', $payload, $this->headersFor($this->ownerA));
        $response->assertStatus(409);
    }

    public function test_discovery_filters_by_category_and_skill_label(): void
    {
        // Session 1: accepts TBY, TB
        $s1 = MatchPost::create([
            'organization_id' => $this->orgA->id,
            'creator_id' => $this->ownerA->id,
            'court_id' => $this->courtA->id,
            'title' => 'Kèo TBY',
            'visibility' => 'public',
            'is_owner_hosted' => true,
            'category' => 'đánh vãng lai',
            'skill_levels' => ['TBY', 'TB'],
            'starts_at' => now()->addDays(1)->setHour(18),
            'ends_at' => now()->addDays(1)->setHour(20),
            'required_participants' => 6,
            'joined_count' => 0,
            'status' => 'open',
        ]);

        // Session 2: accepts only K
        $s2 = MatchPost::create([
            'organization_id' => $this->orgA->id,
            'creator_id' => $this->ownerA->id,
            'court_id' => $this->courtA->id,
            'title' => 'Kèo K',
            'visibility' => 'public',
            'is_owner_hosted' => true,
            'category' => 'giao lưu',
            'skill_levels' => ['K'],
            'starts_at' => now()->addDays(1)->setHour(20),
            'ends_at' => now()->addDays(1)->setHour(22),
            'required_participants' => 6,
            'joined_count' => 0,
            'status' => 'open',
        ]);

        // Filter for TBY
        $resTBY = $this->getJson('/api/v2/social/matches?skill_level=TBY', $this->headersFor($this->player1));
        $resTBY->assertStatus(200);
        $matchesTBY = collect($resTBY->json('matches'));
        $this->assertTrue($matchesTBY->contains('id', $s1->id));
        $this->assertFalse($matchesTBY->contains('id', $s2->id));

        // Filter for newbie (neither accepts)
        $resNewbie = $this->getJson('/api/v2/social/matches?skill_level=newbie', $this->headersFor($this->player1));
        $resNewbie->assertStatus(200);
        $matchesNewbie = collect($resNewbie->json('matches'));
        $this->assertFalse($matchesNewbie->contains('id', $s1->id));
        $this->assertFalse($matchesNewbie->contains('id', $s2->id));

        // Filter for category 'đánh vãng lai'
        $resCat = $this->getJson('/api/v2/social/matches?category='.urlencode('đánh vãng lai'), $this->headersFor($this->player1));
        $resCat->assertStatus(200);
        $matchesCat = collect($resCat->json('matches'));
        $this->assertTrue($matchesCat->contains('id', $s1->id));
        $this->assertFalse($matchesCat->contains('id', $s2->id));
    }

    public function test_individual_participation_and_capacity_conflict(): void
    {
        $post = MatchPost::create([
            'organization_id' => $this->orgA->id,
            'creator_id' => $this->ownerA->id,
            'court_id' => $this->courtA->id,
            'title' => 'Kèo 1 chỗ duy nhất',
            'visibility' => 'public',
            'is_owner_hosted' => true,
            'category' => 'giao lưu',
            'skill_levels' => ['TB'],
            'starts_at' => now()->addDays(1)->setHour(18),
            'ends_at' => now()->addDays(1)->setHour(20),
            'required_participants' => 1,
            'joined_count' => 0,
            'status' => 'open',
        ]);

        // Player 1 joins successfully
        $res1 = $this->postJson("/api/v2/social/matches/{$post->id}/join", [], $this->headersFor($this->player1));
        $res1->assertStatus(201);
        $this->assertSame(1, $post->fresh()->joined_count);
        $this->assertSame('full', $post->fresh()->status);

        // Player 2 attempts to join -> 409 conflict
        $res2 = $this->postJson("/api/v2/social/matches/{$post->id}/join", [], $this->headersFor($this->player2));
        $res2->assertStatus(409);
    }

    public function test_host_ends_session_and_registers_closing(): void
    {
        $post = MatchPost::create([
            'organization_id' => $this->orgA->id,
            'creator_id' => $this->ownerA->id,
            'court_id' => $this->courtA->id,
            'title' => 'Kèo đang diễn ra',
            'visibility' => 'public',
            'is_owner_hosted' => true,
            'category' => 'giao lưu',
            'skill_levels' => ['TB'],
            'starts_at' => now()->subHours(2),
            'ends_at' => now(),
            'required_participants' => 4,
            'joined_count' => 1,
            'status' => 'open',
        ]);

        // Host ends session
        $res = $this->postJson("/api/v2/social/matches/{$post->id}/end", [], $this->headersFor($this->ownerA));
        $res->assertStatus(200);
        $this->assertSame('ended', $res->json('match.status'));

        // Cannot join ended session
        $resJoin = $this->postJson("/api/v2/social/matches/{$post->id}/join", [], $this->headersFor($this->player2));
        $resJoin->assertStatus(409);
    }

    public function test_bill_creation_validation_and_rejection_of_auxiliary_services(): void
    {
        $post = MatchPost::create([
            'organization_id' => $this->orgA->id,
            'creator_id' => $this->ownerA->id,
            'court_id' => $this->courtA->id,
            'title' => 'Buổi chơi xong',
            'visibility' => 'public',
            'is_owner_hosted' => true,
            'category' => 'giao lưu',
            'skill_levels' => ['TB'],
            'starts_at' => now()->subHours(2),
            'ends_at' => now(),
            'required_participants' => 4,
            'joined_count' => 2,
            'status' => 'open', // not ended yet
        ]);

        // 1. Cannot save bill before session has ended
        $resPremature = $this->postJson("/api/v2/social/matches/{$post->id}/bill/draft", [
            'actual_headcount' => 4,
            'court_duration_minutes' => 120,
            'court_hourly_rate' => 150000,
            'shuttlecock_count' => 8,
            'shuttlecock_unit_price' => 25000,
        ], $this->headersFor($this->ownerA));
        $resPremature->assertStatus(422);

        // End session
        $post->update(['status' => 'ended']);

        // 2. Reject auxiliary charges (water, grips, drinks)
        $resAux = $this->postJson("/api/v2/social/matches/{$post->id}/bill/draft", [
            'actual_headcount' => 4,
            'court_duration_minutes' => 120,
            'court_hourly_rate' => 150000,
            'shuttlecock_count' => 8,
            'shuttlecock_unit_price' => 25000,
            'water' => 50000,
            'drinks' => ['sting' => 30000],
        ], $this->headersFor($this->ownerA));
        $resAux->assertStatus(422);

        // 3. Reject zero headcount
        $resZeroHead = $this->postJson("/api/v2/social/matches/{$post->id}/bill/draft", [
            'actual_headcount' => 0,
            'court_duration_minutes' => 120,
            'court_hourly_rate' => 150000,
            'shuttlecock_count' => 8,
            'shuttlecock_unit_price' => 25000,
        ], $this->headersFor($this->ownerA));
        $resZeroHead->assertStatus(422);

        // 4. Reject fractional shuttlecock count
        $resFrac = $this->postJson("/api/v2/social/matches/{$post->id}/bill/draft", [
            'actual_headcount' => 4,
            'court_duration_minutes' => 120,
            'court_hourly_rate' => 150000,
            'shuttlecock_count' => 6.5,
            'shuttlecock_unit_price' => 25000,
        ], $this->headersFor($this->ownerA));
        $resFrac->assertStatus(422);

        // 5. Valid draft succeeds
        $resValid = $this->postJson("/api/v2/social/matches/{$post->id}/bill/draft", [
            'actual_headcount' => 4,
            'court_duration_minutes' => 120,
            'court_hourly_rate' => 150000,
            'shuttlecock_count' => 8,
            'shuttlecock_unit_price' => 25000,
        ], $this->headersFor($this->ownerA));
        $resValid->assertStatus(200);
        $this->assertSame('draft', $resValid->json('bill.status'));
        $this->assertSame(500000, $resValid->json('bill.total_amount'));
        $this->assertSame(125000, $resValid->json('bill.per_person_amount'));
    }

    public function test_full_flow_host_finalizes_bill_and_players_read_snapshot(): void
    {
        // 1. Host creates session
        $post = MatchPost::create([
            'organization_id' => $this->orgA->id,
            'creator_id' => $this->ownerA->id,
            'court_id' => $this->courtA->id,
            'title' => 'Buổi giao lưu trọn luồng',
            'visibility' => 'public',
            'is_owner_hosted' => true,
            'category' => 'giao lưu',
            'skill_levels' => ['TB'],
            'starts_at' => now()->subHours(2),
            'ends_at' => now(),
            'required_participants' => 6,
            'joined_count' => 0,
            'status' => 'open',
        ]);

        // 2. Customers join (only 2 users registered)
        $this->auth($this->player1)->postJson("/api/v2/social/matches/{$post->id}/join", [])->assertStatus(201);
        $this->auth($this->player2)->postJson("/api/v2/social/matches/{$post->id}/join", [])->assertStatus(201);

        // 3. Host ends session
        $this->auth($this->ownerA)->postJson("/api/v2/social/matches/{$post->id}/end", [])->assertStatus(200);

        // 4. Actual headcount differs from registered count (e.g. 6 actual players after walk-ins)
        // 2 hours @ 150,000 = 300,000 court. 8 shuttles @ 25,000 = 200,000 shuttles. Total = 500,000.
        // 6 players => 500,000 / 6 = 83,333 VND (is_approximate = true)
        $finalizeRes = $this->auth($this->ownerA)->postJson("/api/v2/social/matches/{$post->id}/bill/finalize", [
            'actual_headcount' => 6,
            'court_duration_minutes' => 120,
            'court_hourly_rate' => 150000,
            'shuttlecock_count' => 8,
            'shuttlecock_unit_price' => 25000,
        ]);

        $finalizeRes->assertStatus(200);
        $billData = $finalizeRes->json('bill');
        $this->assertSame('finalized', $billData['status']);
        $this->assertSame(500000, $billData['total_amount']);
        $this->assertSame(83333, $billData['per_person_amount']);
        $this->assertTrue($billData['is_approximate']);
        $this->assertSame(6, $billData['snapshot']['breakdown']['divisor']);
        $finalizedById = is_array($billData['finalized_by']) ? $billData['finalized_by']['id'] : $billData['finalized_by'];
        $this->assertSame($this->ownerA->id, $finalizedById);

        // 5. Retry with exact same data succeeds idempotently
        $retryRes = $this->auth($this->ownerA)->postJson("/api/v2/social/matches/{$post->id}/bill/finalize", [
            'actual_headcount' => 6,
            'court_duration_minutes' => 120,
            'court_hourly_rate' => 150000,
            'shuttlecock_count' => 8,
            'shuttlecock_unit_price' => 25000,
        ]);
        $retryRes->assertStatus(200);

        // 6. Conflicting edit after finalization is rejected (409)
        $conflictRes = $this->auth($this->ownerA)->postJson("/api/v2/social/matches/{$post->id}/bill/finalize", [
            'actual_headcount' => 7,
            'court_duration_minutes' => 120,
            'court_hourly_rate' => 150000,
            'shuttlecock_count' => 8,
            'shuttlecock_unit_price' => 25000,
        ]);
        $conflictRes->assertStatus(409);

        // 7. Registered participant can read the finalized bill
        $playerRead = $this->auth($this->player1)->getJson("/api/v2/social/matches/{$post->id}/bill");
        $playerRead->assertStatus(200);
        $this->assertSame(500000, $playerRead->json('bill.total_amount'));
        $this->assertSame(83333, $playerRead->json('bill.per_person_amount'));

        // 8. Unrelated stranger cannot read the bill
        $strangerRead = $this->auth($this->stranger)->getJson("/api/v2/social/matches/{$post->id}/bill");
        $strangerRead->assertStatus(403);
    }
}
