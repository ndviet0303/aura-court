<?php

namespace Tests\Feature\Module01;

use App\Models\HostKyc;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AppliesAsPartner;
use Tests\Concerns\BuildsVenues;
use Tests\TestCase;

/** FR-ACC-11, FR-ACC-12, BR-ACC-09, BR-PAR-03, BR-PAR-04: platform review and owner provisioning. */
class PartnerReviewTest extends TestCase
{
    use AppliesAsPartner;
    use BuildsVenues;
    use RefreshDatabase;

    public function test_fr_acc_11_only_a_platform_admin_reviews(): void
    {
        $kyc = $this->submittedApplication();
        $owner = $this->ownerOf($this->openVenue()->organization);

        $this->reviewApplication($kyc, 'approve', reviewer: $owner)->assertForbidden();
        $this->reviewApplication($kyc, 'approve', reviewer: $kyc->user)->assertForbidden();
        $this->actingAs($owner)->getJson('/api/admin/partner/applications')->assertForbidden();
        $this->assertSame('pending', $kyc->fresh()->status);
    }

    public function test_fr_acc_11_a_platform_admin_lists_and_opens_applications_with_documents(): void
    {
        $kyc = $this->submittedApplication();
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->getJson('/api/admin/partner/applications?status=pending')->assertOk()->assertJsonPath('meta.total', 1);
        $this->actingAs($admin)->getJson("/api/admin/partner/applications/{$kyc->id}")->assertOk()
            ->assertJsonCount(2, 'data.documents')->assertJsonCount(5, 'data.consents');
    }

    public function test_fr_acc_12_br_par_03_br_par_04_approval_grants_ownership_and_keeps_customer_rights(): void
    {
        $kyc = $this->submittedApplication($applicant = User::factory()->create());

        $this->reviewApplication($kyc, 'approve')->assertOk()->assertJsonPath('data.status', 'approved');

        $organization = Organization::where('source_kyc_id', $kyc->id)->sole();
        $this->assertSame('approved', $organization->partner_status);
        $this->assertTrue($applicant->isOrganizationOwner($organization->id));
        $this->assertSame('customer', $applicant->fresh()->role);
        $this->assertFalse($applicant->fresh()->isPlatformAdmin());

        $this->actingAs($applicant)->getJson('/api/auth/me')->assertJsonPath('capabilities.can_manage_venues', true)
            ->assertJsonPath('capabilities.is_platform_admin', false);
        $this->actingAs($applicant)->getJson("/api/partner/organizations/{$organization->id}/venues")->assertOk();
        // Customer functions stay available to the new owner.
        $this->actingAs($applicant)->getJson('/api/me/reservations')->assertOk();
        $this->actingAs($applicant)->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_fr_acc_12_an_approval_retry_provisions_only_once(): void
    {
        $kyc = $this->submittedApplication();
        $admin = $this->platformAdmin();
        $version = $kyc->version;

        $this->reviewApplication($kyc, 'approve', reviewer: $admin)->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/partner/applications/{$kyc->id}/review", ['decision' => 'approve', 'expected_version' => $version])->assertOk();

        $this->assertSame(1, Organization::where('source_kyc_id', $kyc->id)->count());
        $this->assertSame(1, OrganizationMember::where('user_id', $kyc->user_id)->count());
    }

    public function test_fr_acc_11_reject_and_revision_need_a_reason(): void
    {
        $kyc = $this->submittedApplication();
        $admin = $this->platformAdmin();

        foreach (['reject', 'revision_required'] as $decision) {
            $this->actingAs($admin)->postJson("/api/admin/partner/applications/{$kyc->id}/review", [
                'decision' => $decision, 'expected_version' => $kyc->version,
            ])->assertJsonValidationErrors('reason');
        }
        $this->assertSame('pending', $kyc->fresh()->status);
    }

    public function test_fr_acc_11_revision_loop_returns_the_application_to_pending(): void
    {
        $kyc = $this->submittedApplication();
        $this->reviewApplication($kyc, 'revision_required')->assertOk();

        $this->actingAs($kyc->user)->putJson("/api/partner/application/{$kyc->id}", ['business_name' => 'CLB Ánh Dương Mới'])->assertOk();
        $this->submitApplication($kyc)->assertOk()->assertJsonPath('data.status', 'pending');

        $this->assertSame('CLB Ánh Dương Mới', $kyc->fresh()->submission_snapshot['business_name']);
        $this->assertFalse(OrganizationMember::where('user_id', $kyc->user_id)->exists());
    }

    public function test_fr_acc_11_rejection_keeps_history_and_allows_a_new_linked_application(): void
    {
        $kyc = $this->submittedApplication($applicant = User::factory()->create());
        $this->reviewApplication($kyc, 'reject')->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->assertFalse(OrganizationMember::where('user_id', $applicant->id)->exists());
        $this->actingAs($applicant)->getJson('/api/partner/application/current')->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Thiếu giấy tờ chứng minh quyền sử dụng sân.');

        $next = $this->startApplication($applicant);
        $this->assertNotSame($kyc->id, $next->id);
        $this->assertSame($kyc->id, $next->parent_kyc_id);
        $this->assertSame('rejected', $kyc->fresh()->status);
    }

    public function test_br_par_03_an_existing_owner_cannot_start_another_application(): void
    {
        $owner = $this->ownerOf($this->openVenue()->organization);

        $this->actingAs($owner)->postJson('/api/partner/application/start')->assertJsonValidationErrors('user');
        $this->assertSame(0, HostKyc::count());
    }

    public function test_fr_acc_11_admin_kyc_path_shares_the_same_rules(): void
    {
        $kyc = $this->submittedApplication();

        $this->actingAs($this->platformAdmin())->postJson("/api/admin/kyc/{$kyc->id}/review", ['decision' => 'approve'])->assertOk();

        $this->assertSame(1, Organization::where('source_kyc_id', $kyc->id)->count());
        $this->assertSame('customer', $kyc->user->fresh()->role);
    }
}
