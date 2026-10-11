<?php

namespace Tests\Feature\Social;

use App\Models\Court;
use App\Models\CourtBlock;
use App\Models\MatchPost;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\Reservation\CourtCommitmentConflictService;
use App\Services\Social\MatchService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MatchPostTimezoneBoundaryConflictTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Venue $venue;

    private Court $court;

    private User $owner;

    private User $customer;

    private MatchService $matchService;

    private CourtCommitmentConflictService $conflictService;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'Asia/Ho_Chi_Minh',
            'features.flags.social_matchmaking_tournaments' => true,
        ]);

        $this->org = Organization::create([
            'name' => 'Badminton Hub',
            'slug' => 'badminton-hub-'.uniqid(),
            'status' => 'active',
        ]);

        config(['features.pilot_organizations' => [(string) $this->org->id]]);

        $this->venue = Venue::create([
            'organization_id' => $this->org->id,
            'name' => 'Badminton Center Q7',
            'slug' => 'badminton-center-q7-'.uniqid(),
            'address' => 'District 7, TP.HCM',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'is_active' => true,
            'status' => 'active',
        ]);

        $this->court = Court::create([
            'venue_id' => $this->venue->id,
            'name' => 'Court 1',
            'slug' => 'court-1-'.uniqid(),
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create([
            'name' => 'Facility Owner',
            'email' => 'owner_'.uniqid().'@example.com',
        ]);

        $this->customer = User::factory()->create([
            'name' => 'Player One',
            'email' => 'player_'.uniqid().'@example.com',
        ]);

        OrganizationMember::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->customer->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);

        $this->matchService = app(MatchService::class);
        $this->conflictService = app(CourtCommitmentConflictService::class);
    }

    /**
     * Test raw database string in PostgreSQL is stored in UTC (07:00:00 for 14:00 local)
     * and hydrated model uses UtcDateTime cast to output ISO-8601 UTC.
     */
    public function test_match_post_persists_in_utc_and_serializes_iso8601(): void
    {
        $localStartStr = '2026-10-15 14:00:00'; // 14:00 Asia/Ho_Chi_Minh (UTC+7) -> 07:00 UTC
        $localEndStr = '2026-10-15 16:00:00';   // 16:00 Asia/Ho_Chi_Minh (UTC+7) -> 09:00 UTC

        $post = $this->matchService->createPost($this->owner, $this->org->id, [
            'court_id' => $this->court->id,
            'title' => 'Afternoon Match',
            'is_owner_hosted' => true,
            'category' => 'giao lưu',
            'skill_levels' => ['TB'],
            'starts_at' => $localStartStr,
            'ends_at' => $localEndStr,
            'required_participants' => 4,
            'visibility' => 'public',
        ]);

        // Raw database string in PostgreSQL must be in UTC
        $raw = DB::selectOne('SELECT starts_at, ends_at FROM match_posts WHERE id = ?', [$post->id]);
        $this->assertNotNull($raw);
        $this->assertSame('2026-10-15 07:00:00', $raw->starts_at, 'Raw DB starts_at must be in UTC (07:00:00), not local (14:00:00).');
        $this->assertSame('2026-10-15 09:00:00', $raw->ends_at, 'Raw DB ends_at must be in UTC (09:00:00), not local (16:00:00).');

        // Hydrated Eloquent model returns UTC Carbon
        $fresh = MatchPost::findOrFail($post->id);
        $this->assertSame('UTC', $fresh->starts_at->timezoneName);
        $this->assertSame('2026-10-15 07:00:00', $fresh->starts_at->format('Y-m-d H:i:s'));

        // JSON serialization via API / toArray produces ISO-8601 UTC
        $array = $fresh->toArray();
        $this->assertStringContainsString('2026-10-15T07:00:00', $array['starts_at']);
    }

    /**
     * Test CourtCommitmentConflictService probes:
     * - MatchPost at 14:00 local (07:00 UTC).
     * - Probe conflict at 07:00 UTC (14:00 local) -> MUST conflict (HTTP 409).
     * - Probe conflict at 14:00 UTC (21:00 local) -> MUST NOT conflict (proves 7-hour offset is not falsely matching).
     */
    public function test_conflict_service_detects_overlap_at_matching_utc_and_clears_seven_hour_offset(): void
    {
        // Scheduled at 14:00 local (07:00 UTC) to 16:00 local (09:00 UTC)
        $match = MatchPost::create([
            'organization_id' => $this->org->id,
            'creator_id' => $this->owner->id,
            'court_id' => $this->court->id,
            'title' => 'Badminton Social Session',
            'starts_at' => Carbon::parse('2026-10-15 14:00:00', 'Asia/Ho_Chi_Minh'),
            'ends_at' => Carbon::parse('2026-10-15 16:00:00', 'Asia/Ho_Chi_Minh'),
            'required_participants' => 4,
            'joined_count' => 1,
            'status' => 'open',
            'is_owner_hosted' => true,
        ]);

        // Probe 1: Same time: 14:00 local / 07:00 UTC -> MUST conflict
        $probe1Conflict = false;
        try {
            $this->conflictService->assertNoMatchPostConflict(
                $this->court->id,
                Carbon::parse('2026-10-15 14:00:00', 'Asia/Ho_Chi_Minh'),
                Carbon::parse('2026-10-15 16:00:00', 'Asia/Ho_Chi_Minh')
            );
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $probe1Conflict = true;
        }
        $this->assertTrue($probe1Conflict, 'Conflict MUST be detected at 14:00 local (07:00 UTC).');

        // Also test using UTC Carbon directly
        $probe1UtcConflict = false;
        try {
            $this->conflictService->assertNoMatchPostConflict(
                $this->court->id,
                Carbon::parse('2026-10-15 07:00:00', 'UTC'),
                Carbon::parse('2026-10-15 09:00:00', 'UTC')
            );
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $probe1UtcConflict = true;
        }
        $this->assertTrue($probe1UtcConflict, 'Conflict MUST be detected at 07:00 UTC.');

        // Probe 2: 7 hours later: 21:00 local / 14:00 UTC -> MUST NOT conflict
        // If local time were stored as 14:00, UTC 14:00 would falsely match it!
        $probe2Conflict = false;
        try {
            $this->conflictService->assertNoMatchPostConflict(
                $this->court->id,
                Carbon::parse('2026-10-15 21:00:00', 'Asia/Ho_Chi_Minh'),
                Carbon::parse('2026-10-15 23:00:00', 'Asia/Ho_Chi_Minh')
            );
        } catch (HttpException $e) {
            $probe2Conflict = true;
        }
        $this->assertFalse($probe2Conflict, '7-hour offset (21:00 local / 14:00 UTC) MUST NOT falsely conflict.');

        // Probe 2 with UTC directly: 14:00 UTC -> MUST NOT conflict
        $probe2UtcConflict = false;
        try {
            $this->conflictService->assertNoMatchPostConflict(
                $this->court->id,
                Carbon::parse('2026-10-15 14:00:00', 'UTC'),
                Carbon::parse('2026-10-15 16:00:00', 'UTC')
            );
        } catch (HttpException $e) {
            $probe2UtcConflict = true;
        }
        $this->assertFalse($probe2UtcConflict, '14:00 UTC probe MUST NOT falsely conflict with 07:00 UTC match.');

        // Probe CourtBlock preservation check
        $blockConflict = false;
        try {
            $this->conflictService->assertBlockPreservesCommitments(
                $this->court,
                Carbon::parse('2026-10-15 14:00:00', 'Asia/Ho_Chi_Minh'),
                Carbon::parse('2026-10-15 16:00:00', 'Asia/Ho_Chi_Minh')
            );
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $blockConflict = true;
        }
        $this->assertTrue($blockConflict, 'CourtBlock at 14:00 local MUST conflict with match post.');

        // CourtBlock at 21:00 local (14:00 UTC) MUST NOT conflict
        $blockLateConflict = false;
        try {
            $this->conflictService->assertBlockPreservesCommitments(
                $this->court,
                Carbon::parse('2026-10-15 21:00:00', 'Asia/Ho_Chi_Minh'),
                Carbon::parse('2026-10-15 23:00:00', 'Asia/Ho_Chi_Minh')
            );
        } catch (HttpException $e) {
            $blockLateConflict = true;
        }
        $this->assertFalse($blockLateConflict, 'CourtBlock at 21:00 local (14:00 UTC) MUST NOT conflict.');
    }

    /**
     * Test MatchService::checkCourtAvailability with CourtBlock:
     * - CourtBlock at 07:00 UTC (14:00 local) -> Match at 14:00 local MUST conflict.
     * - CourtBlock at 14:00 UTC (21:00 local) -> Match at 14:00 local MUST NOT conflict.
     */
    public function test_match_service_court_availability_boundary_with_court_block(): void
    {
        // Create CourtBlock at 07:00 UTC (14:00 local)
        $block = CourtBlock::create([
            'court_id' => $this->court->id,
            'reason' => 'Maintenance',
            'starts_at' => Carbon::parse('2026-10-15 14:00:00', 'Asia/Ho_Chi_Minh'),
            'ends_at' => Carbon::parse('2026-10-15 16:00:00', 'Asia/Ho_Chi_Minh'),
        ]);

        // Attempting to schedule match at 14:00 local (07:00 UTC) -> MUST conflict
        $conflictDetected = false;
        try {
            $this->matchService->checkCourtAvailability(
                $this->court,
                Carbon::parse('2026-10-15 14:00:00', 'Asia/Ho_Chi_Minh'),
                Carbon::parse('2026-10-15 16:00:00', 'Asia/Ho_Chi_Minh')
            );
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $conflictDetected = true;
        }
        $this->assertTrue($conflictDetected, 'Match at 14:00 local MUST conflict with CourtBlock at 14:00 local.');

        // Attempting to schedule match at 21:00 local (14:00 UTC) -> MUST NOT conflict
        $lateConflict = false;
        try {
            $this->matchService->checkCourtAvailability(
                $this->court,
                Carbon::parse('2026-10-15 21:00:00', 'Asia/Ho_Chi_Minh'),
                Carbon::parse('2026-10-15 23:00:00', 'Asia/Ho_Chi_Minh')
            );
        } catch (HttpException $e) {
            $lateConflict = true;
        }
        $this->assertFalse($lateConflict, 'Match at 21:00 local MUST NOT conflict with CourtBlock at 14:00 local.');

        $block->delete();

        // Create CourtBlock at 14:00 UTC (21:00 local)
        $lateBlock = CourtBlock::create([
            'court_id' => $this->court->id,
            'reason' => 'Late Evening Maintenance',
            'starts_at' => Carbon::parse('2026-10-15 21:00:00', 'Asia/Ho_Chi_Minh'),
            'ends_at' => Carbon::parse('2026-10-15 23:00:00', 'Asia/Ho_Chi_Minh'),
        ]);

        // Scheduling match at 14:00 local (07:00 UTC) -> MUST NOT conflict with late block
        $earlyConflict = false;
        try {
            $this->matchService->checkCourtAvailability(
                $this->court,
                Carbon::parse('2026-10-15 14:00:00', 'Asia/Ho_Chi_Minh'),
                Carbon::parse('2026-10-15 16:00:00', 'Asia/Ho_Chi_Minh')
            );
        } catch (HttpException $e) {
            $earlyConflict = true;
        }
        $this->assertFalse($earlyConflict, 'Match at 14:00 local MUST NOT conflict with CourtBlock at 21:00 local.');

        $lateBlock->delete();
    }

    /**
     * Test MatchService::checkCourtAvailability with ReservationSlot:
     * - ReservationSlot at 07:00 UTC (14:00 local) -> Match at 14:00 local MUST conflict.
     * - ReservationSlot at 14:00 UTC (21:00 local) -> Match at 14:00 local MUST NOT conflict.
     */
    public function test_match_service_court_availability_boundary_with_reservation_slot(): void
    {
        $reservation = Reservation::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'code' => 'RSV-'.uniqid(),
            'status' => 'confirmed',
            'total_amount' => 200000,
            'channel' => 'v2_online',
        ]);

        $slot = ReservationSlot::create([
            'organization_id' => $this->org->id,
            'reservation_id' => $reservation->id,
            'court_id' => $this->court->id,
            'starts_at' => Carbon::parse('2026-10-15 14:00:00', 'Asia/Ho_Chi_Minh'),
            'ends_at' => Carbon::parse('2026-10-15 16:00:00', 'Asia/Ho_Chi_Minh'),
            'unit_price' => 200000,
            'status' => 'confirmed',
        ]);

        // Raw DB string for reservation slot is in UTC
        $rawSlot = DB::selectOne('SELECT starts_at, ends_at FROM reservation_slots WHERE id = ?', [$slot->id]);
        $this->assertSame('2026-10-15 07:00:00', $rawSlot->starts_at);

        // Match at 14:00 local -> MUST conflict with ReservationSlot
        $conflict = false;
        try {
            $this->matchService->checkCourtAvailability(
                $this->court,
                Carbon::parse('2026-10-15 14:00:00', 'Asia/Ho_Chi_Minh'),
                Carbon::parse('2026-10-15 16:00:00', 'Asia/Ho_Chi_Minh')
            );
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $conflict = true;
        }
        $this->assertTrue($conflict, 'Match at 14:00 local MUST conflict with ReservationSlot at 14:00 local.');

        // Match at 21:00 local (14:00 UTC) -> MUST NOT conflict with 14:00 local reservation
        $noConflict = true;
        try {
            $this->matchService->checkCourtAvailability(
                $this->court,
                Carbon::parse('2026-10-15 21:00:00', 'Asia/Ho_Chi_Minh'),
                Carbon::parse('2026-10-15 23:00:00', 'Asia/Ho_Chi_Minh')
            );
        } catch (HttpException $e) {
            $noConflict = false;
        }
        $this->assertTrue($noConflict, 'Match at 21:00 local MUST NOT conflict with ReservationSlot at 14:00 local.');
    }

    public function test_feed_query_boundary_and_date_filtering(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 14:00:00', 'Asia/Ho_Chi_Minh')); // 07:00:00 UTC

        try {
            // Create Match 1 (active now, ending shortly after now):
            // Starts at 13:00 local (06:00 UTC), ends at 15:00 local (08:00 UTC).
            // ends_at (08:00 UTC) is after now()->utc() (07:00 UTC).
            $match1 = MatchPost::create([
                'organization_id' => $this->org->id,
                'creator_id' => $this->owner->id,
                'court_id' => $this->court->id,
                'title' => 'Match 1 Active Now',
                'starts_at' => Carbon::parse('2026-10-15 13:00:00', 'Asia/Ho_Chi_Minh'),
                'ends_at' => Carbon::parse('2026-10-15 15:00:00', 'Asia/Ho_Chi_Minh'),
                'required_participants' => 4,
                'joined_count' => 1,
                'status' => 'open',
                'visibility' => 'public',
                'is_owner_hosted' => true,
            ]);

            // Call discover(). Assert Match 1 is included in the feed!
            $feed = $this->matchService->discover();
            $this->assertTrue($feed->pluck('id')->contains($match1->id), 'Match 1 must be included in the feed.');

            // Create Match 2 (ended yesterday):
            // Starts 2026-10-14 10:00 local, ends 2026-10-14 12:00 local (2026-10-14 05:00 UTC).
            $match2 = MatchPost::create([
                'organization_id' => $this->org->id,
                'creator_id' => $this->owner->id,
                'court_id' => $this->court->id,
                'title' => 'Match 2 Ended Yesterday',
                'starts_at' => Carbon::parse('2026-10-14 10:00:00', 'Asia/Ho_Chi_Minh'),
                'ends_at' => Carbon::parse('2026-10-14 12:00:00', 'Asia/Ho_Chi_Minh'),
                'required_participants' => 4,
                'joined_count' => 1,
                'status' => 'open',
                'visibility' => 'public',
                'is_owner_hosted' => true,
            ]);

            // Call discover(). Assert Match 2 is NOT included in the feed!
            $feedAfterM2 = $this->matchService->discover();
            $this->assertFalse($feedAfterM2->pluck('id')->contains($match2->id), 'Match 2 must NOT be included in the feed.');

            // Create Match 3 (early morning on 2026-10-15 local, crossing the UTC date boundary):
            // Starts 2026-10-15 06:00 local (2026-10-14 23:00:00 UTC), ends 2026-10-15 08:00 local (2026-10-15 01:00:00 UTC).
            $match3 = MatchPost::create([
                'organization_id' => $this->org->id,
                'creator_id' => $this->owner->id,
                'court_id' => $this->court->id,
                'title' => 'Match 3 Early Morning',
                'starts_at' => Carbon::parse('2026-10-15 06:00:00', 'Asia/Ho_Chi_Minh'),
                'ends_at' => Carbon::parse('2026-10-15 08:00:00', 'Asia/Ho_Chi_Minh'),
                'required_participants' => 4,
                'joined_count' => 1,
                'status' => 'open',
                'visibility' => 'public',
                'is_owner_hosted' => true,
            ]);

            // Check raw DB for Match 3 to prove PostgreSQL stores the UTC crossing:
            $rawMatch3 = DB::selectOne('SELECT starts_at, ends_at FROM match_posts WHERE id = ?', [$match3->id]);
            $this->assertSame('2026-10-14 23:00:00', $rawMatch3->starts_at);
            $this->assertSame('2026-10-15 01:00:00', $rawMatch3->ends_at);

            // Assert Match 3 is also present in the default feed without date filter:
            // Because it started 8 hours ago relative to frozen now (07:00 UTC), it is inside the 12-hour window (starts_at >= now()->utc()->subHours(12)):
            $defaultFeed = $this->matchService->discover();
            $this->assertTrue($defaultFeed->pluck('id')->contains($match3->id), 'Match 3 must be in default feed via 12-hour sub-window.');

            // Add Match 4:
            // Starts at 2026-10-14 17:00:00 local (10:00:00 UTC), ends at 2026-10-14 19:00:00 local (12:00:00 UTC).
            // Relative to frozen now (07:00 UTC on Oct 15):
            // - Started 21 hours ago (outside 12-hour window).
            // - Ended 19 hours ago (in the past).
            // - Date is Oct 14 in both local and UTC.
            $match4 = MatchPost::create([
                'organization_id' => $this->org->id,
                'creator_id' => $this->owner->id,
                'court_id' => $this->court->id,
                'title' => 'Match 4 Past Outside Window',
                'starts_at' => Carbon::parse('2026-10-14 17:00:00', 'Asia/Ho_Chi_Minh'),
                'ends_at' => Carbon::parse('2026-10-14 19:00:00', 'Asia/Ho_Chi_Minh'),
                'required_participants' => 4,
                'joined_count' => 1,
                'status' => 'open',
                'visibility' => 'public',
                'is_owner_hosted' => true,
            ]);

            // Assert Match 4 is NOT included in default feed (discover()):
            $defaultFeedAfterM4 = $this->matchService->discover();
            $this->assertFalse($defaultFeedAfterM4->pluck('id')->contains($match4->id), 'Match 4 must NOT be included in default feed.');

            // Call discover($this->org->id, ['date' => '2026-10-15']).
            $filteredFeed = $this->matchService->discover($this->org->id, ['date' => '2026-10-15']);

            // Assert Match 3 is returned in the date-filtered feed!
            $this->assertTrue($filteredFeed->pluck('id')->contains($match3->id), 'Match 3 must be returned in the date-filtered feed.');

            // Assert Match 2 is NOT returned!
            $this->assertFalse($filteredFeed->pluck('id')->contains($match2->id), 'Match 2 must NOT be returned in the date-filtered feed.');

            // Assert Match 4 is NOT included in date-filtered feed!
            $this->assertFalse($filteredFeed->pluck('id')->contains($match4->id), 'Match 4 must NOT be included in date-filtered feed.');
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_update_post_normalizes_timestamps_to_utc(): void
    {
        // 1. Owner-hosted update: registration_deadline normalization
        $ownerPost = MatchPost::create([
            'organization_id' => $this->org->id,
            'creator_id' => $this->owner->id,
            'court_id' => $this->court->id,
            'title' => 'Owner Match',
            'starts_at' => Carbon::parse('2026-10-20 14:00:00', 'Asia/Ho_Chi_Minh'),
            'ends_at' => Carbon::parse('2026-10-20 16:00:00', 'Asia/Ho_Chi_Minh'),
            'required_participants' => 4,
            'joined_count' => 1,
            'status' => 'open',
            'visibility' => 'public',
            'is_owner_hosted' => true,
        ]);

        $this->matchService->updatePost($ownerPost, $this->owner, [
            'registration_deadline' => '2026-10-20 12:00:00', // Asia/Ho_Chi_Minh -> 05:00:00 UTC
        ]);

        $rawOwner = DB::selectOne('SELECT registration_deadline FROM match_posts WHERE id = ?', [$ownerPost->id]);
        $this->assertSame('2026-10-20 05:00:00', $rawOwner->registration_deadline);

        // Clear registration_deadline
        $this->matchService->updatePost($ownerPost, $this->owner, [
            'registration_deadline' => '',
        ]);
        $rawOwnerCleared = DB::selectOne('SELECT registration_deadline FROM match_posts WHERE id = ?', [$ownerPost->id]);
        $this->assertNull($rawOwnerCleared->registration_deadline);

        // 2. Non-owner-hosted update: starts_at, ends_at, registration_deadline normalization
        $customerPost = MatchPost::create([
            'organization_id' => $this->org->id,
            'creator_id' => $this->customer->id,
            'title' => 'Customer Match',
            'starts_at' => Carbon::parse('2026-10-20 14:00:00', 'Asia/Ho_Chi_Minh'),
            'ends_at' => Carbon::parse('2026-10-20 16:00:00', 'Asia/Ho_Chi_Minh'),
            'required_participants' => 4,
            'joined_count' => 1,
            'status' => 'open',
            'visibility' => 'public',
            'is_owner_hosted' => false,
        ]);

        $this->matchService->updatePost($customerPost, $this->customer, [
            'starts_at' => '2026-10-21 08:00:00', // Asia/Ho_Chi_Minh -> 01:00:00 UTC
            'ends_at' => '2026-10-21 10:00:00',   // Asia/Ho_Chi_Minh -> 03:00:00 UTC
            'registration_deadline' => '2026-10-21 07:00:00', // Asia/Ho_Chi_Minh -> 00:00:00 UTC
        ]);

        $rawCustomer = DB::selectOne('SELECT starts_at, ends_at, registration_deadline FROM match_posts WHERE id = ?', [$customerPost->id]);
        $this->assertSame('2026-10-21 01:00:00', $rawCustomer->starts_at);
        $this->assertSame('2026-10-21 03:00:00', $rawCustomer->ends_at);
        $this->assertSame('2026-10-21 00:00:00', $rawCustomer->registration_deadline);

        // Clear registration_deadline on non-owner post
        $this->matchService->updatePost($customerPost, $this->customer, [
            'registration_deadline' => null,
        ]);
        $rawCustomerCleared = DB::selectOne('SELECT registration_deadline FROM match_posts WHERE id = ?', [$customerPost->id]);
        $this->assertNull($rawCustomerCleared->registration_deadline);
    }
}
