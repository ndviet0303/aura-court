<?php

namespace Tests\Feature\Auth;

use App\Models\HostKyc;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationAccessAndCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_applicant_cannot_access_owner_api_or_platform_admin(): void
    {
        $applicant = User::factory()->create([
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);

        HostKyc::create([
            'kyc_code' => 'KYC-PENDING-01',
            'user_id' => $applicant->id,
            'current_user_id' => $applicant->id,
            'business_type' => 'individual',
            'business_name' => 'Pending Arena',
            'legal_name' => 'Applicant Name',
            'tax_code' => '0101234567',
            'representative_name' => 'Applicant Name',
            'id_card_number' => '001099887766',
            'bank_name' => 'VCB',
            'bank_code' => 'VCB',
            'bank_account_number' => '112233',
            'bank_account_holder' => 'APPLICANT NAME',
            'status' => 'pending',
            'version' => 1,
        ]);

        Sanctum::actingAs($applicant);

        // auth/me exposes partner application status
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('capabilities.has_partner_application', true)
            ->assertJsonPath('capabilities.partner_application_status', 'pending');

        // Cannot access platform admin
        $this->getJson('/api/v1/admin/partner/applications')
            ->assertStatus(403);

        // Cannot access organization attendance/admin without membership
        $this->getJson('/api/v2/attendance/SOME_CODE')
            ->assertStatus(400); // Organization context required
    }
    public function test_approved_owner_has_customer_role_and_scoped_owner_capabilities(): void
    {
        $ownerUser = User::factory()->create([
            'role' => 'customer', // Preserved customer identity!
            'email_verified_at' => now(),
        ]);

        $org = Organization::create([
            'name' => 'My Badminton Club',
            'slug' => 'my-badminton-club',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $ownerUser->id,
            'role' => 'organization_owner',
            'status' => 'active',
        ]);

        Sanctum::actingAs($ownerUser);

        // 1. auth/me returns customer role and organization capabilities
        $meRes = $this->getJson('/api/v1/auth/me');
        $meRes->assertStatus(200)
            ->assertJsonPath('role', 'customer')
            ->assertJsonPath('capabilities.is_platform_admin', false)
            ->assertJsonPath('capabilities.can_manage_venues', true)
            ->assertJsonPath('capabilities.active_organization_id', $org->id)
            ->assertJsonPath('capabilities.partner_status', 'approved')
            ->assertJsonPath('organizations.0.organization_id', $org->id)
            ->assertJsonPath('organizations.0.role', 'organization_owner');

        // 2. Owner cannot access platform admin endpoints (e.g. KYC review)
        $this->getJson('/api/v1/admin/partner/applications')
            ->assertStatus(403);

        // 3. Owner can access their own organization endpoints in v2
        $showOrgRes = $this->withHeaders([
            'X-Organization-ID' => (string) $org->id,
        ])->getJson("/api/v2/organizations/{$org->id}");
        $showOrgRes->assertStatus(200);

        // 4. Cross-tenant negative case: Owner CANNOT access another tenant's organization
        $otherOrg = Organization::create([
            'name' => 'Other Tenant Club',
            'slug' => 'other-tenant-club',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        $crossTenantRes = $this->withHeaders([
            'X-Organization-ID' => (string) $otherOrg->id,
        ])->getJson("/api/v2/organizations/{$otherOrg->id}");

        $crossTenantRes->assertStatus(403);
    }
}
