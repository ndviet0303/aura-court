<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Tournament;
use App\Models\TournamentEvent;
use App\Models\TournamentFixture;
use App\Models\TournamentParticipant;
use App\Models\TournamentStanding;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Services\Tournament\TournamentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentRegistrationAndLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $owner;
    private User $player1;
    private User $player2;
    private User $player3;
    private WalletAccount $wallet1;
    private WalletAccount $wallet2;
    private WalletAccount $wallet3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Aura Championship Tenant',
            'slug' => 'aura-champ-' . uniqid(),
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

        $this->player1 = User::factory()->create(['name' => 'Player Alice']);
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

        $this->player2 = User::factory()->create(['name' => 'Player Bob']);
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
            'balance' => 300000,
            'status' => 'active',
        ]);

        $this->player3 = User::factory()->create(['name' => 'Player Charlie']);
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
            'balance' => 50000, // Insufficient for 300k fee
            'status' => 'active',
        ]);
    }

    private function headers(string $keyPrefix = 'test-'): array
    {
        return [
            'X-Organization-ID' => (string) $this->organization->id,
            'Idempotency-Key' => $keyPrefix . uniqid(),
        ];
    }

    public function test_tournament_model_and_show_api_publishes_entry_fee_and_capacity(): void
    {
        $tournament = Tournament::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->owner->id,
            'name' => 'Aura Masters Summer 2026',
            'format' => 'single_elimination',
            'status' => 'open',
            'settings' => [
                'entry_fee' => '300.000 ₫ / VĐV',
                'max_participants' => 8,
                'sport' => 'badminton',
            ],
        ]);

        $this->assertSame('300.000 ₫ / VĐV', $tournament->entry_fee);
        $this->assertSame(8, $tournament->max_participants);
        $this->assertSame(300000, TournamentService::parseEntryFee($tournament->entry_fee));

        $res = $this->actingAs($this->player1)
            ->withHeaders($this->headers())
            ->getJson("/api/v2/tournaments/{$tournament->id}");

        $res->assertOk();
        $res->assertJsonPath('tournament.entry_fee', '300.000 ₫ / VĐV');
        $res->assertJsonPath('tournament.max_participants', 8);
    }

    public function test_paid_tournament_registration_debits_wallet_creates_participant_and_balanced_ledger(): void
    {
        $tournament = Tournament::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->owner->id,
            'name' => 'Aura Open 2026',
            'format' => 'single_elimination',
            'status' => 'open',
            'settings' => [
                'entry_fee' => '300.000 ₫ / VĐV',
                'max_participants' => 16,
            ],
        ]);

        $res = $this->actingAs($this->player1)
            ->withHeaders($this->headers('reg-1-'))
            ->postJson("/api/v2/tournaments/{$tournament->id}/register", [
                'name' => 'Alice Athlete',
                'phone' => '0901112233',
            ]);

        $res->assertCreated();
        $res->assertJsonPath('already_registered', false);
        $res->assertJsonPath('receipt.entry_fee', 300000);
        $res->assertJsonPath('receipt.currency', 'VND');
        $res->assertJsonPath('receipt.status', 'confirmed');
        $res->assertJsonStructure(['participant' => ['id', 'user_id', 'seed', 'status'], 'receipt' => ['receipt_code', 'tournament_id', 'entry_fee']]);

        // Participant persisted
        $this->assertDatabaseHas('tournament_participants', [
            'tournament_id' => $tournament->id,
            'user_id' => $this->player1->id,
            'status' => 'registered',
        ]);

        // Standing created
        $this->assertDatabaseHas('tournament_standings', [
            'tournament_id' => $tournament->id,
            'participant_id' => $res->json('participant.id'),
        ]);

        // Wallet debited exactly 300,000 VND
        $this->assertSame(200000, $this->wallet1->fresh()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_account_id' => $this->wallet1->id,
            'type' => 'debit',
            'amount' => 300000,
            'source_type' => 'tournament_registration',
            'source_id' => $tournament->id,
        ]);

        // Balanced financial posting
        $journal = Journal::where('event_type', 'tournament.registration')
            ->where('event_id', "tournament:{$tournament->id}:participant:{$res->json('participant.id')}")
            ->firstOrFail();
        $this->assertSame(300000, $journal->total_debit);
        $this->assertSame(300000, $journal->total_credit);
    }

    public function test_free_tournament_registration_persists_participant_without_wallet_debit(): void
    {
        $tournament = Tournament::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->owner->id,
            'name' => 'Community Friendly Cup',
            'format' => 'single_elimination',
            'status' => 'open',
            'settings' => [
                'entry_fee' => '0 ₫',
                'max_participants' => 16,
            ],
        ]);

        $res = $this->actingAs($this->player1)
            ->withHeaders($this->headers('reg-free-'))
            ->postJson("/api/v2/tournaments/{$tournament->id}/register", [
                'name' => 'Alice Free',
            ]);

        $res->assertCreated();
        $res->assertJsonPath('receipt.entry_fee', 0);

        // Wallet balance untouched
        $this->assertSame(500000, $this->wallet1->fresh()->balance);
        $this->assertDatabaseMissing('wallet_transactions', [
            'wallet_account_id' => $this->wallet1->id,
            'source_id' => $tournament->id,
        ]);
    }

    public function test_tournament_registration_is_idempotent_and_safe_on_retry(): void
    {
        $tournament = Tournament::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->owner->id,
            'name' => 'Idempotent Tourney',
            'format' => 'single_elimination',
            'status' => 'open',
            'settings' => [
                'entry_fee' => '300000',
                'max_participants' => 16,
            ],
        ]);

        // First attempt
        $res1 = $this->actingAs($this->player2)
            ->withHeaders($this->headers('reg-retry-1-'))
            ->postJson("/api/v2/tournaments/{$tournament->id}/register");
        $res1->assertCreated();
        $this->assertSame(0, $this->wallet2->fresh()->balance);

        // Second attempt: stable retry returns 200 with same receipt without second debit
        $res2 = $this->actingAs($this->player2)
            ->withHeaders($this->headers('reg-retry-2-'))
            ->postJson("/api/v2/tournaments/{$tournament->id}/register");
        $res2->assertOk();
        $res2->assertJsonPath('already_registered', true);
        $res2->assertJsonPath('participant.id', $res1->json('participant.id'));
        $res2->assertJsonPath('receipt.receipt_code', $res1->json('receipt.receipt_code'));

        // Balance is STILL 0 (never negative or debited twice)
        $this->assertSame(0, $this->wallet2->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('wallet_account_id', $this->wallet2->id)->count());
    }

    public function test_tournament_registration_fails_on_insufficient_wallet_balance(): void
    {
        $tournament = Tournament::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->owner->id,
            'name' => 'Expensive Tourney',
            'format' => 'single_elimination',
            'status' => 'open',
            'settings' => [
                'entry_fee' => '300000',
                'max_participants' => 16,
            ],
        ]);

        // Player 3 has only 50,000 VND
        $res = $this->actingAs($this->player3)
            ->withHeaders($this->headers('insuf-'))
            ->postJson("/api/v2/tournaments/{$tournament->id}/register");

        $res->assertStatus(422);

        // No participant or debit created
        $this->assertSame(50000, $this->wallet3->fresh()->balance);
        $this->assertDatabaseMissing('tournament_participants', [
            'tournament_id' => $tournament->id,
            'user_id' => $this->player3->id,
        ]);
    }

    public function test_last_place_race_rejects_with_conflict_when_capacity_reached(): void
    {
        $tournament = Tournament::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->owner->id,
            'name' => 'Limited 2-Player Tourney',
            'format' => 'single_elimination',
            'status' => 'open',
            'settings' => [
                'entry_fee' => '100000',
                'max_participants' => 2,
            ],
        ]);

        // Player 1 takes slot 1
        $this->actingAs($this->player1)
            ->withHeaders($this->headers('cap-1-'))
            ->postJson("/api/v2/tournaments/{$tournament->id}/register")
            ->assertCreated();

        // Player 2 takes final slot 2
        $this->actingAs($this->player2)
            ->withHeaders($this->headers('cap-2-'))
            ->postJson("/api/v2/tournaments/{$tournament->id}/register")
            ->assertCreated();

        // Player 3 attempts to register when capacity is full
        $res = $this->actingAs($this->player3)
            ->withHeaders($this->headers('cap-3-'))
            ->postJson("/api/v2/tournaments/{$tournament->id}/register");

        $res->assertStatus(409);
        $this->assertStringContainsString('maximum participant capacity', $res->json('message'));

        // Player 3 wallet was NOT debited
        $this->assertSame(50000, $this->wallet3->fresh()->balance);
        $this->assertSame(2, $tournament->participants()->count());
    }

    public function test_tournament_registration_endpoint_returns_persisted_participant_and_receipt(): void
    {
        $tournament = Tournament::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->owner->id,
            'name' => 'Registration Check Tourney',
            'format' => 'single_elimination',
            'status' => 'open',
            'settings' => [
                'entry_fee' => '250000',
                'max_participants' => 8,
            ],
        ]);

        // Before registration
        $before = $this->actingAs($this->player1)
            ->withHeaders($this->headers())
            ->getJson("/api/v2/tournaments/{$tournament->id}/registration");
        $before->assertOk();
        $before->assertJsonPath('registered', false);
        $before->assertJsonPath('participant', null);

        // Register
        $reg = $this->actingAs($this->player1)
            ->withHeaders($this->headers('reg-chk-'))
            ->postJson("/api/v2/tournaments/{$tournament->id}/register");
        $reg->assertCreated();

        // After registration reload
        $after = $this->actingAs($this->player1)
            ->withHeaders($this->headers())
            ->getJson("/api/v2/tournaments/{$tournament->id}/registration");
        $after->assertOk();
        $after->assertJsonPath('registered', true);
        $after->assertJsonPath('participant.id', $reg->json('participant.id'));
        $after->assertJsonPath('receipt.receipt_code', $reg->json('receipt.receipt_code'));
        $after->assertJsonPath('receipt.entry_fee', 250000);
    }

    public function test_participant_check_in_and_uncheck_in_with_proper_authorization(): void
    {
        $tournament = Tournament::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->owner->id,
            'name' => 'Check-in Tourney',
            'format' => 'single_elimination',
            'status' => 'open',
        ]);

        $participant = TournamentParticipant::create([
            'organization_id' => $this->organization->id,
            'tournament_id' => $tournament->id,
            'user_id' => $this->player1->id,
            'seed' => 1,
            'status' => 'registered',
        ]);

        // 1. Participant self check-in
        $checkInRes = $this->actingAs($this->player1)
            ->withHeaders($this->headers('ci-1-'))
            ->postJson("/api/v2/tournament-participants/{$participant->id}/check-in", [
                'checked_in' => true,
            ]);
        $checkInRes->assertOk();
        $this->assertSame('checked_in', $participant->fresh()->status);
        $this->assertNotNull($participant->fresh()->checked_in_at);

        // 2. Org owner can uncheck-in or check-in
        $uncheckRes = $this->actingAs($this->owner)
            ->withHeaders($this->headers('ci-2-'))
            ->postJson("/api/v2/tournament-participants/{$participant->id}/check-in", [
                'checked_in' => false,
            ]);
        $uncheckRes->assertOk();
        $this->assertSame('registered', $participant->fresh()->status);
        $this->assertNull($participant->fresh()->checked_in_at);

        // 3. Another customer player cannot check in someone else
        $unauthRes = $this->actingAs($this->player2)
            ->withHeaders($this->headers('ci-3-'))
            ->postJson("/api/v2/tournament-participants/{$participant->id}/check-in", [
                'checked_in' => true,
            ]);
        $unauthRes->assertStatus(403);
    }

    public function test_bracket_regeneration_for_single_elimination_and_round_robin(): void
    {
        // Setup 4 participants
        $players = [$this->player1, $this->player2, $this->player3, User::factory()->create()];
        foreach ($players as $index => $player) {
            OrganizationMember::firstOrCreate(
                ['organization_id' => $this->organization->id, 'user_id' => $player->id],
                ['role' => 'customer', 'status' => 'active']
            );
        }

        // Single elimination
        $singleTourney = app(TournamentService::class)->createAndGenerate(
            $this->owner,
            $this->organization->id,
            'SE Tourney',
            'single_elimination',
            array_map(fn ($p) => $p->id, $players)
        );

        $this->assertSame(3, $singleTourney->fixtures()->count()); // Round 1 (2 matches) + Round 2 (1 final)

        // Regenerate bracket
        $regenRes = $this->actingAs($this->owner)
            ->withHeaders($this->headers('regen-se-'))
            ->postJson("/api/v2/tournaments/{$singleTourney->id}/regenerate");
        $regenRes->assertOk();
        $this->assertSame(3, $singleTourney->fixtures()->count());

        // Round robin
        $rrTourney = app(TournamentService::class)->createAndGenerate(
            $this->owner,
            $this->organization->id,
            'RR Tourney',
            'round_robin',
            array_map(fn ($p) => $p->id, $players)
        );

        // 4 players -> 4*3/2 = 6 fixtures
        $this->assertSame(6, $rrTourney->fixtures()->count());
        $this->assertSame(4, $rrTourney->standings()->count());

        // Regenerate round robin
        $regenRr = $this->actingAs($this->owner)
            ->withHeaders($this->headers('regen-rr-'))
            ->postJson("/api/v2/tournaments/{$rrTourney->id}/regenerate");
        $regenRr->assertOk();
        $this->assertSame(6, $rrTourney->fixtures()->count());
    }

    public function test_score_submission_advances_winner_in_single_elimination(): void
    {
        $players = [$this->player1, $this->player2];
        $tournament = app(TournamentService::class)->createAndGenerate(
            $this->owner,
            $this->organization->id,
            'Advancement Tourney',
            'single_elimination',
            array_map(fn ($p) => $p->id, $players)
        );

        $fixture = $tournament->fixtures->firstWhere('round', 1);
        $this->assertNotNull($fixture);

        $res = $this->actingAs($this->owner)
            ->withHeaders($this->headers('score-se-'))
            ->postJson("/api/v2/tournaments/{$tournament->id}/fixtures/{$fixture->id}/score", [
                'expected_version' => $tournament->version,
                'winner_id' => $fixture->participant_one_id,
                'score' => ['sets' => [['p1' => 21, 'p2' => 18], ['p1' => 21, 'p2' => 15]]],
            ]);

        $res->assertOk();
        $res->assertJsonPath('fixture.status', 'completed');
        $res->assertJsonPath('fixture.winner_id', $fixture->participant_one_id);
    }

    public function test_score_submission_updates_standings_in_round_robin(): void
    {
        $players = [$this->player1, $this->player2];
        $tournament = app(TournamentService::class)->createAndGenerate(
            $this->owner,
            $this->organization->id,
            'Standing Tourney',
            'round_robin',
            array_map(fn ($p) => $p->id, $players)
        );

        $fixture = $tournament->fixtures->first();
        $winnerId = $fixture->participant_one_id;
        $loserId = $fixture->participant_two_id;

        $res = $this->actingAs($this->owner)
            ->withHeaders($this->headers('score-rr-'))
            ->postJson("/api/v2/tournaments/{$tournament->id}/fixtures/{$fixture->id}/score", [
                'expected_version' => $tournament->version,
                'winner_id' => $winnerId,
                'score' => ['p1' => 2, 'p2' => 0],
            ]);

        $res->assertOk();

        // Winner gets 3 points, 1 win, rank 1
        $winnerStanding = TournamentStanding::where('participant_id', $winnerId)->first();
        $this->assertSame(1, $winnerStanding->played);
        $this->assertSame(1, $winnerStanding->wins);
        $this->assertSame(3, $winnerStanding->points);
        $this->assertSame(1, $winnerStanding->rank);

        // Loser gets 0 points, 1 loss
        $loserStanding = TournamentStanding::where('participant_id', $loserId)->first();
        $this->assertSame(1, $loserStanding->played);
        $this->assertSame(1, $loserStanding->losses);
        $this->assertSame(0, $loserStanding->points);
    }

    public function test_realtime_events_and_gap_resynchronization(): void
    {
        $players = [$this->player1, $this->player2];
        $tournament = app(TournamentService::class)->createAndGenerate(
            $this->owner,
            $this->organization->id,
            'Events Tourney',
            'single_elimination',
            array_map(fn ($p) => $p->id, $players)
        );

        $fixture = $tournament->fixtures->first();

        // Submit score to generate an event
        $this->actingAs($this->owner)
            ->withHeaders($this->headers('evt-score-'))
            ->postJson("/api/v2/tournaments/{$tournament->id}/fixtures/{$fixture->id}/score", [
                'expected_version' => $tournament->version,
                'winner_id' => $fixture->participant_one_id,
                'score' => ['p1' => 21, 'p2' => 19],
            ])
            ->assertOk();

        // 1. GET events after version 0
        $eventsRes = $this->actingAs($this->player1)
            ->withHeaders($this->headers())
            ->getJson("/api/v2/tournaments/{$tournament->id}/events?after_version=0");
        $eventsRes->assertOk();
        $events = $eventsRes->json('events');
        $this->assertNotEmpty($events);
        $this->assertCount(2, $events);
        $this->assertSame('tournament.generated', $events[0]['event_type']);
        $this->assertSame('tournament.score_updated', $events[1]['event_type']);

        // After version 1: gap resync returns only the missing event
        $gapRes = $this->actingAs($this->player1)
            ->withHeaders($this->headers())
            ->getJson("/api/v2/tournaments/{$tournament->id}/events?after_version=1");
        $gapRes->assertOk();
        $gapEvents = $gapRes->json('events');
        $this->assertCount(1, $gapEvents);
        $this->assertSame('tournament.score_updated', $gapEvents[0]['event_type']);
        // 2. Poll current version and authoritative fixtures
        $pollRes = $this->actingAs($this->player1)
            ->withHeaders($this->headers())
            ->getJson("/api/v2/tournaments/{$tournament->id}/poll");
        $pollRes->assertOk();
        $this->assertSame($tournament->fresh()->version, $pollRes->json('current_version'));
        $this->assertNotNull($pollRes->json('tournament.fixtures'));
    }
}
