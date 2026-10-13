<?php

namespace Tests\Feature;

use App\Models\FinanceException;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\ReceivingConnection;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\TournamentStanding;
use App\Models\User;
use App\Models\Venue;
use App\Models\WalletTransaction;
use App\Services\Tournament\TournamentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PaysOwnerByQr;
use Tests\TestCase;

class TournamentRegistrationAndLifecycleTest extends TestCase
{
    use PaysOwnerByQr;
    use RefreshDatabase;

    private Organization $organization;

    private User $owner;

    private User $player1;

    private User $player2;

    private User $player3;

    private Venue $venue;

    private ReceivingConnection $connection;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Aura Championship Tenant',
            'slug' => 'aura-champ-'.uniqid(),
        ]);

        config([
            'features.pilot_organizations' => [(string) $this->organization->id],
            'features.flags.social_matchmaking_tournaments' => true,
        ]);
        $this->venue = Venue::create([
            'organization_id' => $this->organization->id,
            'name' => 'Sân Cầu Giấy',
            'slug' => 'cau-giay-'.uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);
        [$this->connection, $this->secret] = $this->connectOwnerReceiver($this->venue);

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

        $this->player2 = User::factory()->create(['name' => 'Player Bob']);
        OrganizationMember::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->player2->id,
            'role' => 'customer',
            'status' => 'active',
        ]);

        $this->player3 = User::factory()->create(['name' => 'Player Charlie']);
        OrganizationMember::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->player3->id,
            'role' => 'customer',
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
            ->getJson("/api/tournaments/{$tournament->id}");

        $res->assertOk();
        $res->assertJsonPath('tournament.entry_fee', '300.000 ₫ / VĐV');
        $res->assertJsonPath('tournament.max_participants', 8);
    }

    private function paidTournament(int $maxParticipants = 16, array $settings = []): Tournament
    {
        return Tournament::create([
            'organization_id' => $this->organization->id,
            'venue_id' => $this->venue->id,
            'created_by' => $this->owner->id,
            'name' => 'Aura Open 2026',
            'format' => 'single_elimination',
            'status' => 'open',
            'settings' => ['entry_fee' => '300.000 ₫ / VĐV', 'max_participants' => $maxParticipants] + $settings,
        ]);
    }

    private function register(User $player, Tournament $tournament)
    {
        return $this->actingAs($player)
            ->withHeaders($this->headers('reg-'))
            ->postJson("/api/tournaments/{$tournament->id}/register", ['name' => $player->name, 'phone' => '0901112233']);
    }

    public function test_paid_registration_opens_the_owner_qr_and_the_receipt_registers_the_player(): void
    {
        $tournament = $this->paidTournament();

        $res = $this->register($this->player1, $tournament)
            ->assertAccepted()
            ->assertJsonPath('registered', false)
            ->assertJsonPath('participant', null)
            ->assertJsonPath('payment.amount', 300000)
            ->assertJsonPath('payment.status', 'pending')
            ->assertJsonPath('payment.receiver.account_number', '0987654321');
        $this->assertNotEmpty($res->json('payment.qr_url'));
        $reference = $res->json('payment.reference');
        $this->assertStringStartsWith('AC', $reference);
        $this->assertDatabaseMissing('tournament_participants', ['tournament_id' => $tournament->id]);
        $attempt = OwnerPaymentAttempt::where('reference', $reference)->firstOrFail();
        $this->assertSame(['tournament_registration', $tournament->uuid, $this->player1->id], [$attempt->purpose, $attempt->purpose_id, (int) $attempt->user_id]);
        $this->assertTrue($attempt->expires_at->lte(now()->addMinutes(15)));

        // The payment page polls the registration until the owner receipt lands.
        $this->actingAs($this->player1)->withHeaders($this->headers())
            ->getJson("/api/tournaments/{$tournament->id}/registration")
            ->assertOk()
            ->assertJsonPath('registered', false)
            ->assertJsonPath('payment.reference', $reference);

        $receipt = $this->payOwner($this->connection, $this->secret, $reference, 300000, 'SEPAY-TRN-1');
        $this->assertSame(OwnerPaymentReceipt::STATUS_ALLOCATED, $receipt->status);

        $after = $this->actingAs($this->player1)->withHeaders($this->headers())
            ->getJson("/api/tournaments/{$tournament->id}/registration")
            ->assertOk()
            ->assertJsonPath('registered', true)
            ->assertJsonPath('payment', null)
            ->assertJsonPath('receipt.entry_fee', 300000)
            ->assertJsonPath('receipt.payment_reference', $reference)
            ->assertJsonPath('receipt.status', 'confirmed');
        $this->assertDatabaseHas('tournament_participants', ['tournament_id' => $tournament->id, 'user_id' => $this->player1->id, 'status' => 'registered']);
        $this->assertDatabaseHas('tournament_standings', ['tournament_id' => $tournament->id, 'participant_id' => $after->json('participant.id')]);
        $this->assertTrue(Journal::where('event_type', 'owner.direct_receipt')->exists());
        $this->assertFalse(Journal::where('event_type', 'tournament.registration')->exists());
        $this->assertSame(0, WalletTransaction::count());

        // Registering again after payment is a no-op.
        $this->register($this->player1, $tournament)->assertOk()->assertJsonPath('already_registered', true);
    }

    public function test_free_tournament_registration_persists_participant_without_payment(): void
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

        $res = $this->register($this->player1, $tournament)
            ->assertCreated()
            ->assertJsonPath('registered', true)
            ->assertJsonPath('payment', null)
            ->assertJsonPath('receipt.entry_fee', 0);

        $this->actingAs($this->player1)->withHeaders($this->headers())
            ->getJson("/api/tournaments/{$tournament->id}/registration")
            ->assertOk()
            ->assertJsonPath('registered', true)
            ->assertJsonPath('receipt.receipt_code', $res->json('receipt.receipt_code'));
        $this->assertSame(0, OwnerPaymentAttempt::count());
        $this->assertSame(0, WalletTransaction::count());
    }

    public function test_creating_a_tournament_stores_only_the_settings_given(): void
    {
        $res = $this->actingAs($this->owner)
            ->withHeaders($this->headers('create-'))
            ->postJson('/api/tournaments', [
                'name' => 'Giải Phong Trào',
                'format' => 'single_elimination',
                'sport' => 'tennis',
                'venue' => 'Sân Phú Nhuận',
                // The organizer cleared the pre-filled fee.
                'entry_fee' => '',
            ]);

        $res->assertCreated();
        $res->assertJsonPath('tournament.entry_fee', null);
        $res->assertJsonPath('tournament.max_participants', 16);
        // No demo city, prize, fee, dates or category is filled in.
        $tournament = Tournament::findOrFail($res->json('tournament.id'));
        $this->assertEquals(['sport' => 'tennis', 'venue' => 'Sân Phú Nhuận', 'max_participants' => 16], $tournament->settings);
        $this->assertNull($tournament->venue_id);

        // With no published fee, entry is free.
        $this->register($this->player1, $tournament)
            ->assertCreated()
            ->assertJsonPath('receipt.entry_fee', 0);
    }

    public function test_a_paid_tournament_needs_an_own_venue_with_a_verified_connection(): void
    {
        $create = fn (array $extra) => $this->actingAs($this->owner)
            ->withHeaders($this->headers('create-paid-'))
            ->postJson('/api/tournaments', ['name' => 'Giải Có Phí', 'format' => 'single_elimination', 'entry_fee' => '50000'] + $extra);

        $create([])->assertStatus(422);
        $otherOrganization = Organization::create(['name' => 'Other', 'slug' => 'other-'.uniqid()]);
        $foreignVenue = Venue::create(['organization_id' => $otherOrganization->id, 'name' => 'Foreign', 'slug' => 'foreign-'.uniqid(), 'status' => 'published', 'is_active' => true]);
        $create(['venue_id' => $foreignVenue->id])->assertStatus(422);
        $unconnected = Venue::create(['organization_id' => $this->organization->id, 'name' => 'No Account', 'slug' => 'no-account-'.uniqid(), 'status' => 'published', 'is_active' => true]);
        $create(['venue_id' => $unconnected->id])->assertStatus(422);
        $this->assertSame(0, Tournament::count());

        $res = $create(['venue_id' => $this->venue->id, 'venue' => 'free text'])->assertCreated();
        $tournament = Tournament::findOrFail($res->json('tournament.id'));
        $this->assertSame($this->venue->id, (int) $tournament->venue_id);
        $this->assertSame('Sân Cầu Giấy', $tournament->settings['venue']);
    }

    public function test_paid_registration_is_idempotent_per_player(): void
    {
        $tournament = $this->paidTournament();

        $first = $this->register($this->player2, $tournament)->assertAccepted();
        $second = $this->register($this->player2, $tournament)->assertAccepted();

        $this->assertSame($first->json('payment.reference'), $second->json('payment.reference'));
        $this->assertSame(1, OwnerPaymentAttempt::where('purpose_id', $tournament->uuid)->count());
    }

    public function test_paid_registration_is_refused_when_the_venue_cannot_collect(): void
    {
        $tournament = $this->paidTournament();
        $tournament->forceFill(['venue_id' => null])->save();

        $this->register($this->player3, $tournament)->assertConflict();

        $this->assertSame(0, OwnerPaymentAttempt::count());
        $this->assertDatabaseMissing('tournament_participants', ['tournament_id' => $tournament->id]);
    }

    public function test_capacity_counts_live_pending_payments(): void
    {
        $tournament = $this->paidTournament(2);

        $this->register($this->player1, $tournament)->assertAccepted();
        $this->register($this->player2, $tournament)->assertAccepted();

        $res = $this->register($this->player3, $tournament)->assertConflict();
        $this->assertStringContainsString('maximum participant capacity', $res->json('message'));

        // A hold that lapses frees its place.
        $this->travel(16)->minutes();
        $this->register($this->player3, $tournament)->assertAccepted();
        $this->assertSame(0, $tournament->participants()->count());
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
            ->postJson("/api/tournament-participants/{$participant->id}/check-in", [
                'checked_in' => true,
            ]);
        $checkInRes->assertOk();
        $this->assertSame('checked_in', $participant->fresh()->status);
        $this->assertNotNull($participant->fresh()->checked_in_at);

        // 2. Org owner can uncheck-in or check-in
        $uncheckRes = $this->actingAs($this->owner)
            ->withHeaders($this->headers('ci-2-'))
            ->postJson("/api/tournament-participants/{$participant->id}/check-in", [
                'checked_in' => false,
            ]);
        $uncheckRes->assertOk();
        $this->assertSame('registered', $participant->fresh()->status);
        $this->assertNull($participant->fresh()->checked_in_at);

        // 3. Another customer player cannot check in someone else
        $unauthRes = $this->actingAs($this->player2)
            ->withHeaders($this->headers('ci-3-'))
            ->postJson("/api/tournament-participants/{$participant->id}/check-in", [
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
            ->postJson("/api/tournaments/{$singleTourney->id}/regenerate");
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
            ->postJson("/api/tournaments/{$rrTourney->id}/regenerate");
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
            ->postJson("/api/tournaments/{$tournament->id}/fixtures/{$fixture->id}/score", [
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
            ->postJson("/api/tournaments/{$tournament->id}/fixtures/{$fixture->id}/score", [
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
            ->postJson("/api/tournaments/{$tournament->id}/fixtures/{$fixture->id}/score", [
                'expected_version' => $tournament->version,
                'winner_id' => $fixture->participant_one_id,
                'score' => ['p1' => 21, 'p2' => 19],
            ])
            ->assertOk();

        // 1. GET events after version 0
        $eventsRes = $this->actingAs($this->player1)
            ->withHeaders($this->headers())
            ->getJson("/api/tournaments/{$tournament->id}/events?after_version=0");
        $eventsRes->assertOk();
        $events = $eventsRes->json('events');
        $this->assertNotEmpty($events);
        $this->assertCount(2, $events);
        $this->assertSame('tournament.generated', $events[0]['event_type']);
        $this->assertSame('tournament.score_updated', $events[1]['event_type']);

        // After version 1: gap resync returns only the missing event
        $gapRes = $this->actingAs($this->player1)
            ->withHeaders($this->headers())
            ->getJson("/api/tournaments/{$tournament->id}/events?after_version=1");
        $gapRes->assertOk();
        $gapEvents = $gapRes->json('events');
        $this->assertCount(1, $gapEvents);
        $this->assertSame('tournament.score_updated', $gapEvents[0]['event_type']);
        // 2. Poll current version and authoritative fixtures
        $pollRes = $this->actingAs($this->player1)
            ->withHeaders($this->headers())
            ->getJson("/api/tournaments/{$tournament->id}/poll");
        $pollRes->assertOk();
        $this->assertSame($tournament->fresh()->version, $pollRes->json('current_version'));
        $this->assertNotNull($pollRes->json('tournament.fixtures'));
    }

    public function test_a_repeated_receipt_registers_the_player_once(): void
    {
        $tournament = $this->paidTournament();
        $reference = $this->register($this->player1, $tournament)->json('payment.reference');

        $at = now()->addSeconds(2)->toIso8601String();
        $this->payOwner($this->connection, $this->secret, $reference, 300000, 'SEPAY-TRN-DUP', $at);
        $this->payOwner($this->connection, $this->secret, $reference, 300000, 'SEPAY-TRN-DUP', $at);
        $second = $this->payOwner($this->connection, $this->secret, $reference, 300000, 'SEPAY-TRN-DUP-2', $at);

        $this->assertSame(1, TournamentParticipant::where('tournament_id', $tournament->id)->count());
        $this->assertSame([OwnerPaymentReceipt::STATUS_EXCEPTION, 'attempt_not_pending'], [$second->status, $second->exception_code]);
    }

    public function test_late_or_unplaceable_receipts_become_exceptions_without_a_participant(): void
    {
        $tournament = $this->paidTournament();
        $late = $this->register($this->player1, $tournament)->json('payment.reference');
        $closed = $this->register($this->player2, $tournament)->json('payment.reference');

        // Transfer after the hold expired.
        $receipt = $this->payOwner($this->connection, $this->secret, $late, 300000, 'SEPAY-TRN-LATE', now()->addMinutes(20)->toIso8601String());
        $this->assertSame([OwnerPaymentReceipt::STATUS_LATE, 'transfer_after_expiry'], [$receipt->status, $receipt->exception_code]);

        // Transfer in time, but the tournament closed registration meanwhile.
        $tournament->forceFill(['status' => 'in_progress'])->save();
        $receipt = $this->payOwner($this->connection, $this->secret, $closed, 300000, 'SEPAY-TRN-CLOSED');
        $this->assertSame([OwnerPaymentReceipt::STATUS_EXCEPTION, 'tournament_registration_unavailable'], [$receipt->status, $receipt->exception_code]);
        $this->assertTrue(FinanceException::where('type', 'owner_payment_tournament_registration_unavailable')->exists());

        $this->assertSame(0, TournamentParticipant::where('tournament_id', $tournament->id)->count());
    }

    public function test_organizer_adds_to_a_paid_tournament_need_a_recorded_waiver_reason(): void
    {
        $tournament = $this->paidTournament();
        $add = fn (array $body) => $this->actingAs($this->owner)
            ->withHeaders($this->headers('add-'))
            ->postJson("/api/tournaments/{$tournament->id}/participants", ['user_id' => $this->player2->id] + $body);

        $add([])->assertStatus(422);
        $add(['waiver_reason' => '   '])->assertStatus(422);
        $this->assertSame(0, TournamentParticipant::where('tournament_id', $tournament->id)->count());

        $participantId = $add(['waiver_reason' => 'Khách mời của ban tổ chức'])->assertCreated()->json('participant.id');
        $this->assertDatabaseHas('tournament_participants', ['id' => $participantId, 'waiver_reason' => 'Khách mời của ban tổ chức']);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->owner->id,
            'action' => 'tournament.entry_fee_waived',
            'auditable_type' => TournamentParticipant::class,
            'auditable_id' => $participantId,
        ]);
        $this->assertSame(0, WalletTransaction::count());
        $this->assertSame(0, OwnerPaymentAttempt::count());
    }
}
