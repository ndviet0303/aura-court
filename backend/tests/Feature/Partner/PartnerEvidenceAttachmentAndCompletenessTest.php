<?php

namespace Tests\Feature\Partner;

use App\Domain\Partner\Actions\ClearDuplicateIdentityInvestigation;
use App\Domain\Partner\Rules\PartnerCompletenessPolicy;
use App\Models\HostKyc;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartnerEvidenceAttachmentAndCompletenessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_can_attach_and_replace_documents_with_versioning(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($user);

        $startRes = $this->postJson('/api/partner/application/start', []);
        $appId = $startRes->json('data.id');

        // First upload
        $file1 = UploadedFile::fake()->create('id_front_v1.jpg', 300, 'image/jpeg');
        $upload1 = $this->postJson("/api/partner/application/{$appId}/documents", [
            'document_type' => 'representative_id_front',
            'file' => $file1,
        ]);

        $upload1->assertStatus(201)
            ->assertJsonPath('data.document_type', 'representative_id_front')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.status', 'uploaded');

        $docId1 = $upload1->json('data.id');

        // Second upload of same document type replaces the first
        $file2 = UploadedFile::fake()->create('id_front_v2.png', 400, 'image/png');
        $upload2 = $this->postJson("/api/partner/application/{$appId}/documents", [
            'document_type' => 'representative_id_front',
            'file' => $file2,
        ]);

        $upload2->assertStatus(201)
            ->assertJsonPath('data.document_type', 'representative_id_front')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.status', 'uploaded');

        $docId2 = $upload2->json('data.id');

        // Verify older document is marked replaced in database
        $this->assertDatabaseHas('partner_documents', [
            'id' => $docId1,
            'status' => 'replaced',
            'version' => 1,
        ]);

        $this->assertDatabaseHas('partner_documents', [
            'id' => $docId2,
            'status' => 'uploaded',
            'version' => 2,
        ]);
    }

    public function test_completeness_policy_evaluates_business_types_and_authorization(): void
    {
        $user = User::factory()->create([
            'name' => 'Nguyen Van Contact',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $startRes = $this->postJson('/api/partner/application/start', []);
        $appId = $startRes->json('data.id');

        // Initial draft is incomplete
        $check1 = $this->getJson("/api/partner/application/{$appId}/completeness");
        $check1->assertStatus(200)
            ->assertJsonPath('data.is_complete', false);
        $this->assertContains('representative_id_front', $check1->json('data.missing_documents'));

        // Populate business details where applicant is NOT the legal representative
        $this->putJson("/api/partner/application/{$appId}", [
            'business_type' => 'company',
            'business_name' => 'Arena Corp',
            'legal_name' => 'Arena Company Ltd',
            'tax_code' => '0311223344',
            'representative_name' => 'Tran Van Rep', // Different from applicant name!
            'id_card_number' => '079123456789',
            'bank_name' => 'VCB',
            'bank_code' => 'VCB',
            'bank_account_number' => '123456789',
            'bank_account_holder' => 'ARENA COMPANY LTD',
            'contact_name' => 'Nguyen Van Contact',
            'contact_phone' => '0911223344',
            'contact_email' => 'contact@arena.vn',
            'address_line' => '123 Tran Hung Dao',
            'city' => 'Ho Chi Minh',
            'sports' => ['badminton'],
        ])->assertStatus(200);

        $kyc = HostKyc::find($appId);
        $eval = PartnerCompletenessPolicy::evaluate($kyc);

        // For company with different representative, power_of_attorney and business_license are required
        $this->assertContains('power_of_attorney', $eval['required_documents']);
        $this->assertContains('business_license', $eval['required_documents']);
        $this->assertFalse($eval['is_complete']);
    }

    public function test_duplicate_tax_code_flags_investigation_and_admin_clears_it(): void
    {
        // Existing application with tax code
        $existingUser = User::factory()->create(['email_verified_at' => now()]);
        HostKyc::create([
            'kyc_code' => 'KYC-EXISTING-01',
            'user_id' => $existingUser->id,
            'business_type' => 'company',
            'business_name' => 'Alpha Sport',
            'legal_name' => 'Alpha Sport Ltd',
            'tax_code' => '0399998888',
            'representative_name' => 'Alpha',
            'id_card_number' => '079999999999',
            'bank_name' => 'VCB',
            'bank_code' => 'VCB',
            'bank_account_number' => '999',
            'bank_account_holder' => 'ALPHA',
            'status' => 'pending',
        ]);

        // New applicant declares the identical tax code
        $newUser = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($newUser);

        $startRes = $this->postJson('/api/partner/application/start', []);
        $newAppId = $startRes->json('data.id');

        $this->putJson("/api/partner/application/{$newAppId}", [
            'business_name' => 'Beta Sport',
            'tax_code' => '0399998888', // Duplicate!
        ])->assertStatus(200);

        $kyc = HostKyc::find($newAppId);
        $this->assertEquals('flagged_duplicate', $kyc->investigation_status);

        // Platform reviewer / admin clears the investigation after verification
        $admin = User::factory()->create(['role' => 'admin']);
        $clearAction = app(ClearDuplicateIdentityInvestigation::class);

        $clearedKyc = $clearAction->execute(
            kycId: $kyc->id,
            reviewerId: $admin->id,
            clearanceNote: 'Đã xác minh giấy phép kinh doanh gốc hợp lệ.'
        );

        $this->assertEquals('cleared', $clearedKyc->investigation_status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'kyc.duplicate_investigation.cleared',
            'user_id' => $admin->id,
            'auditable_id' => $kyc->id,
        ]);
    }
}
