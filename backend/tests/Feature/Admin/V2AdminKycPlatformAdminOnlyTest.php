<?php

namespace Tests\Feature\Admin;

use App\Models\HostKyc;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class V2AdminKycPlatformAdminOnlyTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private HostKyc $kyc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'KYC Pilot Org',
            'slug' => 'kyc-pilot-'.uniqid(),
            'status' => 'active',
        ]);

        // Pilot org with marketplace_payouts on: the old v2 group let its owners/managers through.
        config([
            'features.flags.marketplace_payouts' => true,
            'features.pilot_organizations' => [(string) $this->organization->id],
        ]);

        $applicant = User::factory()->create(['email_verified_at' => now()]);
        $this->kyc = HostKyc::create([
            'kyc_code' => 'KYC-V2-SCOPE-01',
            'user_id' => $applicant->id,
            'current_user_id' => $applicant->id,
            'business_type' => 'individual',
            'business_name' => 'Other Tenant Arena',
            'legal_name' => 'Other Applicant',
            'tax_code' => '0109998887',
            'representative_name' => 'Other Applicant',
            'id_card_number' => '001099555666',
            'bank_name' => 'VCB',
            'bank_code' => 'VCB',
            'bank_account_number' => '555666',
            'bank_account_holder' => 'OTHER APPLICANT',
            'contact_name' => 'Other Applicant',
            'contact_phone' => '0912555666',
            'contact_email' => 'other@test.vn',
            'address_line' => '1 Tran Phu',
            'city' => 'Hà Nội',
            'status' => 'pending',
            'version' => 1,
        ]);
    }

    public function test_organization_owner_and_venue_manager_are_forbidden(): void
    {
        foreach ([OrganizationMember::ROLE_ORGANIZATION_OWNER, OrganizationMember::ROLE_VENUE_MANAGER] as $role) {
            $member = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
            OrganizationMember::create([
                'organization_id' => $this->organization->id,
                'user_id' => $member->id,
                'role' => $role,
                'status' => 'active',
                'permissions' => ['*'],
            ]);

            Sanctum::actingAs($member);
            $headers = ['X-Organization-ID' => (string) $this->organization->id];

            $this->getJson('/api/v2/admin/kyc', $headers)->assertForbidden();
            $this->getJson('/api/v2/admin/kyc/stats', $headers)->assertForbidden();
            $this->getJson("/api/v2/admin/kyc/{$this->kyc->id}", $headers)->assertForbidden();
            $this->postJson("/api/v2/admin/kyc/{$this->kyc->id}/review", ['decision' => 'approve'], $headers)
                ->assertForbidden();
        }

        $this->assertDatabaseHas('host_kycs', ['id' => $this->kyc->id, 'status' => 'pending', 'reviewed_by' => null]);
        $this->assertDatabaseMissing('organizations', ['source_kyc_id' => $this->kyc->id]);
    }

    public function test_platform_admin_can_list_show_and_review_as_reviewer(): void
    {
        $admin = User::factory()->create(['role' => 'platform_admin']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v2/admin/kyc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->kyc->id);
        $this->getJson('/api/v2/admin/kyc/stats')->assertOk();
        $this->getJson("/api/v2/admin/kyc/{$this->kyc->id}")
            ->assertOk()
            ->assertJsonPath('data.kyc_code', 'KYC-V2-SCOPE-01');

        $this->postJson("/api/v2/admin/kyc/{$this->kyc->id}/review", [
            'decision' => 'approve',
            'reviewer_notes' => 'Đạt tiêu chuẩn.',
        ])->assertOk();

        $this->assertDatabaseHas('host_kycs', [
            'id' => $this->kyc->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);
    }
}
