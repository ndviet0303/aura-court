<?php

namespace Tests\Feature\Partner;

use App\Domain\Partner\Actions\ApprovePartnerApplication;
use App\Domain\Partner\DTOs\ReviewPartnerApplicationData;
use App\Models\HostKyc;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PartnerApplicationApprovalAndProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private ApprovePartnerApplication $approveAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->approveAction = app(ApprovePartnerApplication::class);
    }

    public function test_atomic_approval_provisions_organization_and_owner_grant(): void
    {
        $applicant = User::factory()->create([
            'role' => 'customer',
            'email_verified_at' => now(),
            'name' => 'Nguyen Van Owner',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $kyc = HostKyc::create([
            'kyc_code' => 'KYC-APPROVE-01',
            'user_id' => $applicant->id,
            'current_user_id' => $applicant->id,
            'business_type' => 'company',
            'business_name' => 'Thang Long Badminton Arena',
            'legal_name' => 'Thang Long Sports JSC',
            'tax_code' => '0101234567',
            'representative_name' => 'Nguyen Van Owner',
            'id_card_number' => '001099123456',
            'bank_name' => 'VCB',
            'bank_code' => 'VCB',
            'bank_account_number' => '001100998877',
            'bank_account_holder' => 'THANG LONG SPORTS JSC',
            'contact_name' => 'Nguyen Van Owner',
            'contact_phone' => '0988123456',
            'contact_email' => 'owner@thanglong.vn',
            'address_line' => '100 Hoang Quoc Viet',
            'city' => 'Hà Nội',
            'sports' => ['badminton'],
            'court_scale' => 8,
            'status' => 'pending',
            'version' => 5,
        ]);

        $dto = new ReviewPartnerApplicationData(
            reviewerId: $admin->id,
            kycId: $kyc->id,
            decision: 'approve',
            reason: 'Đạt đầy đủ tiêu chuẩn kinh doanh sân thể thao.',
            reviewerNotes: 'Hồ sơ đầy đủ tính pháp lý.',
            expectedVersion: 5
        );

        $result = $this->approveAction->execute($dto);

        // 1. Assert HostKyc is approved and slot released
        $this->assertEquals('approved', $result->status);
        $this->assertNull($result->current_user_id);
        $this->assertNotNull($result->organization_id);

        // 2. Assert Organization is created and linked
        $org = Organization::find($result->organization_id);
        $this->assertNotNull($org);
        $this->assertEquals('Thang Long Badminton Arena', $org->name);
        $this->assertEquals('approved', $org->partner_status);
        $this->assertEquals($kyc->id, $org->source_kyc_id);

        // 3. Assert OrganizationMember is created with organization_owner role
        $member = OrganizationMember::where('organization_id', $org->id)
            ->where('user_id', $applicant->id)
            ->first();
        $this->assertNotNull($member);
        $this->assertEquals('organization_owner', $member->role);

        // 4. Assert User's personal role remains customer (NOT elevated to global admin)
        $this->assertEquals('customer', $applicant->fresh()->role);

        // 5. Assert review history recorded
        $this->assertCount(1, $result->reviewHistories);
        $this->assertEquals('approve', $result->reviewHistories->first()->decision);
    }

    public function test_approval_retry_is_idempotent(): void
    {
        $applicant = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->create(['role' => 'admin']);

        $kyc = HostKyc::create([
            'kyc_code' => 'KYC-RETRY-01',
            'user_id' => $applicant->id,
            'current_user_id' => $applicant->id,
            'business_type' => 'individual',
            'business_name' => 'Single Arena',
            'legal_name' => 'Single Arena',
            'tax_code' => '0109999999',
            'representative_name' => 'Applicant',
            'id_card_number' => '001099000000',
            'bank_name' => 'VCB',
            'bank_code' => 'VCB',
            'bank_account_number' => '0000',
            'bank_account_holder' => 'APPLICANT',
            'status' => 'pending',
            'version' => 1,
        ]);

        $dto = new ReviewPartnerApplicationData(
            reviewerId: $admin->id,
            kycId: $kyc->id,
            decision: 'approve',
            expectedVersion: 1
        );

        // First execution
        $res1 = $this->approveAction->execute($dto);
        $orgId = $res1->organization_id;

        // Second execution (retry)
        $res2 = $this->approveAction->execute($dto);

        // Must return identical organization without duplicate records
        $this->assertEquals($orgId, $res2->organization_id);
        $this->assertEquals(1, Organization::where('source_kyc_id', $kyc->id)->count());
        $this->assertEquals(1, OrganizationMember::where('organization_id', $orgId)->count());
    }

    public function test_conflicting_reviewer_with_stale_version_fails(): void
    {
        $applicant = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->create(['role' => 'admin']);

        $kyc = HostKyc::create([
            'kyc_code' => 'KYC-CONFLICT-01',
            'user_id' => $applicant->id,
            'current_user_id' => $applicant->id,
            'business_type' => 'individual',
            'business_name' => 'Conflict Arena',
            'legal_name' => 'Name',
            'tax_code' => '0108888888',
            'representative_name' => 'Name',
            'id_card_number' => '001088888888',
            'bank_name' => 'VCB',
            'bank_code' => 'VCB',
            'bank_account_number' => '8888',
            'bank_account_holder' => 'NAME',
            'status' => 'pending',
            'version' => 2,
        ]);

        $dtoStale = new ReviewPartnerApplicationData(
            reviewerId: $admin->id,
            kycId: $kyc->id,
            decision: 'approve',
            expectedVersion: 1 // Stale!
        );

        $this->expectException(HttpException::class);
        $this->approveAction->execute($dtoStale);
    }
}
