<?php

namespace Tests\Feature\Partner;

use App\Models\HostKyc;
use App\Models\Organization;
use App\Models\PartnerConsent;
use App\Models\PartnerDocument;
use App\Models\PartnerReviewHistory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerEvidenceAndOrganizationLinkageTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_persist_versioned_documents_consents_and_reviews(): void
    {
        $applicant = User::factory()->create(['email_verified_at' => now()]);
        $reviewer = User::factory()->create(['role' => 'admin']);

        $kyc = HostKyc::create([
            'kyc_code' => 'KYC-EVIDENCE-001',
            'user_id' => $applicant->id,
            'current_user_id' => $applicant->id,
            'business_type' => 'company',
            'business_name' => 'Badminton Pro Corp',
            'legal_name' => 'Badminton Pro Vietnam Co., Ltd',
            'tax_code' => '0312345678',
            'representative_name' => 'Tran Thi B',
            'id_card_number' => '079123456789',
            'bank_name' => 'Techcombank',
            'bank_code' => 'TCB',
            'bank_account_number' => '1903456789',
            'bank_account_holder' => 'BADMINTON PRO VIETNAM',
            'status' => 'draft',
            'version' => 1,
        ]);

        // Attach document
        $doc = PartnerDocument::create([
            'host_kyc_id' => $kyc->id,
            'document_type' => 'business_license',
            'file_path' => 'private/kyc/license-v1.pdf',
            'file_name' => 'business_license.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1048576,
            'file_hash' => hash('sha256', 'dummy'),
            'version' => 1,
            'status' => 'uploaded',
            'uploaded_by' => $applicant->id,
        ]);

        // Attach consent
        $consent = PartnerConsent::create([
            'host_kyc_id' => $kyc->id,
            'user_id' => $applicant->id,
            'consent_type' => 'platform_fee_policy',
            'terms_version' => '2026.1',
            'agreed_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Browser Agent',
        ]);

        // Record review history
        $review = PartnerReviewHistory::create([
            'host_kyc_id' => $kyc->id,
            'reviewer_id' => $reviewer->id,
            'decision' => 'revision_required',
            'reason' => 'Giấy phép kinh doanh bị mờ góc dưới.',
            'feedback_fields' => ['business_license' => 'Vui lòng scan lại bản gốc rõ nét.'],
            'reapplication_allowed' => true,
            'reapplication_cooloff_days' => 0,
            'application_version' => 1,
        ]);

        $this->assertCount(1, $kyc->fresh()->documents);
        $this->assertCount(1, $kyc->fresh()->consents);
        $this->assertCount(1, $kyc->fresh()->reviewHistories);

        $this->assertEquals('business_license', $kyc->documents->first()->document_type);
        $this->assertEquals('platform_fee_policy', $kyc->consents->first()->consent_type);
        $this->assertTrue($kyc->reviewHistories->first()->isRevisionRequired());
    }

    public function test_organization_partner_status_and_unique_source_kyc_linkage(): void
    {
        $applicant = User::factory()->create(['email_verified_at' => now()]);

        $kyc = HostKyc::create([
            'kyc_code' => 'KYC-ORG-001',
            'user_id' => $applicant->id,
            'business_type' => 'company',
            'business_name' => 'Sport Club A',
            'legal_name' => 'Sport Club A Co.',
            'tax_code' => '0398765432',
            'representative_name' => 'Le Van C',
            'id_card_number' => '079987654321',
            'bank_name' => 'MBBank',
            'bank_code' => 'MB',
            'bank_account_number' => '99998888',
            'bank_account_holder' => 'SPORT CLUB A',
            'status' => 'approved',
            'version' => 2,
        ]);

        $org = Organization::create([
            'name' => 'Sport Club A',
            'slug' => 'sport-club-a-' . substr(uniqid(), -5),
            'status' => 'active',
            'partner_status' => 'approved',
            'partner_status_changed_at' => now(),
            'source_kyc_id' => $kyc->id,
        ]);

        $this->assertTrue($org->isApprovedPartner());
        $this->assertEquals($kyc->id, $org->sourceKyc->id);
        $this->assertEquals($org->id, $kyc->fresh()->provisionedOrganization->id);

        // Attempting to create a second organization with the same source_kyc_id must fail unique constraint
        $this->expectException(QueryException::class);

        Organization::create([
            'name' => 'Sport Club A Duplicate',
            'slug' => 'sport-club-a-dup-' . substr(uniqid(), -5),
            'status' => 'active',
            'partner_status' => 'approved',
            'source_kyc_id' => $kyc->id,
        ]);
    }
}
