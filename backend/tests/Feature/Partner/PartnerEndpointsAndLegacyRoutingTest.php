<?php

namespace Tests\Feature\Partner;

use App\Models\HostKyc;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartnerEndpointsAndLegacyRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_applicant_endpoints_enforce_authentication_and_ownership(): void
    {
        $applicant1 = User::factory()->create(['email_verified_at' => now()]);
        $applicant2 = User::factory()->create(['email_verified_at' => now()]);

        $kyc = HostKyc::create([
            'kyc_code' => 'KYC-AUTH-01',
            'user_id' => $applicant1->id,
            'current_user_id' => $applicant1->id,
            'business_type' => 'individual',
            'business_name' => 'Applicant 1 Arena',
            'legal_name' => 'Applicant 1',
            'tax_code' => '0101111111',
            'representative_name' => 'Applicant 1',
            'id_card_number' => '001099111222',
            'bank_name' => 'VCB',
            'bank_code' => 'VCB',
            'bank_account_number' => '123456',
            'bank_account_holder' => 'APPLICANT 1',
            'status' => 'draft',
            'version' => 1,
        ]);

        // Unauthenticated request fails
        $this->getJson("/api/partner/application/{$kyc->id}")
            ->assertStatus(401);

        // Applicant 2 cannot access Applicant 1's application
        Sanctum::actingAs($applicant2);
        $this->getJson("/api/partner/application/{$kyc->id}")
            ->assertStatus(404);
        $this->getJson("/api/partner/application/{$kyc->id}/completeness")
            ->assertStatus(404);
        $this->getJson("/api/partner/application/{$kyc->id}/history")
            ->assertStatus(404);

        // Applicant 1 can access their own application, completeness, and history
        Sanctum::actingAs($applicant1);
        $this->getJson("/api/partner/application/{$kyc->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $kyc->id);

        $this->getJson("/api/partner/application/{$kyc->id}/completeness")
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['is_complete', 'missing_fields', 'missing_documents']]);

        $this->getJson("/api/partner/application/{$kyc->id}/history")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_admin_endpoints_require_admin_role(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        Sanctum::actingAs($customer);
        $this->getJson('/api/admin/partner/applications')
            ->assertStatus(403);
    }

    public function test_admin_can_list_view_and_review_applications(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create(['email_verified_at' => now()]);

        $kyc = HostKyc::create([
            'kyc_code' => 'KYC-ADMIN-01',
            'user_id' => $applicant->id,
            'current_user_id' => $applicant->id,
            'business_type' => 'individual',
            'business_name' => 'Admin Review Test Arena',
            'legal_name' => 'Applicant Name',
            'tax_code' => '0101112223',
            'representative_name' => 'Applicant Name',
            'id_card_number' => '001099887766',
            'bank_name' => 'VCB',
            'bank_code' => 'VCB',
            'bank_account_number' => '998877',
            'bank_account_holder' => 'APPLICANT NAME',
            'contact_name' => 'Applicant Name',
            'contact_phone' => '0912345678',
            'contact_email' => 'applicant@test.vn',
            'address_line' => '123 Cau Giay',
            'city' => 'Hà Nội',
            'status' => 'pending',
            'version' => 1,
        ]);

        Sanctum::actingAs($admin);

        // List
        $listRes = $this->getJson('/api/admin/partner/applications?status=pending');
        $listRes->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        // Show
        $showRes = $this->getJson("/api/admin/partner/applications/{$kyc->id}");
        $showRes->assertStatus(200)
            ->assertJsonPath('data.kyc_code', 'KYC-ADMIN-01');

        // Review approve
        $reviewRes = $this->postJson("/api/admin/partner/applications/{$kyc->id}/review", [
            'decision' => 'approve',
            'reason' => 'Đạt tiêu chuẩn xét duyệt đối tác.',
            'expected_version' => 1,
        ]);

        $reviewRes->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('organizations', [
            'name' => 'Admin Review Test Arena',
            'source_kyc_id' => $kyc->id,
            'partner_status' => 'approved',
        ]);
    }

    public function test_admin_can_manage_partner_lifecycle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $org = Organization::create([
            'name' => 'Lifecycle Test Club',
            'slug' => 'lifecycle-test-club',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        Sanctum::actingAs($admin);

        // Suspend
        $suspendRes = $this->postJson("/api/admin/partner/{$org->id}/suspend", [
            'reason' => 'Tạm ngừng để xác minh khiếu nại chất lượng sân.',
        ]);
        $suspendRes->assertStatus(200)
            ->assertJsonPath('data.partner_status', 'suspended');

        // Restore
        $restoreRes = $this->postJson("/api/admin/partner/{$org->id}/restore", [
            'notes' => 'Đã giải quyết xong khiếu nại.',
        ]);
        $restoreRes->assertStatus(200)
            ->assertJsonPath('data.partner_status', 'approved');

        // Terminate
        $termRes = $this->postJson("/api/admin/partner/{$org->id}/terminate", [
            'reason' => 'Chấm dứt hợp đồng.',
        ]);
        $termRes->assertStatus(200)
            ->assertJsonPath('data.partner_status', 'terminated');
    }

    public function test_legacy_kyc_review_endpoint_routes_through_new_use_cases(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create(['email_verified_at' => now()]);

        $kyc = HostKyc::create([
            'kyc_code' => 'KYC-LEGACY-01',
            'user_id' => $applicant->id,
            'current_user_id' => $applicant->id,
            'business_type' => 'company',
            'business_name' => 'Legacy Reviewed Club',
            'legal_name' => 'Legacy Company Ltd',
            'tax_code' => '0105556667',
            'representative_name' => 'Representative',
            'id_card_number' => '001099333444',
            'bank_name' => 'VCB',
            'bank_code' => 'VCB',
            'bank_account_number' => '333444',
            'bank_account_holder' => 'LEGACY COMPANY',
            'contact_name' => 'Representative',
            'contact_phone' => '0912333444',
            'contact_email' => 'contact@legacy.vn',
            'address_line' => '456 Le Duan',
            'city' => 'Đà Nẵng',
            'status' => 'pending',
            'version' => 1,
        ]);

        Sanctum::actingAs($admin);

        // Call the legacy endpoint /api/kyc/{id}/review or /api/admin/kyc/{id}/review
        $res = $this->postJson("/api/admin/kyc/{$kyc->id}/review", [
            'decision' => 'approve',
            'reviewer_notes' => 'Legacy admin review approval',
        ]);

        $res->assertStatus(200);

        // Verify that atomic provisioning took place through the use case!
        $this->assertDatabaseHas('organizations', [
            'name' => 'Legacy Reviewed Club',
            'source_kyc_id' => $kyc->id,
            'partner_status' => 'approved',
        ]);

        $org = Organization::where('source_kyc_id', $kyc->id)->first();
        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $org->id,
            'user_id' => $applicant->id,
            'role' => 'organization_owner',
        ]);
    }
}
