<?php

namespace Tests\Feature\Tournament;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Tournament;
use App\Models\TournamentEvent;
use App\Models\TournamentGrant;
use App\Models\TournamentStanding;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\Tournament\TournamentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentCompleteLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $owner;

    private User $customerOfficial;

    private User $customerOrganizer;

    private User $player1;

    private User $player2;

    private User $player3;

    private User $player4;

    private WalletAccount $wallet1;

    private WalletAccount $wallet2;

    private WalletAccount $wallet3;

    private WalletAccount $wallet4;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Aura Masters Arena',
            'slug' => 'aura-masters-'.uniqid(),
        ]);

        config([
            'features.pilot_organizations' => [(string) $this->organization->id],
            'features.flags.social_matchmaking_tournaments' => true,
            'features.flags.tenant_pos_and_wallet' => true,
        ]);

        $this->owner = User::factory()->create(['name' => 'Org Owner']);
        OrganizationMember::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->owner->id,
            'role' => 'organization_owner',
            'status' => 'active',
        ]);

        $this->customerOfficial = User::factory()->create(['name' => 'Official Customer']);
        OrganizationMember::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->customerOfficial->id,
            'role' => 'customer',
            'status' => 'active',
        ]);

        $this->customerOrganizer = User::factory()->create(['name' => 'Organizer Customer']);
        OrganizationMember::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->customerOrganizer->id,
            'role' => 'customer',
            'status' => 'active',
        ]);

        $this->player1 = User::factory()->create(['name' => 'Player 1']);
        OrganizationMember::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->player1->id,
            'role' => 'customer',
            'status' => 'active',
        ]);
        $this->wallet1 = WalletAccount::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->player1->id,
            'currency' => 'VND',
            'balance' => 500000,
            'status' => 'active',
        ]);

        $this->player2 = User::factory()->create(['name' => 'Player 2']);
        OrganizationMember::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->player2->id,
            'role' => 'customer',
            'status' => 'active',
        ]);
        $this->wallet2 = WalletAccount::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->player2->id,
            'currency' => 'VND',
            'balance' => 500000,
            'status' => 'active',
        ]);

        $this->player3 = User::factory()->create(['name' => 'Player 3']);
        OrganizationMember::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->player3->id,
            'role' => 'customer',
            'status' => 'active',
        ]);
        $this->wallet3 = WalletAccount::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->player3->id,
            'currency' => 'VND',
            'balance' => 500000,
            'status' => 'active',
        ]);

        $this->player4 = User::factory()->create(['name' => 'Player 4']);
        OrganizationMember::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->player4->id,
            'role' => 'customer',
            'status' => 'active',
        ]);
        $this->wallet4 = WalletAccount::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->player4->id,
            'currency' => 'VND',
            'balance' => 500000,
            'status' => 'active',
        ]);
    }

    private function headers(string $keyPrefix = 'test-'): array
    {
        return [
            'X-Organization-ID' => (string) $this->organization->id,
            'Idempotency-Key' => $keyPrefix.uniqid(),
        ];
    }

    /**
     * Task 10.1 [D63]: Event-scoped grants:
     * - Customer official can score Tournament A but gets 403 on Tournament B.
     * - Customer official does not have organizer permissions on Tournament A (bracket regeneration 403).
     * - Grant endpoints: list active, create grant, revoke grant.
     */
    public function test_event_scoped_grant_allows_customer_official_on_tournament_a_but_403_on_tournament_b_and_grant_management(): void
    {
        $service = app(TournamentService::class);

        // Create Tournament A and B
        $tourneyA = $service->createAndGenerate(
            $this->owner,
            $this->organization->id,
            'Tournament A',
            'single_elimination',
            [$this->player1->id, $this->player2->id]
        );

        $tourneyB = $service->createAndGenerate(
            $this->owner,
            $this->organization->id,
            'Tournament B',
            'single_elimination',
            [$this->player3->id, $this->player4->id]
        );

        // Give customer official grant ONLY on Tournament A
        $grant = TournamentGrant::create([
            'organization_id' => $this->organization->id,
            'tournament_id' => $tourneyA->id,
            'user_id' => $this->customerOfficial->id,
            'role' => 'official',
            'granted_by' => $this->owner->id,
        ]);

        $this->assertTrue($grant->isActive());
        $this->assertTrue($tourneyA->isAuthorizedOfficial($this->customerOfficial));
        $this->assertFalse($tourneyB->isAuthorizedOfficial($this->customerOfficial));

        // 1. Customer official scores Tournament A -> SUCCESS (200)
        $fixtureA = $tourneyA->fixtures()->first();
        $scoreResA = $this->actingAs($this->customerOfficial)
            ->withHeaders($this->headers('score-a-'))
            ->postJson("/api/tournaments/{$tourneyA->id}/fixtures/{$fixtureA->id}/score", [
                'expected_version' => $tourneyA->version,
                'winner_id' => $fixtureA->participant_one_id,
                'score' => ['p1' => 21, 'p2' => 15],
            ]);
        $scoreResA->assertOk();

        // 2. Customer official attempts to score Tournament B -> 403 FORBIDDEN
        $fixtureB = $tourneyB->fixtures()->first();
        $scoreResB = $this->actingAs($this->customerOfficial)
            ->withHeaders($this->headers('score-b-'))
            ->postJson("/api/tournaments/{$tourneyB->id}/fixtures/{$fixtureB->id}/score", [
                'expected_version' => $tourneyB->version,
                'winner_id' => $fixtureB->participant_one_id,
                'score' => ['p1' => 21, 'p2' => 15],
            ]);
        $scoreResB->assertStatus(403);

        // 3. Customer official attempts organizer action (bracket regeneration) on Tournament A -> 403 FORBIDDEN
        $regenRes = $this->actingAs($this->customerOfficial)
            ->withHeaders($this->headers('regen-a-'))
            ->postJson("/api/tournaments/{$tourneyA->id}/regenerate");
        $regenRes->assertStatus(403);

        // 4. Org owner grants 'organizer' role to customerOrganizer via API
        $createGrantRes = $this->actingAs($this->owner)
            ->withHeaders($this->headers('grant-add-'))
            ->postJson("/api/tournaments/{$tourneyA->id}/grants", [
                'user_id' => $this->customerOrganizer->id,
                'role' => 'organizer',
            ]);
        $createGrantRes->assertStatus(201);
        $newGrantId = $createGrantRes->json('grant.id');

        // CustomerOrganizer now has organizer rights on Tournament A
        $this->assertTrue($tourneyA->fresh()->isAuthorizedOfficial($this->customerOrganizer, 'organizer'));

        // CustomerOrganizer lists active grants on Tournament A
        $listGrantsRes = $this->actingAs($this->customerOrganizer)
            ->withHeaders($this->headers('grants-list-'))
            ->getJson("/api/tournaments/{$tourneyA->id}/grants");
        $listGrantsRes->assertOk();
        $this->assertCount(2, $listGrantsRes->json('grants'));

        // CustomerOrganizer regenerates bracket on Tournament A -> 200 OK
        $regenByOrganizerRes = $this->actingAs($this->customerOrganizer)
            ->withHeaders($this->headers('regen-by-org-'))
            ->postJson("/api/tournaments/{$tourneyA->id}/regenerate");
        $regenByOrganizerRes->assertOk();

        // 5. Revoke grant
        $revokeRes = $this->actingAs($this->owner)
            ->withHeaders($this->headers('grant-del-'))
            ->deleteJson("/api/tournaments/{$tourneyA->id}/grants/{$newGrantId}");
        $revokeRes->assertOk();

        $this->assertFalse($tourneyA->fresh()->isAuthorizedOfficial($this->customerOrganizer, 'organizer'));
    }

    /**
     * Task 10.2 [D64]: Unified admission:
     * - Rejects registration if deadline has passed (422) or tournament already started (422).
     * - Capacity check limits active participants.
     * - Entry fee requires wallet payment for self-registration or explicit fee waiver with reason for admin.
     */
    public function test_unified_admission_checks_deadline_capacity_fee_and_waiver(): void
    {
        // 1. Deadline passed check
        $expiredTourney = Tournament::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->owner->id,
            'name' => 'Expired Tourney',
            'format' => 'single_elimination',
            'status' => 'open',
            'settings' => [
                'entry_fee' => '100.000 ₫',
                'max_participants' => 8,
                'registration_deadline' => now()->subDay()->toIso8601String(),
            ],
        ]);

        $lateReg = $this->actingAs($this->player1)
            ->withHeaders($this->headers('late-reg-'))
            ->postJson("/api/tournaments/{$expiredTourney->id}/register");
        $lateReg->assertStatus(422);
        $lateReg->assertJsonPath('message', 'Registration deadline has passed.');

        // 2. Tournament already started check
        $startedTourney = Tournament::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->owner->id,
            'name' => 'Started Tourney',
            'format' => 'single_elimination',
            'status' => 'open',
            'starts_at' => now()->subHour(),
            'settings' => [
                'entry_fee' => '100.000 ₫',
                'max_participants' => 8,
            ],
        ]);

        $startedReg = $this->actingAs($this->player1)
            ->withHeaders($this->headers('started-reg-'))
            ->postJson("/api/tournaments/{$startedTourney->id}/register");
        $startedReg->assertStatus(422);
        $startedReg->assertJsonPath('message', 'Tournament has already started.');

        // 3. Capacity limit check
        $cappedTourney = Tournament::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->owner->id,
            'name' => 'Capped Tourney',
            'format' => 'single_elimination',
            'status' => 'open',
            'settings' => [
                'entry_fee' => '0 ₫',
                'max_participants' => 2,
            ],
        ]);

        // Register 2 players
        $this->actingAs($this->player1)->withHeaders($this->headers('cap-1-'))->postJson("/api/tournaments/{$cappedTourney->id}/register")->assertStatus(201);
        $this->actingAs($this->player2)->withHeaders($this->headers('cap-2-'))->postJson("/api/tournaments/{$cappedTourney->id}/register")->assertStatus(201);

        // Player 3 exceeds capacity -> 409
        $capOverflow = $this->actingAs($this->player3)->withHeaders($this->headers('cap-3-'))->postJson("/api/tournaments/{$cappedTourney->id}/register");
        $capOverflow->assertStatus(409);
        $this->assertStringContainsString('maximum participant capacity', $capOverflow->json('message'));

        // 4. Admin/Organizer fee waiver check
        $paidTourney = Tournament::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->owner->id,
            'name' => 'Paid Tourney',
            'format' => 'single_elimination',
            'status' => 'open',
            'settings' => [
                'entry_fee' => '200.000 ₫',
                'max_participants' => 8,
            ],
        ]);

        // Admin adds participant with fee_waiver: true BUT empty waiver_reason -> 422
        $waiverFail = $this->actingAs($this->owner)
            ->withHeaders($this->headers('wf-'))
            ->postJson("/api/tournaments/{$paidTourney->id}/participants", [
                'user_id' => $this->player1->id,
                'fee_waiver' => true,
                'waiver_reason' => '',
            ]);
        $waiverFail->assertStatus(422);

        // Admin adds participant with valid waiver reason -> 201 Created and waiver_reason saved
        $waiverOk = $this->actingAs($this->owner)
            ->withHeaders($this->headers('wo-'))
            ->postJson("/api/tournaments/{$paidTourney->id}/participants", [
                'user_id' => $this->player1->id,
                'fee_waiver' => true,
                'waiver_reason' => 'Wildcard invitation from tournament director',
            ]);
        $waiverOk->assertStatus(201);
        $this->assertSame('Wildcard invitation from tournament director', $waiverOk->json('participant.waiver_reason'));
    }

    /**
     * Task 10.2 [D64]: Withdrawal releases capacity for new registration.
     */
    public function test_withdrawal_releases_capacity_for_new_registration(): void
    {
        $tourney = Tournament::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->owner->id,
            'name' => 'Capacity Release Tourney',
            'format' => 'single_elimination',
            'status' => 'open',
            'settings' => [
                'entry_fee' => '0 ₫',
                'max_participants' => 2,
            ],
        ]);

        // 1. Fill tournament
        $p1Res = $this->actingAs($this->player1)->withHeaders($this->headers('reg-p1-'))->postJson("/api/tournaments/{$tourney->id}/register")->assertStatus(201);
        $p2Res = $this->actingAs($this->player2)->withHeaders($this->headers('reg-p2-'))->postJson("/api/tournaments/{$tourney->id}/register")->assertStatus(201);
        $p2Id = $p2Res->json('participant.id');

        // Player 3 is rejected due to capacity
        $this->actingAs($this->player3)->withHeaders($this->headers('reg-p3-fail-'))->postJson("/api/tournaments/{$tourney->id}/register")->assertStatus(409);

        // 2. Player 2 withdraws
        $withdrawnRes = $this->actingAs($this->player2)
            ->withHeaders($this->headers('p2-withdraw-'))
            ->postJson("/api/tournament-participants/{$p2Id}/check-in", [
                'status' => 'withdrawn',
            ]);
        $withdrawnRes->assertOk();
        $this->assertSame('withdrawn', $withdrawnRes->json('participant.status'));

        // 3. Now Player 3 can register into the released slot!
        $p3Success = $this->actingAs($this->player3)
            ->withHeaders($this->headers('reg-p3-ok-'))
            ->postJson("/api/tournaments/{$tourney->id}/register");
        $p3Success->assertStatus(201);

        // Active participant count is 2 (p1 and p3)
        $activeCount = $tourney->participants()->whereNotIn('status', ['withdrawn', 'rejected'])->count();
        $this->assertSame(2, $activeCount);
    }

    /**
     * Task 10.2 [D64]: Attendance states supported on checkIn endpoint.
     */
    public function test_attendance_states_supported_on_check_in(): void
    {
        $tourney = Tournament::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->owner->id,
            'name' => 'Attendance Tourney',
            'format' => 'single_elimination',
            'status' => 'open',
            'settings' => ['entry_fee' => '0 ₫', 'max_participants' => 8],
        ]);

        $pRes = $this->actingAs($this->player1)->withHeaders($this->headers('reg-att-'))->postJson("/api/tournaments/{$tourney->id}/register")->assertStatus(201);
        $pId = $pRes->json('participant.id');

        // Grant official role to customerOfficial
        TournamentGrant::create([
            'organization_id' => $this->organization->id,
            'tournament_id' => $tourney->id,
            'user_id' => $this->customerOfficial->id,
            'role' => 'official',
            'granted_by' => $this->owner->id,
        ]);

        // Official sets status to 'present'
        $resPresent = $this->actingAs($this->customerOfficial)
            ->withHeaders($this->headers('att-1-'))
            ->postJson("/api/tournament-participants/{$pId}/check-in", ['status' => 'present']);
        $resPresent->assertOk();
        $this->assertSame('present', $resPresent->json('participant.status'));

        // Official sets status to 'absent'
        $resAbsent = $this->actingAs($this->customerOfficial)
            ->withHeaders($this->headers('att-2-'))
            ->postJson("/api/tournament-participants/{$pId}/check-in", ['status' => 'absent']);
        $resAbsent->assertOk();
        $this->assertSame('absent', $resAbsent->json('participant.status'));

        // Participant self check-in back to 'checked_in'
        $resCheckedIn = $this->actingAs($this->player1)
            ->withHeaders($this->headers('att-3-'))
            ->postJson("/api/tournament-participants/{$pId}/check-in", ['status' => 'checked_in']);
        $resCheckedIn->assertOk();
        $this->assertSame('checked_in', $resCheckedIn->json('participant.status'));

        // Unauthorized user gets 403
        $this->actingAs($this->player2)
            ->withHeaders($this->headers('att-4-'))
            ->postJson("/api/tournament-participants/{$pId}/check-in", ['status' => 'checked_in'])
            ->assertStatus(403);
    }

    /**
     * Task 10.3 [D65]: Deterministic bye propagation for 3, 5, 7 participants.
     */
    public function test_deterministic_byes_for_3_5_7_participants_propagate_to_round_2_without_phantoms(): void
    {
        $service = app(TournamentService::class);

        // --- Case 1: 3 Participants (Size 4, 1 Bye) ---
        $t3 = $service->createAndGenerate(
            $this->owner,
            $this->organization->id,
            'Tourney 3 Players',
            'single_elimination',
            [$this->player1->id, $this->player2->id, $this->player3->id]
        );

        $fixtures3 = $t3->fixtures()->orderBy('round')->orderBy('position')->get();
        // Size 4 -> 2 rounds: Round 1 has 2 fixtures, Round 2 has 1 fixture (Final)
        $this->assertCount(3, $fixtures3);

        $r1f1_3 = $fixtures3->where('round', 1)->where('position', 1)->first();
        $r1f2_3 = $fixtures3->where('round', 1)->where('position', 2)->first();
        $r2f1_3 = $fixtures3->where('round', 2)->where('position', 1)->first();

        // Top seed (Player 1) gets bye in Round 1 Fixture 1
        $this->assertSame('bye', $r1f1_3->status);
        $p1 = $t3->participants()->where('user_id', $this->player1->id)->first();
        $this->assertSame($p1->id, $r1f1_3->winner_id);

        // IMMEDIATELY advanced to Round 2 Fixture 1 slot 1!
        $this->assertSame($p1->id, $r2f1_3->participant_one_id);
        $this->assertNull($r2f1_3->participant_two_id);
        $this->assertSame('pending', $r2f1_3->status);

        // Round 1 Fixture 2 is pending match between Seed 2 and Seed 3
        $this->assertSame('pending', $r1f2_3->status);
        $this->assertNotNull($r1f2_3->participant_one_id);
        $this->assertNotNull($r1f2_3->participant_two_id);

        // --- Case 2: 5 Participants (Size 8, 3 Byes) ---
        $p5_user = User::factory()->create();
        OrganizationMember::create(['organization_id' => $this->organization->id, 'user_id' => $p5_user->id, 'role' => 'customer', 'status' => 'active']);

        $t5 = $service->createAndGenerate(
            $this->owner,
            $this->organization->id,
            'Tourney 5 Players',
            'single_elimination',
            [$this->player1->id, $this->player2->id, $this->player3->id, $this->player4->id, $p5_user->id]
        );

        // Size 8 -> 3 rounds (4 + 2 + 1 = 7 fixtures)
        $this->assertCount(7, $t5->fixtures);

        $r1_5 = $t5->fixtures()->where('round', 1)->orderBy('position')->get();
        // Top 3 seeds (1, 2, 3) get byes
        $this->assertSame('bye', $r1_5->get(0)->status); // Pos 1: Seed 1
        $this->assertSame('pending', $r1_5->get(1)->status); // Pos 2: Seed 4 vs Seed 5
        $this->assertSame('bye', $r1_5->get(2)->status); // Pos 3: Seed 2
        $this->assertSame('bye', $r1_5->get(3)->status); // Pos 4: Seed 3

        $r2_5 = $t5->fixtures()->where('round', 2)->orderBy('position')->get();
        // Round 2 Position 1 has Seed 1 in slot 1, slot 2 awaiting Pos 2 winner
        $this->assertNotNull($r2_5->get(0)->participant_one_id);
        $this->assertNull($r2_5->get(0)->participant_two_id);
        $this->assertSame('pending', $r2_5->get(0)->status);

        // Round 2 Position 2 has BOTH Seed 2 and Seed 3 advancing from byes, ready to play!
        $this->assertNotNull($r2_5->get(1)->participant_one_id);
        $this->assertNotNull($r2_5->get(1)->participant_two_id);
        $this->assertSame('pending', $r2_5->get(1)->status);

        // --- Case 3: 7 Participants (Size 8, 1 Bye) ---
        $p6_user = User::factory()->create();
        OrganizationMember::create(['organization_id' => $this->organization->id, 'user_id' => $p6_user->id, 'role' => 'customer', 'status' => 'active']);
        $p7_user = User::factory()->create();
        OrganizationMember::create(['organization_id' => $this->organization->id, 'user_id' => $p7_user->id, 'role' => 'customer', 'status' => 'active']);

        $t7 = $service->createAndGenerate(
            $this->owner,
            $this->organization->id,
            'Tourney 7 Players',
            'single_elimination',
            [$this->player1->id, $this->player2->id, $this->player3->id, $this->player4->id, $p5_user->id, $p6_user->id, $p7_user->id]
        );

        $r1_7 = $t7->fixtures()->where('round', 1)->orderBy('position')->get();
        $this->assertSame('bye', $r1_7->get(0)->status); // Pos 1 (Seed 1) is bye
        $this->assertSame('pending', $r1_7->get(1)->status); // Match
        $this->assertSame('pending', $r1_7->get(2)->status); // Match
        $this->assertSame('pending', $r1_7->get(3)->status); // Match
    }

    /**
     * Task 10.4 [D66]: Standings recomputation without double-counting & correction protection.
     */
    public function test_score_correction_recomputes_standings_without_double_counting(): void
    {
        $service = app(TournamentService::class);

        $tourney = $service->createAndGenerate(
            $this->owner,
            $this->organization->id,
            'Round Robin Recomputation',
            'round_robin',
            [$this->player1->id, $this->player2->id]
        );

        $fixture = $tourney->fixtures()->first();
        $p1Id = $fixture->participant_one_id;
        $p2Id = $fixture->participant_two_id;

        // 1. Initial score submission: Player 1 wins
        $res1 = $this->actingAs($this->owner)
            ->withHeaders($this->headers('sc-1-'))
            ->postJson("/api/tournaments/{$tourney->id}/fixtures/{$fixture->id}/score", [
                'expected_version' => $tourney->version,
                'winner_id' => $p1Id,
                'score' => ['p1' => 21, 'p2' => 19],
            ]);
        $res1->assertOk();

        $st1 = TournamentStanding::where('participant_id', $p1Id)->first();
        $this->assertSame(1, $st1->played);
        $this->assertSame(1, $st1->wins);
        $this->assertSame(3, $st1->points);

        // Attempt score correction without reason -> 422 Unprocessable
        $resNoReason = $this->actingAs($this->owner)
            ->withHeaders($this->headers('sc-no-reason-'))
            ->postJson("/api/tournaments/{$tourney->id}/fixtures/{$fixture->id}/score", [
                'expected_version' => $tourney->fresh()->version,
                'winner_id' => $p2Id,
                'score' => ['p1' => 19, 'p2' => 21],
            ]);
        $resNoReason->assertStatus(422);
        $this->assertStringContainsString('Lý do điều chỉnh kết quả là bắt buộc', $resNoReason->json('message'));

        // 2. Score correction: Correct to Player 2 winning with reason
        $res2 = $this->actingAs($this->owner)
            ->withHeaders($this->headers('sc-2-'))
            ->postJson("/api/tournaments/{$tourney->id}/fixtures/{$fixture->id}/score", [
                'expected_version' => $tourney->fresh()->version,
                'winner_id' => $p2Id,
                'score' => ['p1' => 19, 'p2' => 21],
                'reason' => 'Scorecard flipped by court umpire during initial submission',
            ]);
        $res2->assertOk();

        // Standings recomputed without double-counting!
        $st1Fresh = TournamentStanding::where('participant_id', $p1Id)->first();
        $st2Fresh = TournamentStanding::where('participant_id', $p2Id)->first();

        // Player 1 now has played 1, wins 0, losses 1, points 0 (NOT played 2!)
        $this->assertSame(1, $st1Fresh->played);
        $this->assertSame(0, $st1Fresh->wins);
        $this->assertSame(1, $st1Fresh->losses);
        $this->assertSame(0, $st1Fresh->points);

        // Player 2 now has played 1, wins 1, losses 0, points 3
        $this->assertSame(1, $st2Fresh->played);
        $this->assertSame(1, $st2Fresh->wins);
        $this->assertSame(0, $st2Fresh->losses);
        $this->assertSame(3, $st2Fresh->points);

        // Verify reason was recorded in event payload
        $event = TournamentEvent::where('tournament_id', $tourney->id)
            ->where('event_type', 'tournament.score_updated')
            ->latest('id')
            ->first();
        $this->assertSame('Scorecard flipped by court umpire during initial submission', $event->payload['reason']);
        $this->assertTrue($event->payload['is_correction']);
    }

    /**
     * Task 10.4 [D66]: Single elimination: downstream completed round blocks overwrite (409).
     */
    public function test_score_correction_blocked_by_completed_downstream_round_in_single_elimination(): void
    {
        $service = app(TournamentService::class);

        // 4 players in single elimination: 2 rounds (semi-finals and final)
        $tourney = $service->createAndGenerate(
            $this->owner,
            $this->organization->id,
            'SE Downstream Block',
            'single_elimination',
            [$this->player1->id, $this->player2->id, $this->player3->id, $this->player4->id]
        );

        $semi1 = $tourney->fixtures()->where('round', 1)->where('position', 1)->first();
        $semi2 = $tourney->fixtures()->where('round', 1)->where('position', 2)->first();
        $final = $tourney->fixtures()->where('round', 2)->where('position', 1)->first();

        // 1. Play Semi 1: Player 1 wins over Player 2
        $this->actingAs($this->owner)
            ->withHeaders($this->headers('semi1-'))
            ->postJson("/api/tournaments/{$tourney->id}/fixtures/{$semi1->id}/score", [
                'expected_version' => $tourney->version,
                'winner_id' => $semi1->participant_one_id,
                'score' => ['p1' => 2, 'p2' => 0],
            ])
            ->assertOk();

        // 2. Play Semi 2: Player 3 wins over Player 4
        $this->actingAs($this->owner)
            ->withHeaders($this->headers('semi2-'))
            ->postJson("/api/tournaments/{$tourney->id}/fixtures/{$semi2->id}/score", [
                'expected_version' => $tourney->fresh()->version,
                'winner_id' => $semi2->participant_one_id,
                'score' => ['p1' => 2, 'p2' => 0],
            ])
            ->assertOk();

        // Final now has Player 1 vs Player 3 (status pending)
        // 3. Score correction while Final is PENDING: SUCCEEDS and updates downstream participant
        $correctSemi1 = $this->actingAs($this->owner)
            ->withHeaders($this->headers('correct-semi1-'))
            ->postJson("/api/tournaments/{$tourney->id}/fixtures/{$semi1->id}/score", [
                'expected_version' => $tourney->fresh()->version,
                'winner_id' => $semi1->participant_two_id, // Player 2 is now winner
                'score' => ['p1' => 0, 'p2' => 2],
                'reason' => 'Line call dispute resolved',
            ]);
        $correctSemi1->assertOk();

        // Downstream final fixture slot 1 is now Player 2!
        $this->assertSame($semi1->participant_two_id, $final->fresh()->participant_one_id);

        // 4. Now play and COMPLETE the Final
        $finalWinner = $final->fresh()->participant_one_id;
        $this->actingAs($this->owner)
            ->withHeaders($this->headers('final-score-'))
            ->postJson("/api/tournaments/{$tourney->id}/fixtures/{$final->id}/score", [
                'expected_version' => $tourney->fresh()->version,
                'winner_id' => $finalWinner,
                'score' => ['p1' => 2, 'p2' => 1],
            ])
            ->assertOk();

        // 5. Attempt to correct Semi 1 now that Final is ALREADY COMPLETED -> 409 CONFLICT!
        $blockedCorrection = $this->actingAs($this->owner)
            ->withHeaders($this->headers('blocked-correct-'))
            ->postJson("/api/tournaments/{$tourney->id}/fixtures/{$semi1->id}/score", [
                'expected_version' => $tourney->fresh()->version,
                'winner_id' => $semi1->participant_one_id,
                'score' => ['p1' => 2, 'p2' => 0],
                'reason' => 'Late video review',
            ]);
        $blockedCorrection->assertStatus(409);
        $this->assertStringContainsString('downstream round has already been completed', $blockedCorrection->json('message'));
    }

    /**
     * Task 10.5 [D67]: Finalize locks results & controlled reopening allows modification with reason.
     */
    public function test_finalize_locks_results_and_controlled_reopening_allows_modifications(): void
    {
        $service = app(TournamentService::class);

        $tourney = $service->createAndGenerate(
            $this->owner,
            $this->organization->id,
            'Lock & Reopen Tourney',
            'single_elimination',
            [$this->player1->id, $this->player2->id]
        );

        $fixture = $tourney->fixtures()->first();

        // 1. Attempt to finalize while fixture is pending -> 422
        $preFinalize = $this->actingAs($this->owner)
            ->withHeaders($this->headers('fin-pre-'))
            ->postJson("/api/tournaments/{$tourney->id}/finalize");
        $preFinalize->assertStatus(422);

        // 2. Complete the match
        $this->actingAs($this->owner)
            ->withHeaders($this->headers('score-fin-'))
            ->postJson("/api/tournaments/{$tourney->id}/fixtures/{$fixture->id}/score", [
                'expected_version' => $tourney->version,
                'winner_id' => $fixture->participant_one_id,
                'score' => ['p1' => 21, 'p2' => 10],
            ])
            ->assertOk();

        // 3. Finalize tournament -> 200 OK
        $finRes = $this->actingAs($this->owner)
            ->withHeaders($this->headers('fin-ok-'))
            ->postJson("/api/tournaments/{$tourney->id}/finalize");
        $finRes->assertOk();
        $this->assertSame('finalized', $finRes->json('tournament.status'));
        $this->assertNotNull($finRes->json('tournament.settings.finalized_at'));

        // 4. Once finalized, actions are locked with 409
        // A. Score change rejected
        $this->actingAs($this->owner)
            ->withHeaders($this->headers('fin-score-block-'))
            ->postJson("/api/tournaments/{$tourney->id}/fixtures/{$fixture->id}/score", [
                'expected_version' => $tourney->fresh()->version,
                'winner_id' => $fixture->participant_two_id,
                'score' => ['p1' => 10, 'p2' => 21],
            ])
            ->assertStatus(409);

        // B. Bracket regenerate rejected
        $this->actingAs($this->owner)
            ->withHeaders($this->headers('fin-regen-block-'))
            ->postJson("/api/tournaments/{$tourney->id}/regenerate")
            ->assertStatus(409);

        // C. Add participant rejected
        $this->actingAs($this->owner)
            ->withHeaders($this->headers('fin-part-block-'))
            ->postJson("/api/tournaments/{$tourney->id}/participants", [
                'user_id' => $this->player3->id,
            ])
            ->assertStatus(409);

        // 5. Reopening: Customer cannot reopen (403)
        $this->actingAs($this->customerOfficial)
            ->withHeaders($this->headers('reopen-cust-'))
            ->postJson("/api/tournaments/{$tourney->id}/reopen", ['reason' => 'I want to fix'])
            ->assertStatus(403);

        // 6. Owner must provide min 5 chars reason
        $this->actingAs($this->owner)
            ->withHeaders($this->headers('reopen-short-'))
            ->postJson("/api/tournaments/{$tourney->id}/reopen", ['reason' => 'fix'])
            ->assertStatus(422);

        // 7. Owner reopens successfully with valid reason
        $reopenRes = $this->actingAs($this->owner)
            ->withHeaders($this->headers('reopen-ok-'))
            ->postJson("/api/tournaments/{$tourney->id}/reopen", [
                'reason' => 'Overturning disqualified finalist after positive doping test cleared on appeal',
            ]);
        $reopenRes->assertOk();
        $this->assertSame('in_progress', $reopenRes->json('tournament.status'));

        // Verify tournament.reopened event
        $reopenedEvent = TournamentEvent::where('tournament_id', $tourney->id)
            ->where('event_type', 'tournament.reopened')
            ->first();
        $this->assertNotNull($reopenedEvent);
        $this->assertSame('Overturning disqualified finalist after positive doping test cleared on appeal', $reopenedEvent->payload['reason']);

        // Modifications are now allowed again!
        $this->actingAs($this->owner)
            ->withHeaders($this->headers('score-post-reopen-'))
            ->postJson("/api/tournaments/{$tourney->id}/fixtures/{$fixture->id}/score", [
                'expected_version' => $tourney->fresh()->version,
                'winner_id' => $fixture->participant_two_id,
                'score' => ['p1' => 10, 'p2' => 21],
                'reason' => 'Score corrected after tournament reopened',
            ])
            ->assertOk();
    }
}
