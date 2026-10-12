<?php

namespace Tests\Feature\Readiness;

use App\Models\Booking;
use App\Models\Court;
use App\Models\DisputeCase;
use App\Models\FeedPost;
use App\Models\ModerationRecord;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Reservation;
use App\Models\SportZone;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\WalletPaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Task 13.2 Readiness Acceptance: Comprehensive Role, Scoping & Lifecycle Matrix
 *
 * Covers:
 * - Matrix 1: Account Status & Suspended Users (D01, D02)
 * - Matrix 2: Organization Owner != Global Admin Separation (D07)
 * - Matrix 3: Venue Scoping, Revoked Grants & Role Transitions (D08)
 * - Matrix 4: Dispute Case, Evidence & Attachments Scoping & Conflict of Interest
 * - Matrix 5: Social Channels, Community Feed & Moderation Isolation (D59)
 * - Matrix 6: Financial Reports, Data Export & Venue Scoping (D46, D47, D48)
 */
class ComprehensiveRoleAndScopingMatrixTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgAlpha;

    private User $ownerAlpha;

    private Venue $venueAlpha;

    private Court $courtAlpha;

    private SportZone $zoneAlpha;

    private Venue $venueAlpha2;

    private Court $courtAlpha2;

    private Organization $orgBeta;

    private User $ownerBeta;

    private Venue $venueBeta;

    private Court $courtBeta;

    private User $managerAlpha;

    private OrganizationMember $memberManagerAlpha;

    private User $platformAdmin;

    private User $customerAlice;

    private User $customerBob;

    private User $outsider;

    private User $suspendedUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Organization Alpha
        $this->orgAlpha = Organization::create([
            'name' => 'Alpha Sports Complex',
            'slug' => 'alpha-sports-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        $this->ownerAlpha = $this->createCustomerUser('owner.alpha@test.local', 'Owner Alpha');

        OrganizationMember::create([
            'organization_id' => $this->orgAlpha->id,
            'user_id' => $this->ownerAlpha->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        // Venue Alpha (primary)
        $this->venueAlpha = Venue::create([
            'organization_id' => $this->orgAlpha->id,
            'name' => ['vi' => 'Sân Alpha 1', 'en' => 'Alpha Arena 1'],
            'slug' => 'alpha-arena-1-'.uniqid(),
            'address' => '100 Alpha Street, District 1',
            'status' => 'published',
            'is_active' => true,
        ]);

        $this->zoneAlpha = SportZone::create([
            'venue_id' => $this->venueAlpha->id,
            'name' => ['vi' => 'Khu Cầu Lông Alpha', 'en' => 'Alpha Badminton Zone'],
            'sport_type' => 'badminton',
            'slot_duration' => 60,
            'is_active' => true,
        ]);

        $this->courtAlpha = Court::create([
            'venue_id' => $this->venueAlpha->id,
            'sport_zone_id' => $this->zoneAlpha->id,
            'name' => ['vi' => 'Sân Alpha A1', 'en' => 'Court Alpha A1'],
            'is_active' => true,
        ]);

        // Venue Alpha 2 (sibling in same organization)
        $this->venueAlpha2 = Venue::create([
            'organization_id' => $this->orgAlpha->id,
            'name' => ['vi' => 'Sân Alpha 2', 'en' => 'Alpha Arena 2'],
            'slug' => 'alpha-arena-2-'.uniqid(),
            'address' => '102 Alpha Street, District 1',
            'status' => 'published',
            'is_active' => true,
        ]);

        $zoneAlpha2 = SportZone::create([
            'venue_id' => $this->venueAlpha2->id,
            'name' => ['vi' => 'Khu Pickleball Alpha 2', 'en' => 'Alpha Pickleball Zone'],
            'sport_type' => 'pickleball',
            'slot_duration' => 60,
            'is_active' => true,
        ]);

        $this->courtAlpha2 = Court::create([
            'venue_id' => $this->venueAlpha2->id,
            'sport_zone_id' => $zoneAlpha2->id,
            'name' => ['vi' => 'Sân Alpha A2', 'en' => 'Court Alpha A2'],
            'is_active' => true,
        ]);

        // 2. Create Venue Manager for Venue Alpha only
        $this->managerAlpha = $this->createCustomerUser('manager.alpha@test.local', 'Manager Alpha');
        $this->memberManagerAlpha = OrganizationMember::create([
            'organization_id' => $this->orgAlpha->id,
            'user_id' => $this->managerAlpha->id,
            'role' => OrganizationMember::ROLE_VENUE_MANAGER,
            'permissions' => [
                'venue_ids' => [$this->venueAlpha->id],
                'venue_id' => $this->venueAlpha->id,
                'functions' => ['pricing', 'calendar', 'reports'],
            ],
            'status' => 'active',
        ]);

        // 3. Create Organization Beta (separate tenant)
        $this->orgBeta = Organization::create([
            'name' => 'Beta Sports Club',
            'slug' => 'beta-sports-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        $this->ownerBeta = $this->createCustomerUser('owner.beta@test.local', 'Owner Beta');

        OrganizationMember::create([
            'organization_id' => $this->orgBeta->id,
            'user_id' => $this->ownerBeta->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        $this->venueBeta = Venue::create([
            'organization_id' => $this->orgBeta->id,
            'name' => ['vi' => 'Sân Beta', 'en' => 'Beta Stadium'],
            'slug' => 'beta-stadium-'.uniqid(),
            'address' => '200 Beta Street, District 2',
            'status' => 'published',
            'is_active' => true,
        ]);

        $zoneBeta = SportZone::create([
            'venue_id' => $this->venueBeta->id,
            'name' => ['vi' => 'Khu Cầu Lông Beta', 'en' => 'Beta Badminton Zone'],
            'sport_type' => 'badminton',
            'slot_duration' => 60,
            'is_active' => true,
        ]);

        $this->courtBeta = Court::create([
            'venue_id' => $this->venueBeta->id,
            'sport_zone_id' => $zoneBeta->id,
            'name' => ['vi' => 'Sân Beta B1', 'en' => 'Court Beta B1'],
            'is_active' => true,
        ]);

        // 4. Platform Admin & Customers
        $this->platformAdmin = User::factory()->create([
            'name' => 'Platform Super Admin',
            'email' => 'admin@platform.vn',
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        $this->customerAlice = $this->createCustomerUser('alice@test.local', 'Alice Customer');
        $this->customerBob = $this->createCustomerUser('bob@test.local', 'Bob Customer');
        $this->outsider = $this->createCustomerUser('outsider@test.local', 'Outsider User');

        // Suspended User
        $this->suspendedUser = User::factory()->create([
            'name' => 'Suspended Account User',
            'email' => 'suspended@test.local',
            'role' => 'customer',
            'status' => 'suspended',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
        OrganizationMember::create([
            'organization_id' => $this->orgAlpha->id,
            'user_id' => $this->suspendedUser->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);
        // Credit Alice's wallet for reservation checkout tests
        $walletService = app(WalletPaymentService::class);
        $aliceWallet = $walletService->getOrCreateWallet($this->customerAlice->id);
        $aliceWallet->update(['balance' => 5000000]);

        // Configure required feature flags for social and finance
        config([
            'features.flags.marketplace_payouts' => true,
            'features.flags.social_matchmaking_tournaments' => true,
            'features.pilot_organizations' => [
                (string) $this->orgAlpha->id,
                (string) $this->orgBeta->id,
            ],
        ]);
    }

    private function createCustomerUser(string $email, string $name = 'Customer User'): User
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

    // =========================================================================
    // MATRIX 1: Account Status & Suspended Users (D01, D02)
    // =========================================================================

    /**
     * Suspended user is blocked from creating bookings and reservations with 403.
     */
    public function test_matrix_1_suspended_user_is_blocked_from_creating_bookings_and_reservations(): void
    {
        Sanctum::actingAs($this->suspendedUser);

        $initialBookings = Booking::count();
        $initialReservations = Reservation::count();
        $slotTime = Carbon::tomorrow()->setTime(9, 0)->toIso8601String();

        // 1. V1 Booking attempt
        $v1Response = $this->postJson('/api/v1/bookings', [
            'court_id' => $this->courtAlpha->id,
            'slots' => [$slotTime],
        ]);

        $v1Response->assertStatus(403);
        $v1Response->assertJsonPath('error', 'account_restricted');
        $this->assertSame($initialBookings, Booking::count(), 'Suspended account must not create V1 bookings.');

        // 2. V2 Reservation attempt
        $v2Response = $this->withHeaders([
            'X-Organization-Id' => (string) $this->orgAlpha->id,
        ])->postJson('/api/v2/reservations', [
            'court_id' => $this->courtAlpha->id,
            'slots' => [$slotTime],
        ]);

        $v2Response->assertStatus(403);
        $v2Response->assertJsonPath('error', 'account_restricted');
        $this->assertSame($initialReservations, Reservation::count(), 'Suspended account must not create V2 reservations.');
    }

    /**
     * Suspended user is blocked from accessing venue administration, filing disputes, or partner application.
     */
    public function test_matrix_1_suspended_user_is_blocked_from_venue_admin_disputes_and_partner_start(): void
    {
        // Even if the user was previously marked as an organization member
        OrganizationMember::where('organization_id', $this->orgAlpha->id)
            ->where('user_id', $this->suspendedUser->id)
            ->update(['role' => OrganizationMember::ROLE_ORGANIZATION_OWNER]);
        Sanctum::actingAs($this->suspendedUser);

        // 1. Admin venues endpoint
        $venuesRes = $this->getJson('/api/v1/admin/venues');
        $venuesRes->assertStatus(403);
        $venuesRes->assertJsonPath('error', 'account_restricted');

        // 2. Filing disputes
        $disputeRes = $this->postJson('/api/v1/disputes', [
            'title' => 'Khiếu nại kiểm thử',
            'summary' => 'Tóm tắt sự cố',
            'dispute_type' => 'court_quality',
            'severity' => 'medium',
        ]);
        $disputeRes->assertStatus(403);
        $disputeRes->assertJsonPath('error', 'account_restricted');

        // 3. Partner application start
        $partnerRes = $this->postJson('/api/v1/partner/application/start', [
            'business_type' => 'household',
            'tax_code' => '0123456789',
        ]);
        $partnerRes->assertStatus(403);
    }

    /**
     * Suspended user retains access to whitelisted endpoints (auth/me and auth/logout).
     */
    public function test_matrix_1_suspended_user_retains_whitelisted_profile_and_logout_access(): void
    {
        Sanctum::actingAs($this->suspendedUser);

        // GET auth/me returns 200 with suspended status
        $meRes = $this->getJson('/api/v1/auth/me');
        $meRes->assertOk();
        $this->assertSame('suspended', $meRes->json('status'));

        // POST auth/logout returns 200
        $logoutRes = $this->postJson('/api/v1/auth/logout');
        $logoutRes->assertOk();
    }

    // =========================================================================
    // MATRIX 2: Organization Owner != Global Admin Separation (D07)
    // =========================================================================

    /**
     * Organization Owner cannot access platform subscription plan management routes.
     */
    public function test_matrix_2_organization_owner_cannot_access_platform_subscription_plan_management(): void
    {
        $this->actingAs($this->ownerAlpha);

        // GET platform plans (admin catalog)
        $this->getJson('/api/v1/admin/platform-plans')->assertStatus(403);

        // POST create platform plan
        $this->postJson('/api/v1/admin/platform-plans', [
            'name' => 'Hacked Enterprise Plan',
            'code' => 'hacked_plan',
            'price' => 999999,
            'billing_cycle' => 'monthly',
        ])->assertStatus(403);

        // POST publish platform plan
        $this->postJson('/api/v1/admin/platform-plans/1/publish', [
            'expected_version' => 1,
        ])->assertStatus(403);
    }

    /**
     * Organization Owner cannot access platform-wide global metrics, KYC, payment audits, or admin dispute queue.
     */
    public function test_matrix_2_organization_owner_cannot_access_platform_global_metrics_and_audits(): void
    {
        $this->actingAs($this->ownerAlpha);

        // 1. Audit logs
        $this->getJson('/api/v1/admin/audit-logs')->assertStatus(403);

        // 2. KYC platform reviews
        $this->getJson('/api/v1/admin/kyc')->assertStatus(403);
        $this->getJson('/api/v1/admin/kyc/stats')->assertStatus(403);

        // 3. Global payment reviews
        $this->getJson('/api/v1/admin/payments')->assertStatus(403);

        // 4. Partner application reviews
        $this->getJson('/api/v1/admin/partner/applications')->assertStatus(403);

        // 5. Global platform admin dispute queue
        $this->getJson('/api/v1/admin/disputes')->assertStatus(403);
    }

    /**
     * Organization Owner cannot access another organization's disputes or settlements.
     */
    public function test_matrix_2_organization_owner_cannot_access_foreign_organization_disputes_or_settlements(): void
    {
        // Create dispute in Org Beta
        $disputeBeta = DisputeCase::create([
            'dispute_code' => 'DSP-BETA-'.uniqid(),
            'organization_id' => $this->orgBeta->id,
            'venue_id' => $this->venueBeta->id,
            'court_name' => 'Sân Beta B1',
            'title' => 'Tranh chấp tại Sân Beta',
            'summary' => 'Sự cố phát sinh tại Beta',
            'complainant_id' => $this->customerAlice->id,
            'complainant_name' => $this->customerAlice->name,
            'respondent_id' => $this->ownerBeta->id,
            'respondent_name' => $this->ownerBeta->name,
            'facility_name' => 'Beta Stadium',
            'status' => 'pending',
            'severity' => 'medium',
            'dispute_type' => 'court_quality',
        ]);

        $this->actingAs($this->ownerAlpha);

        // 1. Owner Alpha attempts to read Org Beta dispute details -> 404 (does not disclose)
        $detailRes = $this->getJson("/api/v1/disputes/{$disputeBeta->id}");
        $detailRes->assertStatus(404);

        // 2. Owner Alpha attempts to arbitrate Org Beta dispute -> 404
        $arbitrateRes = $this->postJson("/api/v1/disputes/{$disputeBeta->id}/arbitrate", [
            'decision' => 'resolve',
            'notes' => 'Owner Alpha can thiệp trái phép',
        ]);
        $arbitrateRes->assertStatus(404);

        // 3. Owner Alpha attempts to query Org Beta settlements -> 403
        $settlementsRes = $this->withHeaders([
            'X-Organization-Id' => (string) $this->orgBeta->id,
        ])->getJson('/api/v2/finance/settlements');
        $settlementsRes->assertStatus(403);

        // 4. Owner Alpha attempts to export Org Beta settlements CSV -> 403
        $settlementsCsvRes = $this->withHeaders([
            'X-Organization-Id' => (string) $this->orgBeta->id,
        ])->getJson('/api/v2/finance/settlements.csv');
        $settlementsCsvRes->assertStatus(403);
    }

    // =========================================================================
    // MATRIX 3: Venue Scoping & Revoked Grants (D08)
    // =========================================================================

    /**
     * Venue Manager granted to Venue Alpha can access Venue Alpha data.
     */
    public function test_matrix_3_venue_manager_can_access_assigned_venue_data(): void
    {
        $this->actingAs($this->managerAlpha);

        // 1. Can update assigned Venue Alpha
        $patchRes = $this->patchJson("/api/v1/admin/venues/{$this->venueAlpha->id}", [
            'address' => 'Updated Alpha Address 888',
        ]);
        $patchRes->assertOk();
        $this->assertDatabaseHas('venues', [
            'id' => $this->venueAlpha->id,
            'address' => 'Updated Alpha Address 888',
        ]);

        // 2. Can set price on Court Alpha
        $priceRes = $this->postJson("/api/v1/admin/courts/{$this->courtAlpha->id}/prices", [
            'weekday' => 1,
            'starts_at' => '10:00',
            'ends_at' => '11:00',
            'price' => 120000,
            'is_active' => true,
        ]);
        $priceRes->assertCreated();

        // 3. Can query reports for Venue Alpha
        $reportRes = $this->getJson("/api/v1/admin/reports?venue_id={$this->venueAlpha->id}");
        $reportRes->assertOk();
    }

    /**
     * Venue Manager CANNOT access Venue Beta (in same or different org) -> 403.
     */
    public function test_matrix_3_venue_manager_cannot_access_sibling_or_foreign_venue(): void
    {
        $this->actingAs($this->managerAlpha);

        // 1. Sibling venue in SAME organization (Venue Alpha 2) is denied (403)
        $this->patchJson("/api/v1/admin/venues/{$this->venueAlpha2->id}", [
            'address' => 'Hacked Sibling Address',
        ])->assertStatus(403);

        $this->postJson("/api/v1/admin/courts/{$this->courtAlpha2->id}/prices", [
            'weekday' => 1,
            'starts_at' => '10:00',
            'ends_at' => '11:00',
            'price' => 150000,
            'is_active' => true,
        ])->assertStatus(403);

        $this->getJson("/api/v1/admin/reports?venue_id={$this->venueAlpha2->id}")->assertStatus(403);

        // 2. Foreign venue in DIFFERENT organization (Venue Beta) is denied (403)
        $this->patchJson("/api/v1/admin/venues/{$this->venueBeta->id}", [
            'address' => 'Hacked Foreign Address',
        ])->assertStatus(403);

        $this->postJson("/api/v1/admin/courts/{$this->courtBeta->id}/prices", [
            'weekday' => 1,
            'starts_at' => '10:00',
            'ends_at' => '11:00',
            'price' => 150000,
            'is_active' => true,
        ])->assertStatus(403);

        $this->getJson("/api/v1/admin/reports?venue_id={$this->venueBeta->id}")->assertStatus(403);
    }

    /**
     * When grant is revoked or shifted, subsequent requests immediately return 403.
     */
    public function test_matrix_3_revoked_grant_or_removed_membership_immediately_blocks_manager(): void
    {
        // Verify manager can initially set price on Court Alpha
        $this->actingAs($this->managerAlpha)
            ->postJson("/api/v1/admin/courts/{$this->courtAlpha->id}/prices", [
                'weekday' => 2,
                'starts_at' => '07:00',
                'ends_at' => '08:00',
                'price' => 90000,
                'is_active' => true,
            ])->assertCreated();

        // 1. Shift grant: Owner Alpha shifts manager to Venue Alpha 2, revoking Venue Alpha
        $updateGrantRes = $this->actingAs($this->ownerAlpha)
            ->patchJson("/api/v1/organizations/{$this->orgAlpha->id}/members/{$this->memberManagerAlpha->id}", [
                'role' => OrganizationMember::ROLE_VENUE_MANAGER,
                'venue_id' => $this->venueAlpha2->id,
                'venue_ids' => [$this->venueAlpha2->id],
            ]);
        $updateGrantRes->assertOk();

        // Immediate effect: subsequent request to Venue Alpha returns 403
        $this->actingAs($this->managerAlpha->fresh())
            ->postJson("/api/v1/admin/courts/{$this->courtAlpha->id}/prices", [
                'weekday' => 3,
                'starts_at' => '07:00',
                'ends_at' => '08:00',
                'price' => 95000,
                'is_active' => true,
            ])->assertStatus(403);

        // But manager can now manage Court Alpha 2
        $this->actingAs($this->managerAlpha->fresh())
            ->postJson("/api/v1/admin/courts/{$this->courtAlpha2->id}/prices", [
                'weekday' => 3,
                'starts_at' => '07:00',
                'ends_at' => '08:00',
                'price' => 110000,
                'is_active' => true,
            ])->assertCreated();

        // 2. Removal: Owner Alpha deletes manager membership
        $deleteMemberRes = $this->actingAs($this->ownerAlpha)
            ->deleteJson("/api/v1/organizations/{$this->orgAlpha->id}/members/{$this->memberManagerAlpha->id}");
        $deleteMemberRes->assertOk();

        // Immediate effect: manager is completely denied across all venues in the org
        $this->actingAs($this->managerAlpha->fresh())
            ->postJson("/api/v1/admin/courts/{$this->courtAlpha2->id}/prices", [
                'weekday' => 4,
                'starts_at' => '07:00',
                'ends_at' => '08:00',
                'price' => 115000,
                'is_active' => true,
            ])->assertStatus(403);

        $this->actingAs($this->managerAlpha->fresh())
            ->getJson('/api/v1/admin/venues')
            ->assertStatus(403);
    }

    // =========================================================================
    // MATRIX 4: Dispute Case, Evidence & Attachments Scoping & Conflict of Interest
    // =========================================================================

    /**
     * Complainant and respondent can access case and upload private evidence.
     */
    public function test_matrix_4_parties_can_access_case_and_upload_private_evidence(): void
    {
        $dispute = DisputeCase::create([
            'dispute_code' => 'DSP-PARTY-'.uniqid(),
            'organization_id' => $this->orgAlpha->id,
            'venue_id' => $this->venueAlpha->id,
            'court_name' => 'Sân Alpha A1',
            'title' => 'Sự cố hư hại mặt sân',
            'summary' => 'Mặt sân trơn trượt gây chấn thương',
            'complainant_id' => $this->customerAlice->id,
            'complainant_name' => $this->customerAlice->name,
            'respondent_id' => $this->ownerAlpha->id,
            'respondent_name' => $this->ownerAlpha->name,
            'facility_name' => 'Alpha Arena 1',
            'status' => 'pending',
            'severity' => 'medium',
            'dispute_type' => 'court_quality',
        ]);

        // 1. Complainant Alice views dispute detail
        $this->actingAs($this->customerAlice)
            ->getJson("/api/v1/disputes/{$dispute->id}")
            ->assertOk()
            ->assertJsonPath('data.dispute_code', $dispute->dispute_code);

        // 2. Respondent Owner Alpha views dispute detail
        $this->actingAs($this->ownerAlpha)
            ->getJson("/api/v1/disputes/{$dispute->id}")
            ->assertOk()
            ->assertJsonPath('data.dispute_code', $dispute->dispute_code);

        // 3. Complainant Alice uploads private evidence
        $aliceUpload = $this->actingAs($this->customerAlice)
            ->postJson("/api/v1/disputes/{$dispute->id}/evidence", [
                'explanation' => 'Hình ảnh chụp vị trí sân ướt và đọng nước.',
                'evidence' => [
                    ['url' => 'https://storage.local/court_water_leak.jpg', 'type' => 'image'],
                ],
                'is_private' => true,
            ]);
        $aliceUpload->assertOk();

        $freshDispute = $dispute->fresh();
        $evidenceList = $freshDispute->evidence ?? [];
        $this->assertNotEmpty($evidenceList);
        $lastEvidence = end($evidenceList);
        $this->assertTrue($lastEvidence['is_private']);
        $this->assertSame($this->customerAlice->id, $lastEvidence['submitted_by']);
        $this->assertSame('Hình ảnh chụp vị trí sân ướt và đọng nước.', $lastEvidence['explanation']);
        // 4. Respondent Owner Alpha uploads private rebuttal evidence
        $ownerUpload = $this->actingAs($this->ownerAlpha)
            ->postJson("/api/v1/disputes/{$dispute->id}/evidence", [
                'explanation' => 'Biên bản kiểm tra kỹ thuật định kỳ sân.',
                'evidence' => [
                    ['url' => 'https://storage.local/inspection_sheet.pdf', 'type' => 'document'],
                ],
                'is_private' => true,
            ]);
        $ownerUpload->assertOk();

        $freshDispute2 = $dispute->fresh();
        $evidenceList2 = $freshDispute2->evidence ?? [];
        $this->assertCount(2, $evidenceList2);
        $secondEvidence = $evidenceList2[1];
        $this->assertTrue($secondEvidence['is_private']);
        $this->assertSame($this->ownerAlpha->id, $secondEvidence['submitted_by']);
    }

    /**
     * Unrelated customer / outsider cannot access case details -> 404/403.
     */
    public function test_matrix_4_outsider_cannot_access_or_upload_to_dispute(): void
    {
        $dispute = DisputeCase::create([
            'dispute_code' => 'DSP-OUTSIDER-'.uniqid(),
            'organization_id' => $this->orgAlpha->id,
            'venue_id' => $this->venueAlpha->id,
            'court_name' => 'Sân Alpha A1',
            'title' => 'Tranh chấp nội bộ',
            'summary' => 'Vấn đề giữa Alice và Venue Alpha',
            'complainant_id' => $this->customerAlice->id,
            'complainant_name' => $this->customerAlice->name,
            'respondent_id' => $this->ownerAlpha->id,
            'respondent_name' => $this->ownerAlpha->name,
            'facility_name' => 'Alpha Arena 1',
            'status' => 'pending',
            'severity' => 'medium',
            'dispute_type' => 'court_quality',
        ]);

        $this->actingAs($this->outsider);

        // 1. Outsider cannot view dispute details (404 Not Found to prevent leaking case existence)
        $detailRes = $this->getJson("/api/v1/disputes/{$dispute->id}");
        $detailRes->assertStatus(404);

        // 2. Outsider cannot upload evidence (403 Forbidden)
        $uploadRes = $this->postJson("/api/v1/disputes/{$dispute->id}/evidence", [
            'explanation' => 'Spam evidence from outsider',
            'evidence' => [['url' => 'https://storage.local/fake.jpg', 'type' => 'image']],
        ]);
        $uploadRes->assertStatus(403);
    }

    /**
     * Reviewer who is party cannot arbitrate (422 conflict of interest).
     */
    public function test_matrix_4_reviewer_who_is_party_cannot_arbitrate_due_to_conflict_of_interest(): void
    {
        $dispute = DisputeCase::create([
            'dispute_code' => 'DSP-CONFLICT-'.uniqid(),
            'organization_id' => $this->orgAlpha->id,
            'venue_id' => $this->venueAlpha->id,
            'court_name' => 'Sân Alpha A1',
            'title' => 'Tranh chấp kiểm thử xung đột lợi ích',
            'summary' => 'Alice và Owner Alpha có tranh chấp',
            'complainant_id' => $this->customerAlice->id,
            'complainant_name' => $this->customerAlice->name,
            'respondent_id' => $this->ownerAlpha->id,
            'respondent_name' => $this->ownerAlpha->name,
            'facility_name' => 'Alpha Arena 1',
            'status' => 'pending',
            'severity' => 'medium',
            'dispute_type' => 'court_quality',
        ]);

        // 1. Complainant Alice tries to arbitrate -> 422 Conflict of interest
        $aliceArb = $this->actingAs($this->customerAlice)
            ->postJson("/api/v1/disputes/{$dispute->id}/arbitrate", [
                'decision' => 'resolve',
                'notes' => 'Tự phán quyết cho bản thân',
            ]);
        $aliceArb->assertStatus(422);
        $aliceArb->assertJsonFragment([
            'message' => 'Conflict of interest: Reviewer cannot arbitrate a dispute in which they are an involved party.',
        ]);

        // 2. Respondent Owner Alpha tries to arbitrate -> 422 Conflict of interest
        $ownerArb = $this->actingAs($this->ownerAlpha)
            ->postJson("/api/v1/disputes/{$dispute->id}/arbitrate", [
                'decision' => 'dismiss',
                'notes' => 'Tự bác bỏ khiếu nại đối với cơ sở của mình',
            ]);
        $ownerArb->assertStatus(422);
        $ownerArb->assertJsonFragment([
            'message' => 'Conflict of interest: Reviewer cannot arbitrate a dispute in which they are an involved party.',
        ]);

        // 3. Neutral Platform Admin arbitrates smoothly
        $adminArb = $this->actingAs($this->platformAdmin)
            ->postJson("/api/v1/admin/disputes/{$dispute->id}/arbitrate", [
                'decision' => 'resolve',
                'notes' => 'Phán quyết công tâm từ ban quản trị nền tảng độc lập.',
            ]);
        $adminArb->assertOk();
        $this->assertSame('resolved', $dispute->fresh()->status);
    }

    // =========================================================================
    // MATRIX 5: Social Channels & Moderation (D59)
    // =========================================================================

    /**
     * Ordinary customer cannot delete or hide another user's post -> 403.
     */
    public function test_matrix_5_ordinary_customer_cannot_delete_or_hide_another_users_post(): void
    {
        $post = FeedPost::create([
            'organization_id' => $this->orgAlpha->id,
            'user_id' => $this->customerAlice->id,
            'content' => 'Bài viết tìm đối thủ giao lưu cầu lông tại sân Alpha.',
            'audience' => 'organization',
            'is_pinned' => false,
            'is_hidden' => false,
        ]);

        // Customer Bob attempts to delete Alice's post
        $deleteRes = $this->actingAs($this->customerBob)
            ->withHeaders(['X-Organization-Id' => (string) $this->orgAlpha->id])
            ->deleteJson("/api/v2/social/feed/{$post->id}");
        $deleteRes->assertStatus(403);
        $this->assertDatabaseHas('feed_posts', [
            'id' => $post->id,
            'is_hidden' => false,
        ]);

        // Customer Bob attempts to hide Alice's post
        $hideRes = $this->actingAs($this->customerBob)
            ->withHeaders(['X-Organization-Id' => (string) $this->orgAlpha->id])
            ->postJson("/api/v2/social/feed/{$post->id}/hide", [
                'reason' => 'Không thích bài viết này',
            ]);
        $hideRes->assertStatus(403);

        $this->assertFalse($post->fresh()->is_hidden, 'Post must remain visible.');
    }

    /**
     * Venue staff / admin can hide with reason; reasoned record is logged.
     */
    public function test_matrix_5_venue_staff_can_hide_with_reason_and_audit_record_is_logged(): void
    {
        $post = FeedPost::create([
            'organization_id' => $this->orgAlpha->id,
            'user_id' => $this->customerAlice->id,
            'content' => 'Nội dung quảng cáo cá độ trái phép không phù hợp.',
            'audience' => 'organization',
            'is_pinned' => false,
            'is_hidden' => false,
        ]);

        // Venue Manager Alpha hides the post with audited reason
        $hideRes = $this->actingAs($this->managerAlpha)
            ->withHeaders(['X-Organization-Id' => (string) $this->orgAlpha->id])
            ->postJson("/api/v2/social/feed/{$post->id}/hide", [
                'reason' => 'Nội dung vi phạm tiêu chuẩn cộng đồng sân bãi.',
            ]);
        $hideRes->assertOk();

        // Verify post state
        $freshPost = $post->fresh();
        $this->assertTrue($freshPost->is_hidden);
        $this->assertSame('Nội dung vi phạm tiêu chuẩn cộng đồng sân bãi.', $freshPost->hidden_reason);
        $this->assertSame($this->managerAlpha->id, $freshPost->hidden_by);
        $this->assertNotNull($freshPost->hidden_at);

        // Verify ModerationRecord is created
        $this->assertDatabaseHas('moderation_records', [
            'organization_id' => $this->orgAlpha->id,
            'actor_id' => $this->managerAlpha->id,
            'target_type' => FeedPost::class,
            'target_id' => $post->id,
            'action' => 'content_hidden',
            'reason' => 'Nội dung vi phạm tiêu chuẩn cộng đồng sân bãi.',
        ]);
    }

    // =========================================================================
    // MATRIX 6: Financial Reports & Data Export (D46, D47, D48)
    // =========================================================================

    /**
     * Owner can export reports for their own venues.
     */
    public function test_matrix_6_owner_can_export_reports_for_their_own_venues(): void
    {
        Booking::create([
            'court_id' => $this->courtAlpha->id,
            'user_id' => $this->customerAlice->id,
            'code' => 'BK-ALPHA-REV-100',
            'status' => 'confirmed',
            'total_amount' => 150000,
        ]);

        // 1. JSON report query for authorized venue
        $jsonRes = $this->actingAs($this->ownerAlpha)
            ->getJson("/api/v1/admin/reports?venue_id={$this->venueAlpha->id}");
        $jsonRes->assertOk();
        $jsonRes->assertJsonPath('booking_revenue', 150000);

        // 2. CSV report export for authorized venue (.csv route)
        $csvRes1 = $this->actingAs($this->ownerAlpha)
            ->get("/api/v1/admin/reports.csv?venue_id={$this->venueAlpha->id}");
        $csvRes1->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csvRes1->headers->get('Content-Type'));

        // 3. CSV report export (/csv route)
        $csvRes2 = $this->actingAs($this->ownerAlpha)
            ->get("/api/v1/admin/reports/csv?venue_id={$this->venueAlpha->id}");
        $csvRes2->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csvRes2->headers->get('Content-Type'));

        $streamed = $csvRes2->streamedContent();
        $lines = array_filter(explode("\n", trim($streamed)));
        $this->assertGreaterThanOrEqual(2, count($lines), 'CSV must contain at least header and one data row.');
        $this->assertStringContainsString('BK-ALPHA-REV-100', $streamed);
    }

    /**
     * Owner CANNOT export reports specifying a foreign venue_id -> 403.
     */
    public function test_matrix_6_owner_cannot_export_reports_specifying_foreign_venue(): void
    {
        $this->actingAs($this->ownerAlpha);

        // 1. JSON report query with foreign venue -> 403
        $this->getJson("/api/v1/admin/reports?venue_id={$this->venueBeta->id}")
            ->assertStatus(403);

        // 2. CSV export (.csv) with foreign venue -> 403
        $this->get("/api/v1/admin/reports.csv?venue_id={$this->venueBeta->id}")
            ->assertStatus(403);

        // 3. CSV export (/csv) with foreign venue -> 403
        $this->get("/api/v1/admin/reports/csv?venue_id={$this->venueBeta->id}")
            ->assertStatus(403);
    }

    /**
     * Export includes only records within authorized venue scope.
     */
    public function test_matrix_6_export_includes_only_records_within_authorized_venue_scope(): void
    {
        // Booking 1 in Venue Alpha (Org Alpha)
        $bookingAlpha = Booking::create([
            'court_id' => $this->courtAlpha->id,
            'user_id' => $this->customerAlice->id,
            'code' => 'BK-ALPHA-SCOPED-001',
            'status' => 'confirmed',
            'total_amount' => 200000,
        ]);

        // Booking 2 in Venue Beta (Org Beta)
        $bookingBeta = Booking::create([
            'court_id' => $this->courtBeta->id,
            'user_id' => $this->customerBob->id,
            'code' => 'BK-BETA-SCOPED-002',
            'status' => 'confirmed',
            'total_amount' => 350000,
        ]);

        // 1. Export with explicit venue_id = Venue Alpha
        $exportAlpha = $this->actingAs($this->ownerAlpha)
            ->get("/api/v1/admin/reports.csv?venue_id={$this->venueAlpha->id}");
        $exportAlpha->assertOk();
        $contentAlpha = $exportAlpha->streamedContent();

        $this->assertStringContainsString('BK-ALPHA-SCOPED-001', $contentAlpha);
        $this->assertStringNotContainsString('BK-BETA-SCOPED-002', $contentAlpha);

        // 2. Export without specifying venue_id: scoped to Owner Alpha's authorized venues
        $exportDefault = $this->actingAs($this->ownerAlpha)
            ->get('/api/v1/admin/reports.csv');
        $exportDefault->assertOk();
        $contentDefault = $exportDefault->streamedContent();

        $this->assertStringContainsString('BK-ALPHA-SCOPED-001', $contentDefault);
        $this->assertStringNotContainsString('BK-BETA-SCOPED-002', $contentDefault);
    }
}
