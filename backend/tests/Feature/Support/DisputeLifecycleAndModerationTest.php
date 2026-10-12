<?php

namespace Tests\Feature\Support;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\DisputeCase;
use App\Models\FeedPost;
use App\Models\FeedReport;
use App\Models\ModerationRecord;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OutboxMessage;
use App\Models\OwnerRefundObligation;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisputeLifecycleAndModerationTest extends TestCase
{
    use RefreshDatabase;

    private User $platformAdmin;

    private Organization $tenantA;

    private User $ownerA;

    private Venue $venueA;

    private Court $courtA;

    private Organization $tenantB;

    private User $ownerB;

    private Venue $venueB;

    private Court $courtB;

    private User $customerAlice;

    private User $customerBob;

    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platformAdmin = User::factory()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@platform.local',
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        [$this->tenantA, $this->ownerA, $this->venueA, $this->courtA] = $this->createTenant('Tenant Alpha', 'tenant-alpha');
        [$this->tenantB, $this->ownerB, $this->venueB, $this->courtB] = $this->createTenant('Tenant Beta', 'tenant-beta');

        config([
            'features.flags.social_matchmaking_tournaments' => true,
            'features.pilot_organizations' => array_values(array_unique(array_merge(
                (array) config('features.pilot_organizations', []),
                [(string) $this->tenantA->id, (string) $this->tenantB->id]
            ))),
        ]);
        $this->customerAlice = $this->createCustomerUser('alice@test.local', 'Alice Customer');
        $this->customerBob = $this->createCustomerUser('bob@test.local', 'Bob Customer');
        $this->stranger = $this->createCustomerUser('stranger@test.local', 'Stranger User');
    }

    private function createCustomerUser(string $email, string $name = 'User'): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'role' => 'customer',
            'status' => 'active',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
    }

    private function createTenant(string $name, string $slug): array
    {
        $org = Organization::create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        $owner = $this->createCustomerUser("owner_{$slug}@test.local", "Owner {$name}");

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => "Venue {$name}",
            'slug' => "venue-{$slug}",
            'is_active' => true,
        ]);

        $court = Court::create([
            'venue_id' => $venue->id,
            'name' => "Court {$name} 1",
            'is_active' => true,
        ]);

        return [$org, $owner, $venue, $court];
    }

    private function createBooking(User $user, Court $court, ?Carbon $endedAt = null): Booking
    {
        $end = $endedAt ?? now()->subDays(2)->setTime(16, 0);
        $start = (clone $end)->subHour();

        $booking = Booking::create([
            'code' => 'BK-'.strtoupper(uniqid()),
            'user_id' => $user->id,
            'court_id' => $court->id,
            'total_amount' => 300000,
            'status' => 'confirmed',
        ]);

        BookingSlot::create([
            'booking_id' => $booking->id,
            'court_id' => $court->id,
            'starts_at' => $start,
            'ends_at' => $end,
            'unit_price' => 300000,
            'status' => 'confirmed',
        ]);

        return $booking;
    }

    private function createDisputeCase(
        Organization $org,
        Venue $venue,
        User $complainant,
        User $respondent,
        string $status = 'pending',
        string $code = 'DSP-TEST-1',
        ?Booking $booking = null
    ): DisputeCase {
        return DisputeCase::create([
            'dispute_code' => $code,
            'organization_id' => $org->id,
            'venue_id' => $venue->id,
            'booking_id' => $booking?->id,
            'reference_type' => $booking ? 'booking' : null,
            'reference_id' => $booking?->id,
            'court_name' => 'Sân 1',
            'facility_name' => $venue->name,
            'facility_operator_name' => $org->name,
            'title' => "Tranh chấp cơ sở {$venue->name}",
            'summary' => 'Tóm tắt tranh chấp kiểm thử chi tiết và hợp lệ.',
            'complainant_id' => $complainant->id,
            'complainant_name' => $complainant->name,
            'complainant_reputation_score' => 100,
            'respondent_id' => $respondent->id,
            'respondent_name' => $respondent->name,
            'respondent_reputation_score' => 100,
            'status' => $status,
            'severity' => 'medium',
            'dispute_type' => 'court_quality',
            'decision_version' => 1,
            'decision_history' => [],
            'evidence' => [],
            'timeline_events' => [
                [
                    'event' => 'created',
                    'timestamp' => now()->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Test 1: Reasoned moderation dismiss (keep), hide, and restore with Outbox notification.
     */
    public function test_reasoned_moderation_dismiss_hide_and_restore_with_outbox_notifications(): void
    {
        $post = FeedPost::create([
            'organization_id' => $this->tenantA->id,
            'user_id' => $this->customerAlice->id,
            'content' => 'Bài viết thảo luận kỹ thuật chơi bóng.',
            'audience' => 'organization',
        ]);

        $report = FeedReport::create([
            'organization_id' => $this->tenantA->id,
            'reporter_id' => $this->customerBob->id,
            'reportable_type' => FeedPost::class,
            'reportable_id' => $post->id,
            'reason' => 'Nghi ngờ nội dung vi phạm tiêu chuẩn cộng đồng',
            'status' => FeedReport::STATUS_PENDING,
        ]);

        // 1. Moderator dismisses report with reasoned judgment (keep)
        $dismissRes = $this->actingAs($this->ownerA)
            ->withHeaders(['X-Organization-Id' => (string) $this->tenantA->id])
            ->postJson("/api/v2/social/feed/reports/{$report->id}/dismiss", [
                'reason' => 'Nội dung chia sẻ kỹ thuật hoàn toàn hợp lệ, không vi phạm.',
            ]);

        $dismissRes->assertOk();
        $this->assertSame(FeedReport::STATUS_DISMISSED, $report->fresh()->status);
        $this->assertSame('dismiss', $report->fresh()->action_taken);
        $this->assertEquals($this->ownerA->id, $report->fresh()->reviewed_by);

        // Verify ModerationRecord action is 'keep'
        $this->assertDatabaseHas('moderation_records', [
            'organization_id' => $this->tenantA->id,
            'actor_id' => $this->ownerA->id,
            'target_type' => FeedPost::class,
            'target_id' => $post->id,
            'action' => 'keep',
        ]);

        // Verify OutboxMessage to reporter
        $this->assertDatabaseHas('outbox_messages', [
            'organization_id' => $this->tenantA->id,
            'event_type' => 'feed.report_dismissed',
            'aggregate_id' => $report->id,
        ]);

        // 2. Hide post
        $hideRes = $this->actingAs($this->ownerA)
            ->withHeaders(['X-Organization-Id' => (string) $this->tenantA->id])
            ->postJson("/api/v2/social/feed/{$post->id}/hide", [
                'reason' => 'Cần ẩn tạm thời để bổ sung xác minh thêm.',
            ]);
        $hideRes->assertOk();
        $this->assertTrue($post->fresh()->is_hidden);

        $this->assertDatabaseHas('moderation_records', [
            'organization_id' => $this->tenantA->id,
            'target_type' => FeedPost::class,
            'target_id' => $post->id,
            'action' => 'content_hidden',
        ]);

        $this->assertDatabaseHas('outbox_messages', [
            'organization_id' => $this->tenantA->id,
            'event_type' => 'feed.post_hidden',
            'aggregate_id' => $post->id,
        ]);

        // 3. Restore post
        $restoreRes = $this->actingAs($this->ownerA)
            ->withHeaders(['X-Organization-Id' => (string) $this->tenantA->id])
            ->postJson("/api/v2/social/feed/{$post->id}/restore", [
                'reason' => 'Đã xác minh đầy đủ thông tin, khôi phục hiển thị.',
            ]);
        $restoreRes->assertOk();
        $this->assertFalse($post->fresh()->is_hidden);

        $this->assertDatabaseHas('moderation_records', [
            'organization_id' => $this->tenantA->id,
            'target_type' => FeedPost::class,
            'target_id' => $post->id,
            'action' => 'content_restored',
        ]);

        $this->assertDatabaseHas('outbox_messages', [
            'organization_id' => $this->tenantA->id,
            'event_type' => 'feed.post_restored',
            'aggregate_id' => $post->id,
        ]);
    }

    /**
     * Test 2: Conflict of interest guard blocks reviewer who is a party; reassignment allows new reviewer.
     */
    public function test_conflict_of_interest_guard_and_reviewer_reassignment(): void
    {
        $dispute = $this->createDisputeCase(
            $this->tenantA,
            $this->venueA,
            $this->customerAlice,
            $this->ownerA,
            'pending',
            'DSP-CONFLICT-1'
        );

        // Complainant tries to arbitrate -> 422 Conflict of interest
        $resComplainant = $this->actingAs($this->customerAlice)
            ->postJson("/api/v1/disputes/{$dispute->id}/arbitrate", [
                'decision' => 'resolve',
                'notes' => 'Tự phán quyết cho mình',
            ]);
        $resComplainant->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Conflict of interest: Reviewer cannot arbitrate a dispute in which they are an involved party.',
            ]);

        // Respondent (Owner A) tries to arbitrate -> 422 Conflict of interest
        $resRespondent = $this->actingAs($this->ownerA)
            ->postJson("/api/v1/disputes/{$dispute->id}/arbitrate", [
                'decision' => 'dismiss',
                'notes' => 'Tự bác bỏ khiếu nại',
            ]);
        $resRespondent->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Conflict of interest: Reviewer cannot arbitrate a dispute in which they are an involved party.',
            ]);

        // Platform admin reassigns reviewer to neutral admin/manager
        $newReviewer = $this->createCustomerUser('reviewer@test.local', 'Independent Reviewer');
        $reassignRes = $this->actingAs($this->platformAdmin)
            ->postJson("/api/v1/admin/disputes/{$dispute->id}/reassign", [
                'new_reviewer_id' => $newReviewer->id,
                'reason' => 'Chuyển người xử lý do xung đột lợi ích giữa chủ sân và khách hàng',
            ]);

        $reassignRes->assertOk();
        $freshDispute = $dispute->fresh();
        $this->assertEquals($newReviewer->id, $freshDispute->assigned_reviewer_id);
        $this->assertNotNull($freshDispute->conflict_reassigned_at);
        $this->assertEquals($this->platformAdmin->id, $freshDispute->conflict_reassigned_by);
        $this->assertStringContainsString('xung đột lợi ích', $freshDispute->reassignment_reason);

        // Platform admin can now arbitrate smoothly
        $arbitrateRes = $this->actingAs($this->platformAdmin)
            ->postJson("/api/v1/disputes/{$dispute->id}/arbitrate", [
                'decision' => 'resolve',
                'notes' => 'Phán quyết phân xử độc lập công tâm từ ban quản trị.',
            ]);
        $arbitrateRes->assertOk();
        $this->assertSame('resolved', $freshDispute->fresh()->status);
    }

    /**
     * Test 3: Party intake: booking party scope, deadline check (>30 days -> 422),
     * duplicate detection (409), and incomplete filing -> awaiting_evidence.
     */
    public function test_party_intake_scope_deadline_duplicate_and_awaiting_evidence(): void
    {
        // 1. Booking ended 35 days ago (> 30 days)
        $expiredBooking = $this->createBooking($this->customerAlice, $this->courtA, now()->subDays(35));

        $lateRes = $this->actingAs($this->customerAlice)
            ->postJson('/api/v1/disputes', [
                'title' => 'Khiếu nại đặt sân quá hạn',
                'summary' => 'Nội dung tóm tắt khiếu nại đã quá hạn 30 ngày theo quy định.',
                'dispute_type' => 'court_quality',
                'booking_id' => $expiredBooking->id,
            ]);
        $lateRes->assertStatus(422)
            ->assertJsonFragment(['message' => 'Filing window has expired for this booking.']);

        // 2. Stranger tries to file dispute for Alice's booking -> 403 Unauthorized
        $validBooking = $this->createBooking($this->customerAlice, $this->courtA, now()->subDays(5));

        $unauthRes = $this->actingAs($this->stranger)
            ->postJson('/api/v1/disputes', [
                'title' => 'Người lạ cố tình khiếu nại',
                'summary' => 'Tóm tắt nội dung khiếu nại hợp lệ về độ dài.',
                'dispute_type' => 'court_quality',
                'booking_id' => $validBooking->id,
            ]);
        $unauthRes->assertStatus(403);

        // 3. Incomplete filing: high severity or court_quality with NO evidence -> status becomes awaiting_evidence
        $intakeRes = $this->actingAs($this->customerAlice)
            ->postJson('/api/v1/disputes', [
                'title' => 'Sân chất lượng kém ngập nước',
                'summary' => 'Mặt sân trơn trượt ngập nước mưa không thể thi đấu an toàn.',
                'dispute_type' => 'court_quality',
                'severity' => 'high',
                'booking_id' => $validBooking->id,
                'evidence' => [],
            ]);

        $intakeRes->assertStatus(201);
        $disputeId = $intakeRes->json('data.id');
        $createdDispute = DisputeCase::findOrFail($disputeId);

        $this->assertSame('awaiting_evidence', $createdDispute->status);
        $this->assertNotNull($createdDispute->sla_deadline);
        $this->assertEquals(['photos_or_video', 'detailed_statement'], $createdDispute->requested_evidence);
        $this->assertEquals($this->tenantA->id, $createdDispute->organization_id);
        $this->assertEquals($this->venueA->id, $createdDispute->venue_id);

        // Outbox notification enqueued for evidence request
        $this->assertDatabaseHas('outbox_messages', [
            'event_type' => 'dispute.evidence_requested',
            'aggregate_id' => $createdDispute->id,
        ]);

        // 4. Duplicate check: Trying to file another dispute for same booking while one is active -> 409
        $dupRes = $this->actingAs($this->customerAlice)
            ->postJson('/api/v1/disputes', [
                'title' => 'Gửi trùng khiếu nại cho cùng booking',
                'summary' => 'Tóm tắt nội dung khiếu nại trùng lặp cho đơn đặt sân.',
                'dispute_type' => 'payment_issue',
                'booking_id' => $validBooking->id,
            ]);

        $dupRes->assertStatus(409)
            ->assertJsonFragment([
                'message' => 'A dispute is already active for this booking.',
                'dispute_id' => $createdDispute->id,
            ]);
    }

    /**
     * Test 4: Party evidence upload: adds evidence, advances awaiting_evidence to investigating.
     */
    public function test_party_evidence_upload_advances_status_to_investigating(): void
    {
        $dispute = $this->createDisputeCase(
            $this->tenantA,
            $this->venueA,
            $this->customerAlice,
            $this->ownerA,
            'awaiting_evidence',
            'DSP-EVID-1'
        );

        // Stranger cannot upload evidence
        $strangerRes = $this->actingAs($this->stranger)
            ->postJson("/api/v1/disputes/{$dispute->id}/evidence", [
                'evidence' => ['https://example.com/bogus.jpg'],
            ]);
        $strangerRes->assertStatus(403);

        // Alice (complainant) uploads evidence and explanation
        $uploadRes = $this->actingAs($this->customerAlice)
            ->postJson("/api/v1/disputes/{$dispute->id}/evidence", [
                'explanation' => 'Hình ảnh chụp mặt sân bị nứt và lưới rách.',
                'evidence' => [
                    ['url' => 'https://storage.local/court_crack.jpg', 'type' => 'image'],
                    ['url' => 'https://storage.local/court_net.jpg', 'type' => 'image'],
                ],
                'is_private' => true,
            ]);

        $uploadRes->assertOk();
        $dispute->refresh();

        // Status advanced to investigating
        $this->assertSame('investigating', $dispute->status);
        $this->assertCount(1, $dispute->evidence);
        $this->assertTrue($dispute->evidence[0]['is_private']);
        $this->assertSame('Hình ảnh chụp mặt sân bị nứt và lưới rách.', $dispute->evidence[0]['explanation']);

        // Check timeline events
        $events = $dispute->timeline_events ?? [];
        $lastEvent = end($events);
        $this->assertSame('evidence_submitted', $lastEvent['event']);
        $this->assertEquals($this->customerAlice->id, $lastEvent['actor_id']);
    }

    /**
     * Test 5: Decision versions and linked OwnerRefundObligation: obligation created in approved status, NOT auto-paid.
     */
    public function test_decision_creates_owner_refund_obligation_in_approved_status_not_autopaid(): void
    {
        $booking = $this->createBooking($this->customerAlice, $this->courtA);
        $dispute = $this->createDisputeCase(
            $this->tenantA,
            $this->venueA,
            $this->customerAlice,
            $this->ownerA,
            'investigating',
            'DSP-REFUND-1',
            $booking
        );

        $arbitrateRes = $this->actingAs($this->platformAdmin)
            ->postJson("/api/v1/admin/disputes/{$dispute->id}/arbitrate", [
                'decision' => 'resolve_and_refund',
                'refund_amount' => 200000,
                'refund_method' => 'owner_direct',
                'notes' => 'Chấp thuận hoàn lại 200,000 VND cho khách do sân lỗi.',
            ]);

        $arbitrateRes->assertOk();
        $this->assertSame('Đã ban hành phán quyết và tạo nghĩa vụ hoàn tiền chờ chủ cơ sở thực hiện.', $arbitrateRes->json('message'));
        $this->assertFalse(str_contains($arbitrateRes->json('message'), 'thành công'));
        $this->assertSame('resolved', $arbitrateRes->json('data.status'));
        $this->assertFalse($arbitrateRes->json('data.payment_completed'));
        $this->assertSame('approved', $arbitrateRes->json('data.refund_obligation_status'));

        $dispute->refresh();

        $this->assertSame('resolved', $dispute->status);
        $obligation = OwnerRefundObligation::findOrFail($dispute->refund_obligation_id);
        $this->assertEquals(200000, $obligation->amount);
        $this->assertEquals($this->tenantA->id, $obligation->organization_id);
        $this->assertEquals($this->venueA->id, $obligation->venue_id);
        $this->assertEquals($this->ownerA->id, $obligation->payer_id);

        // CRITICAL CHECK: Status is APPROVED, NOT paid or acknowledged!
        $this->assertSame(OwnerRefundObligation::STATUS_APPROVED, $obligation->status);
        $this->assertNull($obligation->recipient_acknowledged_at);
        $this->assertNull($obligation->owner_reported_at);

        // Outbox notification sent to complainant and respondent
        $this->assertDatabaseHas('outbox_messages', [
            'event_type' => 'dispute.arbitrated',
            'aggregate_id' => $dispute->id,
        ]);
    }

    /**
     * Test 6: Close gate: blocks closure (409) while refund obligation is pending; allows closure after obligation is acknowledged.
     */
    public function test_close_gate_blocks_pending_obligation_and_allows_after_settlement(): void
    {
        $booking = $this->createBooking($this->customerAlice, $this->courtA);
        $dispute = $this->createDisputeCase(
            $this->tenantA,
            $this->venueA,
            $this->customerAlice,
            $this->ownerA,
            'investigating',
            'DSP-GATE-1',
            $booking
        );

        // Arbitrate with refund
        $arbRes = $this->actingAs($this->platformAdmin)
            ->postJson("/api/v1/disputes/{$dispute->id}/arbitrate", [
                'decision' => 'resolve_and_refund',
                'refund_amount' => 150000,
                'notes' => 'Hoàn tiền cho khách hàng',
            ]);
        $arbRes->assertOk();

        $dispute->refresh();
        $this->assertSame('resolved', $dispute->status);
        $this->assertTrue($arbRes->json('data.has_pending_execution'));

        $obligation = OwnerRefundObligation::findOrFail($dispute->refund_obligation_id);
        $this->assertSame(OwnerRefundObligation::STATUS_APPROVED, $obligation->status);

        // 1. Trying to close while obligation is still in 'approved' status -> 409 Conflict
        $closeRes = $this->actingAs($this->platformAdmin)
            ->postJson("/api/v1/admin/disputes/{$dispute->id}/close");

        $closeRes->assertStatus(409)
            ->assertJsonFragment([
                'message' => 'Cannot close dispute: pending refund obligation must be executed or settled first.',
            ]);
        $this->assertSame('resolved', $dispute->fresh()->status);

        // 2. Owner acknowledges / settles the obligation
        $obligation->update([
            'status' => OwnerRefundObligation::STATUS_ACKNOWLEDGED,
            'recipient_acknowledged_at' => now(),
        ]);

        // 3. Close dispute now succeeds when obligation is acknowledged
        $closeSuccessRes = $this->actingAs($this->platformAdmin)
            ->postJson("/api/v1/admin/disputes/{$dispute->id}/close");

        $closeSuccessRes->assertOk();
        $this->assertFalse($closeSuccessRes->json('data.has_pending_execution'));
        $dispute->refresh();
        $this->assertSame('closed', $dispute->status);

        $events = $dispute->timeline_events ?? [];
        $lastEvent = end($events);
        $this->assertSame('dispute_closed', $lastEvent['event']);

        // 4. Verify closing also works when obligation is rejected
        $booking2 = $this->createBooking($this->customerAlice, $this->courtA, now()->subDays(3)->setTime(16, 0));
        $disputeRejected = $this->createDisputeCase(
            $this->tenantA,
            $this->venueA,
            $this->customerAlice,
            $this->ownerA,
            'investigating',
            'DSP-GATE-2',
            $booking2
        );

        $arbRes2 = $this->actingAs($this->platformAdmin)
            ->postJson("/api/v1/disputes/{$disputeRejected->id}/arbitrate", [
                'decision' => 'resolve_and_refund',
                'refund_amount' => 100000,
                'notes' => 'Hoàn tiền cho vụ việc thứ hai',
            ]);
        $arbRes2->assertOk();
        $disputeRejected->refresh();
        $this->assertSame('resolved', $disputeRejected->status);
        $this->assertTrue($arbRes2->json('data.has_pending_execution'));
        $obligation2 = OwnerRefundObligation::findOrFail($disputeRejected->refund_obligation_id);
        $obligation2->update([
            'status' => OwnerRefundObligation::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejected_by' => $this->ownerA->id,
        ]);

        $closeRejectedRes = $this->actingAs($this->platformAdmin)
            ->postJson("/api/v1/admin/disputes/{$disputeRejected->id}/close");

        $closeRejectedRes->assertOk();
        $this->assertFalse($closeRejectedRes->json('data.has_pending_execution'));
        $disputeRejected->refresh();
        $this->assertSame('closed', $disputeRejected->status);
    }

    /**
     * Test 7: Controlled reopening: requires material evidence and reason;
     * preserves past decision history and increments version.
     */
    public function test_controlled_reopening_preserves_history_and_increments_version(): void
    {
        $dispute = $this->createDisputeCase(
            $this->tenantA,
            $this->venueA,
            $this->customerAlice,
            $this->ownerA,
            'closed',
            'DSP-REOPEN-1'
        );
        $dispute->update([
            'resolution_decision' => 'dismiss',
            'arbitrator_notes' => 'Ban đầu bác bỏ khiếu nại vì chưa đủ căn cứ.',
            'arbitrated_by' => $this->platformAdmin->id,
            'arbitrated_at' => now()->subDays(1),
            'decision_version' => 1,
        ]);

        // 1. Validation fails without material evidence or short reason
        $failRes = $this->actingAs($this->customerAlice)
            ->postJson("/api/v1/disputes/{$dispute->id}/reopen", [
                'reason' => 'Hi',
                'material_evidence' => [],
            ]);
        $failRes->assertStatus(422);

        // 2. Stranger cannot reopen -> 403 or 404 (denies access without disclosing dispute)
        $strangerRes = $this->actingAs($this->stranger)
            ->postJson("/api/v1/disputes/{$dispute->id}/reopen", [
                'reason' => 'Lý do hợp lệ trên năm ký tự',
                'material_evidence' => ['https://example.com/proof.pdf'],
            ]);
        $this->assertContains($strangerRes->status(), [403, 404]);
        // 3. Complainant reopens with material evidence and reasoned request
        $reopenRes = $this->actingAs($this->customerAlice)
            ->postJson("/api/v1/disputes/{$dispute->id}/reopen", [
                'reason' => 'Bổ sung video camera giám sát thời điểm xảy ra va chạm sân trơn trượt.',
                'material_evidence' => [
                    ['url' => 'https://storage.local/cctv_footage.mp4', 'type' => 'video'],
                ],
            ]);

        $reopenRes->assertOk();
        $dispute->refresh();

        $this->assertSame('reopened', $dispute->status);
        $this->assertEquals(2, $dispute->decision_version);
        $this->assertCount(1, $dispute->decision_history);

        $pastDecision = $dispute->decision_history[0];
        $this->assertEquals(1, $pastDecision['version']);
        $this->assertSame('dismiss', $pastDecision['decision']);
        $this->assertSame('Ban đầu bác bỏ khiếu nại vì chưa đủ căn cứ.', $pastDecision['notes']);

        $this->assertNotNull($dispute->reopened_at);
        $this->assertEquals($this->customerAlice->id, $dispute->reopened_by);
        $this->assertSame('Bổ sung video camera giám sát thời điểm xảy ra va chạm sân trơn trượt.', $dispute->reopen_reason);
    }

    /**
     * Test 8: Cross-tenant scoping: verified tenant isolation.
     */
    public function test_cross_tenant_scoping_isolation(): void
    {
        $disputeTenantA = $this->createDisputeCase(
            $this->tenantA,
            $this->venueA,
            $this->customerAlice,
            $this->ownerA,
            'pending',
            'DSP-TENANT-A'
        );

        // Owner B (Tenant Beta) tries to view Dispute A -> 404 (or denied access without leakage)
        $viewRes = $this->actingAs($this->ownerB)
            ->getJson("/api/v1/disputes/{$disputeTenantA->id}");
        $viewRes->assertStatus(404);

        // Owner B tries to arbitrate Dispute A -> 404
        $arbRes = $this->actingAs($this->ownerB)
            ->postJson("/api/v1/disputes/{$disputeTenantA->id}/arbitrate", [
                'decision' => 'dismiss',
                'notes' => 'Owner B can thiệp trái phép',
            ]);
        $arbRes->assertStatus(404);

        // Owner B tries to reassign Dispute A -> 404
        $reassignRes = $this->actingAs($this->ownerB)
            ->postJson("/api/v1/disputes/{$disputeTenantA->id}/reassign", [
                'new_reviewer_id' => $this->ownerB->id,
                'reason' => 'Cố tình chuyển cho mình',
            ]);
        $reassignRes->assertStatus(404);

        // Owner A can view Dispute A
        $ownerAView = $this->actingAs($this->ownerA)
            ->getJson("/api/v1/disputes/{$disputeTenantA->id}");
        $ownerAView->assertOk()
            ->assertJsonPath('data.dispute_code', 'DSP-TENANT-A');
    }

    /**
     * Test 9: A customer's dispute names the venue's active owner as respondent, who is told of the case,
     * can answer with evidence, cannot arbitrate it, and is notified of the decision.
     */
    public function test_filed_dispute_names_venue_owner_as_notified_respondent(): void
    {
        $booking = $this->createBooking($this->customerAlice, $this->courtA);

        $fileRes = $this->actingAs($this->customerAlice)
            ->postJson('/api/v1/disputes', [
                'title' => 'Sân ngập nước không được đổi giờ',
                'summary' => 'Sân bị ngập nước và chủ sân không hỗ trợ đổi giờ chơi.',
                'dispute_type' => 'court_quality',
                'severity' => 'high',
                'booking_id' => $booking->id,
            ]);

        $fileRes->assertCreated()
            ->assertJsonPath('data.respondent.id', $this->ownerA->id);
        $disputeId = $fileRes->json('data.id');
        $this->assertDatabaseHas('dispute_cases', ['id' => $disputeId, 'respondent_id' => $this->ownerA->id]);
        $this->assertEquals(['dispute.evidence_requested'], $this->customerAlice->notifications()->get()->pluck('data.event')->all());
        $this->assertEquals(['dispute.filed'], $this->ownerA->notifications()->get()->pluck('data.event')->all());

        $this->actingAs($this->ownerA)
            ->postJson("/api/v1/disputes/{$disputeId}/evidence", [
                'explanation' => 'Sân đã được thoát nước trước giờ chơi.',
                'evidence' => [['url' => 'https://storage.local/drained.jpg', 'type' => 'image']],
            ])
            ->assertOk();

        $this->actingAs($this->ownerA)
            ->postJson("/api/v1/disputes/{$disputeId}/arbitrate", [
                'decision' => 'dismiss',
                'notes' => 'Chủ sân tự bác bỏ khiếu nại.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Conflict of interest: Reviewer cannot arbitrate a dispute in which they are an involved party.');

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/v1/admin/disputes/{$disputeId}/arbitrate", [
                'decision' => 'resolve',
                'notes' => 'Chấp thuận khiếu nại về chất lượng sân.',
            ])
            ->assertOk();

        $this->assertContains('dispute.arbitrated', $this->ownerA->notifications()->get()->pluck('data.event'));
    }

    /**
     * Test 10: A refund judgment on a dispute stored without a respondent charges the organization's active owner.
     */
    public function test_refund_judgment_without_respondent_charges_active_organization_owner(): void
    {
        $activeOwner = $this->replaceOwner($this->tenantA, $this->ownerA);
        $booking = $this->createBooking($this->customerAlice, $this->courtA);
        $dispute = $this->createDisputeCase(
            $this->tenantA,
            $this->venueA,
            $this->customerAlice,
            $this->ownerA,
            'investigating',
            'DSP-NO-RESPONDENT',
            $booking
        );
        $dispute->update(['respondent_id' => null]);

        $this->actingAs($this->platformAdmin)
            ->postJson("/api/v1/admin/disputes/{$dispute->id}/arbitrate", [
                'decision' => 'resolve_and_refund',
                'refund_amount' => 150000,
                'refund_method' => 'owner_direct',
                'notes' => 'Hoàn tiền cho khách do sân lỗi.',
            ])
            ->assertOk();

        $obligation = OwnerRefundObligation::findOrFail($dispute->fresh()->refund_obligation_id);
        $this->assertEquals($activeOwner->id, $obligation->payer_id);
    }

    /**
     * Test 11: The respondent backfill names the active owner on disputes filed over HTTP and leaves other rows alone.
     */
    public function test_respondent_backfill_names_active_organization_owner(): void
    {
        $activeOwnerB = $this->replaceOwner($this->tenantB, $this->ownerB);
        $bookingA = $this->createBooking($this->customerAlice, $this->courtA);
        $bookingB = $this->createBooking($this->customerBob, $this->courtB);
        $disputeA = $this->createDisputeCase($this->tenantA, $this->venueA, $this->customerAlice, $this->ownerA, 'pending', 'DSP-LEGACY-A', $bookingA);
        $disputeB = $this->createDisputeCase($this->tenantB, $this->venueB, $this->customerBob, $this->ownerB, 'pending', 'DSP-LEGACY-B', $bookingB);
        $answered = $this->createDisputeCase($this->tenantA, $this->venueA, $this->customerBob, $this->stranger, 'pending', 'DSP-ANSWERED', $bookingA);
        $seeded = $this->createDisputeCase($this->tenantA, $this->venueA, $this->customerBob, $this->ownerA, 'pending', 'DSP-SEEDED');
        DisputeCase::whereKey([$disputeA->id, $disputeB->id, $seeded->id])->update(['respondent_id' => null]);

        (require database_path('migrations/2026_10_12_000001_backfill_dispute_case_respondents.php'))->up();

        $this->assertEquals($this->ownerA->id, $disputeA->fresh()->respondent_id);
        $this->assertEquals($activeOwnerB->id, $disputeB->fresh()->respondent_id);
        $this->assertEquals($this->stranger->id, $answered->fresh()->respondent_id);
        $this->assertNull($seeded->fresh()->respondent_id);
    }

    /**
     * Test 12: A named respondent who no longer owns the venue loses the case; the owner after handover answers and reopens it.
     */
    public function test_suspended_respondent_loses_access_to_organization_dispute(): void
    {
        $dispute = $this->createDisputeCase($this->tenantA, $this->venueA, $this->customerAlice, $this->ownerA, 'pending', 'DSP-FORMER-OWNER');
        $activeOwner = $this->replaceOwner($this->tenantA, $this->ownerA);

        $this->actingAs($this->ownerA)->getJson("/api/v1/disputes/{$dispute->id}")->assertStatus(404);
        $this->assertNotContains(
            'DSP-FORMER-OWNER',
            collect($this->actingAs($this->ownerA)->getJson('/api/v1/disputes')->json('data'))->pluck('dispute_code')
        );
        $this->actingAs($this->ownerA)
            ->postJson("/api/v1/disputes/{$dispute->id}/evidence", [
                'explanation' => 'Tôi không còn quản lý cơ sở này.',
            ])
            ->assertStatus(403);

        $this->actingAs($activeOwner)
            ->postJson("/api/v1/disputes/{$dispute->id}/evidence", [
                'explanation' => 'Chủ mới tiếp nhận và phản hồi khiếu nại.',
                'evidence' => [['url' => 'https://storage.local/handover.jpg', 'type' => 'image']],
            ])
            ->assertOk();

        $dispute->update(['status' => 'dismissed']);
        $this->actingAs($activeOwner)
            ->postJson("/api/v1/disputes/{$dispute->id}/reopen", [
                'reason' => 'Chủ mới có chứng cứ mới sau quyết định.',
                'material_evidence' => [['url' => 'https://storage.local/after-handover.jpg', 'type' => 'image']],
            ])
            ->assertOk();
        $this->assertSame('reopened', $dispute->fresh()->status);
    }

    /**
     * Test 13: A respondent demoted to venue manager can still read the case but can no longer answer or reopen it.
     */
    public function test_demoted_respondent_cannot_answer_or_reopen_organization_dispute(): void
    {
        $dispute = $this->createDisputeCase($this->tenantA, $this->venueA, $this->customerAlice, $this->ownerA, 'resolved', 'DSP-DEMOTED-OWNER');
        OrganizationMember::where('organization_id', $this->tenantA->id)
            ->where('user_id', $this->ownerA->id)
            ->update(['role' => OrganizationMember::ROLE_VENUE_MANAGER]);

        $this->actingAs($this->ownerA)->getJson("/api/v1/disputes/{$dispute->id}")->assertOk();
        $this->actingAs($this->ownerA)
            ->postJson("/api/v1/disputes/{$dispute->id}/evidence", [
                'evidence' => [['url' => 'https://storage.local/late.jpg', 'type' => 'image']],
            ])
            ->assertStatus(403);
        $this->actingAs($this->ownerA)
            ->postJson("/api/v1/disputes/{$dispute->id}/reopen", [
                'reason' => 'Có chứng cứ mới về tình trạng sân.',
                'material_evidence' => [['url' => 'https://storage.local/new.jpg', 'type' => 'image']],
            ])
            ->assertStatus(403);
        $this->assertSame('resolved', $dispute->fresh()->status);
    }

    /**
     * Suspends the organization's current owner and adds a later, active one. A venue manager and a
     * platform admin holding owner membership join first, so lookups must skip them by role, not by order.
     */
    private function replaceOwner(Organization $org, User $owner): User
    {
        OrganizationMember::where('organization_id', $org->id)->where('user_id', $owner->id)->update(['status' => 'suspended']);
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $this->createCustomerUser("manager_{$org->slug}@test.local", "Manager {$org->name}")->id,
            'role' => OrganizationMember::ROLE_VENUE_MANAGER,
            'status' => 'active',
        ]);
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $this->platformAdmin->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);
        $newOwner = $this->createCustomerUser("new_owner_{$org->slug}@test.local", "New Owner {$org->name}");
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $newOwner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        return $newOwner;
    }
}
