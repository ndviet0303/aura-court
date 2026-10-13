<?php

namespace Tests\Feature\Social;

use App\Models\Court;
use App\Models\MatchParticipation;
use App\Models\MatchPost;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlayerProfile;
use App\Models\QualifyingActivity;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\Reservation\CourtCommitmentConflictService;
use App\Services\Reservation\SlotSelectionPolicy;
use App\Services\Social\MatchService;
use App\Services\Social\ReputationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CommunityActivityAndReputationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $tenant;

    private Organization $otherTenant;

    private Venue $venue;

    private Court $court;

    private User $owner;

    private User $manager;

    private User $hostCustomer;

    private User $playerA;

    private User $playerB;

    private User $stranger;

    private MatchService $matchService;

    private ReputationService $reputationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Organization::create([
            'name' => 'Community Badminton Club',
            'slug' => 'community-badminton-'.uniqid(),
            'status' => 'active',
        ]);

        $this->otherTenant = Organization::create([
            'name' => 'Other Badminton Club',
            'slug' => 'other-badminton-'.uniqid(),
            'status' => 'active',
        ]);

        config([
            'features.flags.social_matchmaking_tournaments' => true,
            'features.pilot_organizations' => [(string) $this->tenant->id, (string) $this->otherTenant->id],
        ]);

        $this->venue = Venue::create([
            'organization_id' => $this->tenant->id,
            'name' => 'Community Stadium',
            'slug' => 'community-stadium-'.uniqid(),
            'status' => 'active',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ]);

        $this->court = Court::create([
            'venue_id' => $this->venue->id,
            'name' => 'Court 1',
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create(['name' => 'Venue Owner', 'email' => 'owner@test.vn']);
        $this->manager = User::factory()->create(['name' => 'Venue Manager', 'email' => 'manager@test.vn']);
        $this->hostCustomer = User::factory()->create(['name' => 'Host Customer', 'email' => 'host@test.vn']);
        $this->playerA = User::factory()->create(['name' => 'Player A', 'email' => 'playera@test.vn']);
        $this->playerB = User::factory()->create(['name' => 'Player B', 'email' => 'playerb@test.vn']);
        $this->stranger = User::factory()->create(['name' => 'Stranger User', 'email' => 'stranger@test.vn']);

        OrganizationMember::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->manager->id,
            'role' => OrganizationMember::ROLE_VENUE_MANAGER,
            'status' => 'active',
        ]);

        foreach ([$this->hostCustomer, $this->playerA, $this->playerB, $this->stranger] as $u) {
            OrganizationMember::create([
                'organization_id' => $this->tenant->id,
                'user_id' => $u->id,
                'role' => OrganizationMember::ROLE_CUSTOMER,
                'status' => 'active',
            ]);
        }

        $this->matchService = app(MatchService::class);
        $this->reputationService = app(ReputationService::class);
    }

    private function headersFor(User $user, ?Organization $org = null): array
    {
        $org = $org ?? $this->tenant;

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

    private function createConfirmedReservation(User $user, Carbon $startsAt, Carbon $endsAt, ?Court $court = null): array
    {
        $court = $court ?? $this->court;
        $reservation = Reservation::create([
            'organization_id' => $court->venue->organization_id,
            'user_id' => $user->id,
            'court_id' => $court->id,
            'code' => 'RES-'.uniqid(),
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => 120000,
            'confirmed_at' => now(),
        ]);

        $slot = ReservationSlot::create([
            'organization_id' => $court->venue->organization_id,
            'reservation_id' => $reservation->id,
            'court_id' => $court->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'unit_price' => 120000,
            'status' => 'confirmed',
        ]);

        $entitlement = ReservationEntitlement::create([
            'organization_id' => $court->venue->organization_id,
            'reservation_id' => $reservation->id,
            'reservation_slot_id' => $slot->id,
            'user_id' => $user->id,
            'court_id' => $court->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'amount' => 120000,
            'status' => 'active',
        ]);

        return [$reservation, $entitlement, $slot];
    }

    // ==========================================
    // Task 9.2 [D59]: Customer Booking Rights Host
    // ==========================================

    public function test_customer_with_confirmed_reservation_can_create_match_post_and_prevents_double_allocation(): void
    {
        $startsAt = now()->addDays(2)->setHour(14)->setMinute(0)->setSecond(0);
        $endsAt = now()->addDays(2)->setHour(16)->setMinute(0)->setSecond(0);

        [$reservation, $entitlement] = $this->createConfirmedReservation($this->hostCustomer, $startsAt, $endsAt);

        // 1. Host customer creates match post using their confirmed reservation
        $res = $this->auth($this->hostCustomer)
            ->postJson('/api/social/matches', [
                'title' => 'Buổi giao lưu thứ 7',
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
                'required_participants' => 4,
                'reservation_id' => $reservation->id,
                'entitlement_id' => $entitlement->id,
            ]);

        $res->assertCreated();
        $match = $res->json('match');
        $this->assertFalse((bool) $match['is_owner_hosted']);
        $this->assertSame($this->court->id, $match['court_id']);
        $this->assertSame($reservation->id, $match['reservation_id']);
        $this->assertSame($entitlement->id, $match['entitlement_id']);
        $this->assertSame(1, $match['joined_count']); // Customer host is participant

        // 2. Double allocation: attempting to create a second match on the same reservation/entitlement fails with 409
        $doubleRes = $this->auth($this->hostCustomer)
            ->postJson('/api/social/matches', [
                'title' => 'Buổi giao lưu trùng',
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
                'required_participants' => 4,
                'reservation_id' => $reservation->id,
            ]);
        $doubleRes->assertStatus(409);
    }

    public function test_customer_cannot_use_unowned_reservation_or_exceed_time_window(): void
    {
        $startsAt = now()->addDays(2)->setHour(14)->setMinute(0)->setSecond(0);
        $endsAt = now()->addDays(2)->setHour(16)->setMinute(0)->setSecond(0);

        [$reservation] = $this->createConfirmedReservation($this->hostCustomer, $startsAt, $endsAt);

        // 1. Stranger tries to use hostCustomer's reservation -> 403
        $unauthRes = $this->auth($this->stranger)
            ->postJson('/api/social/matches', [
                'title' => 'Stranger Match',
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
                'required_participants' => 4,
                'reservation_id' => $reservation->id,
            ]);
        $unauthRes->assertStatus(403);

        // 2. Activity time exceeds reservation bounds -> 422
        $exceedRes = $this->auth($this->hostCustomer)
            ->postJson('/api/social/matches', [
                'title' => 'Exceeding Match',
                'starts_at' => $startsAt->copy()->subHour()->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
                'required_participants' => 4,
                'reservation_id' => $reservation->id,
            ]);
        $exceedRes->assertStatus(422);
    }

    // ==========================================
    // Task 9.3 [D60]: Timing, Deadline and Skill
    // ==========================================

    public function test_publish_timing_and_skill_validations(): void
    {
        // 1. starts_at in the past -> 422
        $pastRes = $this->auth($this->hostCustomer)
            ->postJson('/api/social/matches', [
                'title' => 'Past Match',
                'starts_at' => now()->subDay()->toIso8601String(),
                'ends_at' => now()->subDay()->addHours(2)->toIso8601String(),
                'required_participants' => 4,
            ]);
        $pastRes->assertStatus(422);

        // 2. starts_at >= ends_at -> 422
        $invTimeRes = $this->auth($this->hostCustomer)
            ->postJson('/api/social/matches', [
                'title' => 'Inverted Time',
                'starts_at' => now()->addDays(2)->toIso8601String(),
                'ends_at' => now()->addDays(1)->toIso8601String(),
                'required_participants' => 4,
            ]);
        $invTimeRes->assertStatus(422);

        // 3. registration_deadline > starts_at -> 422
        $invDeadlineRes = $this->auth($this->hostCustomer)
            ->postJson('/api/social/matches', [
                'title' => 'Late Deadline',
                'starts_at' => now()->addDays(2)->setHour(14)->toIso8601String(),
                'ends_at' => now()->addDays(2)->setHour(16)->toIso8601String(),
                'registration_deadline' => now()->addDays(2)->setHour(15)->toIso8601String(),
                'required_participants' => 4,
            ]);
        $invDeadlineRes->assertStatus(422);

        // 4. min_skill > max_skill -> 422
        $invSkillRes = $this->auth($this->hostCustomer)
            ->postJson('/api/social/matches', [
                'title' => 'Inverted Skill',
                'starts_at' => now()->addDays(2)->setHour(14)->toIso8601String(),
                'ends_at' => now()->addDays(2)->setHour(16)->toIso8601String(),
                'min_skill' => 5,
                'max_skill' => 2,
                'required_participants' => 4,
            ]);
        $invSkillRes->assertStatus(422);
    }

    public function test_join_rejects_past_deadline_and_skill_mismatch(): void
    {
        $match = MatchPost::create([
            'organization_id' => $this->tenant->id,
            'creator_id' => $this->hostCustomer->id,
            'title' => 'Skill Gated Match',
            'visibility' => 'public',
            'starts_at' => now()->addDays(2)->setHour(14),
            'ends_at' => now()->addDays(2)->setHour(16),
            'registration_deadline' => now()->addDay(),
            'min_skill' => 3,
            'max_skill' => 5,
            'required_participants' => 4,
            'joined_count' => 1,
            'status' => 'open',
        ]);

        // Player A has skill level 2 (below min_skill 3)
        PlayerProfile::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->playerA->id,
            'skill_level' => 2,
        ]);

        $failJoin = $this->auth($this->playerA)
            ->postJson("/api/social/matches/{$match->id}/join");
        $failJoin->assertStatus(422);
        $this->assertStringContainsString('Player skill level does not meet activity requirements.', $failJoin->json('message'));

        // Player B has skill level 4 (within 3..5)
        PlayerProfile::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->playerB->id,
            'skill_level' => 4,
        ]);

        $successJoin = $this->auth($this->playerB)
            ->postJson("/api/social/matches/{$match->id}/join");
        $successJoin->assertStatus(201);
        $this->assertSame(2, $match->fresh()->joined_count);

        // Fast forward past registration deadline
        $match->update(['registration_deadline' => now()->subMinute()]);
        $lateJoin = $this->auth($this->stranger)
            ->postJson("/api/social/matches/{$match->id}/join");
        $lateJoin->assertStatus(422);
        $this->assertStringContainsString('Registration deadline has passed.', $lateJoin->json('message'));
    }

    // ==========================================
    // Task 9.4 [D61]: Canonical Inventory Guard
    // ==========================================

    public function test_booking_and_block_conflict_with_active_match_post(): void
    {
        $startsAt = now()->addDays(3)->setHour(10)->setMinute(0)->setSecond(0)->utc();
        $endsAt = now()->addDays(3)->setHour(12)->setMinute(0)->setSecond(0)->utc();

        // Create an active MatchPost on court
        $match = MatchPost::create([
            'organization_id' => $this->tenant->id,
            'creator_id' => $this->hostCustomer->id,
            'court_id' => $this->court->id,
            'title' => 'Scheduled Activity on Court',
            'visibility' => 'public',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'required_participants' => 4,
            'joined_count' => 1,
            'status' => 'open',
        ]);

        // 1. SlotSelectionPolicy assertAllowed should abort 409
        $policy = app(SlotSelectionPolicy::class);
        try {
            $policy->assertAllowed($this->court, [
                ['start' => $startsAt->copy()->addMinutes(30), 'end' => $endsAt->copy()->subMinutes(30)],
            ]);
            $this->fail('Expected SlotSelectionPolicy to reject overlapping active MatchPost.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertSame('Court interval is reserved for a community/hosted activity.', $e->getMessage());
        }

        // 2. CourtCommitmentConflictService assertBlockPreservesCommitments should abort 409
        $conflicts = app(CourtCommitmentConflictService::class);
        try {
            $conflicts->assertBlockPreservesCommitments($this->court, $startsAt, $endsAt);
            $this->fail('Expected CourtCommitmentConflictService to reject overlapping active MatchPost.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertSame('Court interval is reserved for a community/hosted activity.', $e->getMessage());
        }
        // 3. Cancelled MatchPost does NOT block subsequent booking or court block
        $match->update(['status' => 'cancelled']);
        $policy->assertAllowed($this->court, [
            ['start' => $startsAt->copy()->addMinutes(30), 'end' => $endsAt->copy()->subMinutes(30)],
        ]);
        $conflicts->assertBlockPreservesCommitments($this->court, $startsAt, $endsAt);

        // 4. Completed MatchPost does NOT block subsequent booking or court block
        $match->update(['status' => 'completed']);
        $policy->assertAllowed($this->court, [
            ['start' => $startsAt->copy()->addMinutes(30), 'end' => $endsAt->copy()->subMinutes(30)],
        ]);
        $conflicts->assertBlockPreservesCommitments($this->court, $startsAt, $endsAt);
        $this->assertTrue(true);
    }

    public function test_customer_hosted_activity_linked_to_reservation_does_not_block_rescheduling(): void
    {
        $startsAt = now()->addDays(2)->setHour(14)->setMinute(0)->setSecond(0)->utc();
        $endsAt = now()->addDays(2)->setHour(16)->setMinute(0)->setSecond(0)->utc();

        [$reservation, $entitlement, $slot] = $this->createConfirmedReservation($this->hostCustomer, $startsAt, $endsAt);

        // Host customer creates match post using their confirmed reservation
        $match = MatchPost::create([
            'organization_id' => $this->tenant->id,
            'creator_id' => $this->hostCustomer->id,
            'court_id' => $this->court->id,
            'reservation_id' => $reservation->id,
            'entitlement_id' => $entitlement->id,
            'title' => 'Customer Match Linked to Reservation',
            'visibility' => 'public',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'required_participants' => 4,
            'joined_count' => 1,
            'status' => 'open',
            'is_owner_hosted' => false,
        ]);

        $policy = app(SlotSelectionPolicy::class);

        // 1. Rescheduling/updating the reservation for the same court and interval with excludeReservationId:
        // Must NOT abort with 409 from MatchPost conflict
        $policy->assertAllowed(
            $this->court,
            [
                ['start' => $startsAt, 'end' => $endsAt],
            ],
            excludeReservationId: (int) $reservation->id,
        );

        // 2. Rescheduling to a modified/overlapping slot with excludeReservationId also succeeds without MatchPost conflict
        $policy->assertAllowed(
            $this->court,
            [
                ['start' => $startsAt->copy()->addMinutes(30), 'end' => $endsAt->copy()->subMinutes(30)],
            ],
            excludeReservationId: (int) $reservation->id,
        );

        // 3. A third-party new booking without excluding this reservation is blocked by the reservation's slot
        try {
            $policy->assertAllowed($this->court, [
                ['start' => $startsAt, 'end' => $endsAt],
            ]);
            $this->fail('Expected SlotSelectionPolicy to reject overlapping slot for third party.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertSame('The selected court-time is no longer available.', $e->getMessage());
        }

        // 4. CourtCommitmentConflictService assertNoMatchPostConflict:
        // A customer-hosted match linked to a reservation does NOT trigger MatchPost conflict
        $conflicts = app(CourtCommitmentConflictService::class);
        $conflicts->assertNoMatchPostConflict($this->court->id, $startsAt, $endsAt, excludeReservationId: (int) $reservation->id);
        $conflicts->assertNoMatchPostConflict($this->court->id, $startsAt, $endsAt); // Also succeeds without exclude because is_owner_hosted = false with reservation_id

        // 5. assertBlockPreservesCommitments with excludeReservationId:
        // Excludes the reservation from both MatchPost and commitments checks
        $conflicts->assertBlockPreservesCommitments($this->court, $startsAt, $endsAt, excludeReservationId: (int) $reservation->id);

        // 6. Without excludeReservationId, assertBlockPreservesCommitments rejects with 409 from the underlying reservation commitment
        try {
            $conflicts->assertBlockPreservesCommitments($this->court, $startsAt, $endsAt);
            $this->fail('Expected assertBlockPreservesCommitments to reject due to reservation slot.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertStringContainsString('Khung giờ đóng sân bị trùng', $e->getMessage());
        }
    }

    // ==========================================
    // Task 9.5 [D62]: Approval, Roster & Attendance
    // ==========================================

    public function test_approval_required_workflow_and_capacity(): void
    {
        $match = MatchPost::create([
            'organization_id' => $this->tenant->id,
            'creator_id' => $this->hostCustomer->id,
            'title' => 'Approval Gated Match',
            'visibility' => 'public',
            'starts_at' => now()->addDays(2)->setHour(14),
            'ends_at' => now()->addDays(2)->setHour(16),
            'approval_required' => true,
            'required_participants' => 2,
            'joined_count' => 1, // Host already joined
            'status' => 'open',
        ]);

        // 1. Player A requests to join -> status is 'pending_approval', joined_count does NOT increment
        $joinRes = $this->auth($this->playerA)
            ->postJson("/api/matches/{$match->id}/join");
        $joinRes->assertCreated();
        $this->assertSame(MatchParticipation::STATUS_PENDING_APPROVAL, $joinRes->json('participation.status'));
        $this->assertSame(1, $match->fresh()->joined_count);

        // 2. Unauthorized user cannot approve -> 403
        $unauthApprove = $this->auth($this->stranger)
            ->postJson("/api/matches/{$match->id}/approve-participant", [
                'user_id' => $this->playerA->id,
            ]);
        $unauthApprove->assertStatus(403);

        // 3. Organizer approves Player A -> status becomes 'joined', joined_count increments to 2 (full)
        $approveRes = $this->auth($this->hostCustomer)
            ->postJson("/api/matches/{$match->id}/approve-participant", [
                'user_id' => $this->playerA->id,
            ]);
        $approveRes->assertOk();
        $this->assertSame(MatchParticipation::STATUS_JOINED, $approveRes->json('participation.status'));
        $this->assertSame(2, $match->fresh()->joined_count);
        $this->assertSame('full', $match->fresh()->status);

        // 4. Player B tries to join full match -> 409
        $fullJoin = $this->auth($this->playerB)
            ->postJson("/api/matches/{$match->id}/join");
        $fullJoin->assertStatus(409);
    }

    public function test_rejection_and_roster_close(): void
    {
        $match = MatchPost::create([
            'organization_id' => $this->tenant->id,
            'creator_id' => $this->hostCustomer->id,
            'title' => 'Roster Close Match',
            'visibility' => 'public',
            'starts_at' => now()->addDays(2)->setHour(14),
            'ends_at' => now()->addDays(2)->setHour(16),
            'approval_required' => true,
            'required_participants' => 4,
            'joined_count' => 1,
            'status' => 'open',
        ]);

        $this->auth($this->playerA)
            ->postJson("/api/matches/{$match->id}/join");

        // Organizer rejects Player A
        $rejectRes = $this->auth($this->hostCustomer)
            ->postJson("/api/matches/{$match->id}/reject-participant", [
                'user_id' => $this->playerA->id,
            ]);
        $rejectRes->assertOk();
        $this->assertSame(MatchParticipation::STATUS_REJECTED, $rejectRes->json('participation.status'));

        // Organizer closes roster
        $closeRes = $this->auth($this->hostCustomer)
            ->postJson("/api/matches/{$match->id}/close-roster");
        $closeRes->assertOk();
        $this->assertTrue($match->fresh()->isRosterClosed());

        // Subsequent join rejected with 422
        $subJoin = $this->auth($this->playerB)
            ->postJson("/api/matches/{$match->id}/join");
        $subJoin->assertStatus(422);
        $this->assertStringContainsString('Roster is closed.', $subJoin->json('message'));
    }

    public function test_record_attendance_and_withdrawal_quota_release(): void
    {
        $match = MatchPost::create([
            'organization_id' => $this->tenant->id,
            'creator_id' => $this->hostCustomer->id,
            'title' => 'Attendance Session',
            'visibility' => 'public',
            'starts_at' => now()->addDays(1)->setHour(10),
            'ends_at' => now()->addDays(1)->setHour(12),
            'required_participants' => 2,
            'joined_count' => 2,
            'status' => 'full',
        ]);

        MatchParticipation::create([
            'organization_id' => $this->tenant->id,
            'match_post_id' => $match->id,
            'user_id' => $this->playerA->id,
            'status' => MatchParticipation::STATUS_JOINED,
            'joined_at' => now(),
        ]);

        // 1. Staff/Operator records Player A as present
        $attRes = $this->auth($this->manager)
            ->postJson("/api/matches/{$match->id}/record-attendance", [
                'user_id' => $this->playerA->id,
                'status' => 'present',
                'notes' => 'Checked in on time',
            ]);
        $attRes->assertOk();
        $this->assertSame('present', $attRes->json('participation.attendance_status'));
        $this->assertNotNull($attRes->json('participation.verified_at'));

        // 2. Staff records Player A as withdrawn -> releases quota, decrements joined_count, match reopens
        $withRes = $this->auth($this->manager)
            ->postJson("/api/matches/{$match->id}/record-attendance", [
                'user_id' => $this->playerA->id,
                'status' => 'withdrawn',
                'notes' => 'Injured during warm up',
            ]);
        $withRes->assertOk();
        $this->assertSame(MatchParticipation::STATUS_WITHDRAWN, $withRes->json('participation.status'));
        $this->assertSame('withdrawn', $withRes->json('participation.attendance_status'));

        $freshMatch = $match->fresh();
        $this->assertSame(1, $freshMatch->joined_count);
        $this->assertSame('open', $freshMatch->status);
    }

    // ==========================================
    // Task 9.6 [D68]: Reputation Source & Disputes
    // ==========================================

    public function test_reputation_source_model_verification_and_unauthorized_rejection(): void
    {
        // 1. Non-existent source model -> 422
        $fakeSourceRes = $this->auth($this->manager)
            ->postJson('/api/social/reputation/activity', [
                'user_id' => $this->playerA->id,
                'type' => 'attendance',
                'source_type' => 'reservation',
                'source_id' => 999999,
            ]);
        $fakeSourceRes->assertStatus(422);
        $this->assertStringContainsString('Source event does not exist.', $fakeSourceRes->json('message'));

        // 2. Real reservation but belonging to another organization -> 422
        $otherVenue = Venue::create([
            'organization_id' => $this->otherTenant->id,
            'name' => 'Other Stadium',
            'slug' => 'other-stadium-'.uniqid(),
            'status' => 'active',
        ]);
        $otherCourt = Court::create([
            'venue_id' => $otherVenue->id,
            'name' => 'Court Other',
            'is_active' => true,
        ]);
        $otherRes = Reservation::create([
            'organization_id' => $this->otherTenant->id,
            'user_id' => $this->playerA->id,
            'court_id' => $otherCourt->id,
            'code' => 'RES-OTHER-'.uniqid(),
            'status' => 'confirmed',
            'total_amount' => 100000,
        ]);

        $wrongOrgRes = $this->auth($this->manager)
            ->postJson('/api/social/reputation/activity', [
                'user_id' => $this->playerA->id,
                'type' => 'attendance',
                'source_type' => 'reservation',
                'source_id' => $otherRes->id,
            ]);
        $wrongOrgRes->assertStatus(422);

        // 3. User is not a participant in source event -> 422
        $ourRes = Reservation::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->playerA->id,
            'court_id' => $this->court->id,
            'code' => 'RES-OUR-'.uniqid(),
            'status' => 'confirmed',
            'total_amount' => 100000,
        ]);

        $nonPartRes = $this->auth($this->manager)
            ->postJson('/api/social/reputation/activity', [
                'user_id' => $this->playerB->id, // Player B is not the reservation user
                'type' => 'attendance',
                'source_type' => 'reservation',
                'source_id' => $ourRes->id,
            ]);
        $nonPartRes->assertStatus(422);
    }

    public function test_upheld_dispute_reverses_penalty_without_penalizing_appellant(): void
    {
        $res = Reservation::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->playerA->id,
            'court_id' => $this->court->id,
            'code' => 'RES-DISP-'.uniqid(),
            'status' => 'confirmed',
            'total_amount' => 100000,
        ]);

        // 1. Staff records no_show (-15 reputation)
        $noShowRes = $this->auth($this->manager)
            ->postJson('/api/social/reputation/activity', [
                'user_id' => $this->playerA->id,
                'type' => 'no_show',
                'source_type' => 'reservation',
                'source_id' => $res->id,
            ]);
        $noShowRes->assertOk();
        // Baseline 60 - 15 = 45
        $this->assertSame(45, $noShowRes->json('profile.reputation_score'));

        $activity = QualifyingActivity::where('user_id', $this->playerA->id)->firstOrFail();

        // 2. Player A files dispute
        $dispRes = $this->auth($this->playerA)
            ->postJson("/api/social/reputation/activities/{$activity->id}/dispute", [
                'reason' => 'Khách đến đúng giờ, check-in bị lỗi',
            ]);
        $dispRes->assertCreated();
        $disputeId = $dispRes->json('dispute.id');

        // 3. Manager upholds the dispute
        $resolveRes = $this->auth($this->manager)
            ->postJson("/api/social/reputation/disputes/{$disputeId}/resolve", [
                'decision' => 'upheld',
                'resolution' => 'Camera xác nhận khách có mặt đúng giờ',
            ]);
        $resolveRes->assertOk();
        $this->assertSame('upheld', $resolveRes->json('dispute.decision'));

        // 4. Verify appellant is NOT penalized (-10 penalty removed!), and no-show penalty is reversed
        $profile = PlayerProfile::where('organization_id', $this->tenant->id)->where('user_id', $this->playerA->id)->firstOrFail();
        // Score returns to baseline 60 (penalty was reversed to 0, and no -10 was added)
        $this->assertSame(60, $profile->reputation_score);

        // Confirm no 'upheld_dispute' penalty activity exists for player A
        $this->assertDatabaseMissing('qualifying_activities', [
            'user_id' => $this->playerA->id,
            'type' => 'upheld_dispute',
        ]);
    }

    // ========================================================
    // Aliased regression tests explicitly referenced in D59-D68
    // ========================================================

    public function test_customer_host_can_use_valid_booking_entitlement_without_double_allocation(): void
    {
        $this->test_customer_with_confirmed_reservation_can_create_match_post_and_prevents_double_allocation();
    }

    public function test_ordinary_activity_validates_timing_deadline_and_skill_levels(): void
    {
        $this->test_publish_timing_and_skill_validations();
        $this->test_join_rejects_past_deadline_and_skill_mismatch();
    }

    public function test_hosted_session_participates_in_canonical_inventory_bidirectionally(): void
    {
        $this->test_booking_and_block_conflict_with_active_match_post();
    }

    public function test_activity_roster_approval_close_and_attendance_lifecycle(): void
    {
        $this->test_approval_required_workflow_and_capacity();
        $this->test_rejection_and_roster_close();
        $this->test_record_attendance_and_withdrawal_quota_release();
    }

    public function test_reputation_source_verification_and_appeal_penalty_reversal(): void
    {
        $this->test_reputation_source_model_verification_and_unauthorized_rejection();
        $this->test_upheld_dispute_reverses_penalty_without_penalizing_appellant();
    }
}
