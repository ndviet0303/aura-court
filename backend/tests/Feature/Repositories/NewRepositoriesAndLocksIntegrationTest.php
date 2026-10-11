<?php

namespace Tests\Feature\Repositories;

use App\Contracts\Repositories\HostKycRepositoryInterface;
use App\Contracts\Repositories\OrganizationLockRepositoryInterface;
use App\Contracts\Repositories\PlatformPlanRepositoryInterface;
use App\Contracts\Repositories\PlatformSubscriptionRepositoryInterface;
use App\Contracts\Repositories\VenueLifecycleRepositoryInterface;
use App\Models\Organization;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NewRepositoriesAndLocksIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private HostKycRepositoryInterface $kycRepo;

    private PlatformPlanRepositoryInterface $planRepo;

    private PlatformSubscriptionRepositoryInterface $subRepo;

    private VenueLifecycleRepositoryInterface $venueRepo;

    private OrganizationLockRepositoryInterface $lockRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kycRepo = app(HostKycRepositoryInterface::class);
        $this->planRepo = app(PlatformPlanRepositoryInterface::class);
        $this->subRepo = app(PlatformSubscriptionRepositoryInterface::class);
        $this->venueRepo = app(VenueLifecycleRepositoryInterface::class);
        $this->lockRepo = app(OrganizationLockRepositoryInterface::class);
    }

    public function test_host_kyc_repository_crud_locking_and_sub_records(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $kyc = $this->kycRepo->create([
            'kyc_code' => 'KYC-REPO-001',
            'user_id' => $user->id,
            'current_user_id' => $user->id,
            'business_type' => 'individual',
            'business_name' => 'Badminton Test Center',
            'legal_name' => 'Nguyen Van Test',
            'tax_code' => '0987654321',
            'representative_name' => 'Nguyen Van Test',
            'id_card_number' => '001234567899',
            'bank_name' => 'Vietcombank',
            'bank_code' => 'VCB',
            'bank_account_number' => '123456',
            'bank_account_holder' => 'NGUYEN VAN TEST',
            'status' => 'draft',
            'version' => 1,
        ]);

        $this->assertNotNull($this->kycRepo->findByTaxCode('0987654321'));

        // Test lock query inside transaction
        DB::transaction(function () use ($user, $kyc) {
            $lockedKyc = $this->kycRepo->findCurrentByUserIdWithLock($user->id);
            $this->assertNotNull($lockedKyc);
            $this->assertEquals($kyc->id, $lockedKyc->id);

            $lockedById = $this->kycRepo->findByIdWithLock($kyc->id);
            $this->assertEquals($kyc->id, $lockedById->id);
        });

        // Test document attachment and retrieval
        $doc = $this->kycRepo->attachDocument($kyc, [
            'document_type' => 'id_card_front',
            'file_path' => 'private/docs/id1.jpg',
            'file_name' => 'id1.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 204800,
            'version' => 1,
            'status' => 'uploaded',
            'uploaded_by' => $user->id,
        ]);

        $this->assertNotNull($this->kycRepo->findDocument($doc->id));

        // Test consent recording
        $consent = $this->kycRepo->recordConsent($kyc, [
            'user_id' => $user->id,
            'consent_type' => 'terms_of_service',
            'terms_version' => '1.0',
            'agreed_at' => now(),
        ]);
        $this->assertEquals('terms_of_service', $consent->consent_type);

        // Test review recording
        $reviewer = User::factory()->create(['role' => 'admin']);
        $review = $this->kycRepo->recordReview($kyc, [
            'reviewer_id' => $reviewer->id,
            'decision' => 'approve',
            'reason' => 'Đạt chuẩn',
            'application_version' => 1,
        ]);
        $this->assertTrue($review->isApproval());

        // Test document deletion
        $this->assertTrue($this->kycRepo->deleteDocument($doc));
        $this->assertNull($this->kycRepo->findDocument($doc->id));
    }

    public function test_platform_plan_repository_operations(): void
    {
        $plan = $this->planRepo->create([
            'code' => 'plan_gold',
            'name' => 'Gold Plan',
            'price' => 1000000,
            'currency' => 'VND',
            'billing_cycle' => 'monthly',
            'trial_days' => 7,
            'trial_enabled' => true,
            'is_active' => true,
        ]);

        $this->assertEquals($plan->id, $this->planRepo->findById($plan->id)->id);
        $this->assertEquals($plan->id, $this->planRepo->findByCode('plan_gold')->id);
        $this->assertCount(1, $this->planRepo->listActive());
    }

    public function test_platform_subscription_repository_operations_and_locks(): void
    {
        $org = Organization::create([
            'name' => 'Subscription Test Org',
            'slug' => 'sub-test-org',
            'status' => 'active',
        ]);

        $plan = $this->planRepo->create([
            'code' => 'plan_silver',
            'name' => 'Silver Plan',
            'price' => 300000,
            'currency' => 'VND',
        ]);

        $sub = $this->subRepo->create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'plan_version' => 1,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        DB::transaction(function () use ($org, $sub) {
            $locked = $this->subRepo->findActiveForOrganizationWithLock($org->id);
            $this->assertNotNull($locked);
            $this->assertEquals($sub->id, $locked->id);

            $lockedById = $this->subRepo->findByIdWithLock($sub->id);
            $this->assertEquals($sub->id, $lockedById->id);
        });

        // Payment attempt & receipt
        $attempt = $this->subRepo->createPaymentAttempt([
            'subscription_id' => $sub->id,
            'organization_id' => $org->id,
            'reference_code' => 'ATTEMPT-001',
            'amount' => 300000,
            'currency' => 'VND',
            'provider' => 'vnpay',
            'status' => 'succeeded',
        ]);

        $this->assertNotNull($this->subRepo->findPaymentAttemptByReference('ATTEMPT-001'));

        $receipt = $this->subRepo->recordReceipt([
            'payment_attempt_id' => $attempt->id,
            'provider' => 'vnpay',
            'provider_transaction_id' => 'VNPAY-TRANS-12345',
            'amount' => 300000,
            'currency' => 'VND',
            'raw_payload' => ['success' => true],
            'verified_at' => now(),
        ]);

        $this->assertNotNull($this->subRepo->findReceiptByTransactionId('vnpay', 'VNPAY-TRANS-12345'));
    }

    public function test_venue_lifecycle_repository_operations(): void
    {
        $org = Organization::create([
            'name' => 'Venue Lifecycle Org',
            'slug' => 'venue-lifecycle-org',
            'status' => 'active',
        ]);

        $owner = User::factory()->create();

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Court Arena 1',
            'slug' => 'court-arena-1',
            'address' => '100 Dien Bien Phu',
            'status' => 'draft',
        ]);

        // Status update
        $updatedVenue = $this->venueRepo->updateStatus($venue, 'pending_publication', 'Đã nộp hồ sơ xuất bản');
        $this->assertTrue($updatedVenue->isPendingPublication());
        $this->assertEquals('Đã nộp hồ sơ xuất bản', $updatedVenue->status_reason);

        // Publication revision
        $revision = $this->venueRepo->createPublicationRevision($venue, [
            'revision_number' => 1,
            'status' => 'pending',
            'configuration_snapshot' => ['courts' => 4],
            'submitted_by' => $owner->id,
            'submitted_at' => now(),
        ]);

        $this->assertEquals(1, $this->venueRepo->getLatestRevision($venue)->revision_number);
        $this->assertCount(1, $this->venueRepo->paginatePendingPublication(10)->items());

        // Update revision with lock in transaction
        DB::transaction(function () use ($revision) {
            $lockedRev = $this->venueRepo->findPublicationRevisionWithLock($revision->id);
            $this->assertNotNull($lockedRev);

            $this->venueRepo->updatePublicationRevision($lockedRev, [
                'status' => 'approved',
                'decision_reason' => 'Đạt chất lượng',
            ]);
        });

        $this->assertTrue($this->venueRepo->findPublicationRevision($revision->id)->isApproved());
    }

    public function test_organization_lock_repository_locks(): void
    {
        $user = User::factory()->create();
        $org = Organization::create([
            'name' => 'Lock Test Org',
            'slug' => 'lock-test-org',
            'status' => 'active',
        ]);

        DB::transaction(function () use ($user, $org) {
            $lockedUser = $this->lockRepo->lockUserForUpdate($user->id);
            $this->assertNotNull($lockedUser);
            $this->assertEquals($user->id, $lockedUser->id);

            $lockedOrg = $this->lockRepo->lockOrganizationForUpdate($org->id);
            $this->assertNotNull($lockedOrg);
            $this->assertEquals($org->id, $lockedOrg->id);

            $lockedOrgWithMembers = $this->lockRepo->lockOrganizationWithMembersForUpdate($org->id);
            $this->assertNotNull($lockedOrgWithMembers);
        });
    }
}
