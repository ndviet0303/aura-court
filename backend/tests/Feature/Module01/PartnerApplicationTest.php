<?php

namespace Tests\Feature\Module01;

use App\Models\HostKyc;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\AppliesAsPartner;
use Tests\TestCase;

/** FR-ACC-08, FR-ACC-09, FR-ACC-10, BR-PAR-01, BR-PAR-02, BR-ACC-09: the applicant's side of partner registration. */
class PartnerApplicationTest extends TestCase
{
    use AppliesAsPartner;
    use RefreshDatabase;

    public function test_br_par_01_only_a_signed_in_verified_customer_starts_an_application(): void
    {
        $this->postJson('/api/partner/application/start')->assertUnauthorized();
        $this->actingAs(User::factory()->unverified()->create())->postJson('/api/partner/application/start')
            ->assertJsonValidationErrors('user');
        $this->assertSame(0, HostKyc::count());
    }

    public function test_alt_par_04_starting_twice_resumes_the_same_draft(): void
    {
        $applicant = User::factory()->create();
        $first = $this->startApplication($applicant);

        $this->actingAs($applicant)->postJson('/api/partner/application/start')->assertCreated()->assertJsonPath('data.id', $first->id);
        $this->assertSame(1, HostKyc::count());
    }

    public function test_fr_acc_08_a_draft_is_saved_and_resumed_with_its_missing_requirements(): void
    {
        $kyc = $this->startApplication($applicant = User::factory()->create());

        $this->actingAs($applicant)->putJson("/api/partner/application/{$kyc->id}", ['business_name' => 'CLB Ánh Dương', 'business_type' => 'company'])->assertOk();

        $this->actingAs($applicant)->getJson('/api/partner/application/current')->assertOk()
            ->assertJsonPath('data.business_name', 'CLB Ánh Dương')->assertJsonPath('data.status', 'draft');
        $completeness = $this->actingAs($applicant)->getJson("/api/partner/application/{$kyc->id}/completeness")->assertOk()->json('data');
        $this->assertFalse($completeness['is_complete']);
        $this->assertContains('address_line', $completeness['missing_fields']);
        $this->assertContains('business_license', $completeness['missing_documents']);
    }

    public function test_alt_par_02_an_incomplete_application_cannot_be_submitted(): void
    {
        $kyc = $this->startApplication(User::factory()->create());

        $this->submitApplication($kyc)->assertUnprocessable()->assertJsonValidationErrors(['business_name', 'representative_id_front']);
        $this->assertSame('draft', $kyc->fresh()->status);
    }

    public function test_fr_acc_08_submission_needs_every_required_consent(): void
    {
        $kyc = $this->completeApplication($this->startApplication(User::factory()->create()));

        $this->actingAs($kyc->user)->postJson("/api/partner/application/{$kyc->id}/submit", [
            'expected_version' => $kyc->version,
            'consents' => [['consent_type' => 'cooperation_terms', 'terms_version' => 'v1']],
        ])->assertUnprocessable();
        $this->assertSame('draft', $kyc->fresh()->status);
    }

    public function test_br_par_02_br_acc_09_submission_queues_review_without_granting_ownership(): void
    {
        $applicant = User::factory()->create();
        $kyc = $this->submittedApplication($applicant);

        $this->assertNotNull($kyc->submitted_at);
        $this->assertNotNull($kyc->submission_snapshot);
        $this->assertSame('customer', $applicant->fresh()->role);
        $this->assertFalse(OrganizationMember::where('user_id', $applicant->id)->exists());
        $this->actingAs($applicant)->getJson('/api/auth/me')->assertJsonPath('capabilities.can_manage_venues', false)
            ->assertJsonPath('capabilities.partner_application_status', 'pending');
    }

    public function test_fr_acc_08_a_pending_application_cannot_be_edited(): void
    {
        $kyc = $this->submittedApplication();

        $this->actingAs($kyc->user)->putJson("/api/partner/application/{$kyc->id}", ['business_name' => 'Đổi tên'])->assertConflict();
        $this->attachDocument($kyc, 'business_license')->assertConflict();
        $this->assertSame('CLB Cầu Lông Ánh Dương', $kyc->fresh()->business_name);
    }

    public function test_fr_acc_08_submission_needs_a_complete_personal_profile(): void
    {
        $applicant = User::factory()->create(['phone' => null]);
        $kyc = $this->completeApplication($this->startApplication($applicant));

        $this->submitApplication($kyc)->assertJsonValidationErrors('profile');
    }

    public function test_fr_acc_09_documents_are_replaced_by_type_and_private_to_the_applicant(): void
    {
        $kyc = $this->startApplication($applicant = User::factory()->create());
        $this->attachDocument($kyc, 'representative_id_front')->assertCreated()->assertJsonPath('data.version', 1);
        $this->attachDocument($kyc, 'representative_id_front')->assertCreated()->assertJsonPath('data.version', 2);

        $docs = $kyc->documents()->orderBy('version')->pluck('status')->all();
        $this->assertSame(['replaced', 'uploaded'], $docs);

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->getJson("/api/partner/application/{$kyc->id}")->assertNotFound();
        $this->actingAs($stranger)->post("/api/partner/application/{$kyc->id}/documents", [
            'document_type' => 'business_license', 'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_fr_acc_09_document_type_size_and_format_are_validated(): void
    {
        $kyc = $this->startApplication(User::factory()->create());

        $this->actingAs($kyc->user)->post("/api/partner/application/{$kyc->id}/documents", [
            'document_type' => 'selfie', 'file' => UploadedFile::fake()->create('x.exe', 20000, 'application/x-msdownload'),
        ], ['Accept' => 'application/json'])->assertJsonValidationErrors(['document_type', 'file']);
        $this->assertSame(0, $kyc->documents()->count());
    }

    public function test_fr_acc_10_the_applicant_follows_status_reasons_and_history(): void
    {
        $kyc = $this->submittedApplication();
        $this->reviewApplication($kyc, 'revision_required', 'Ảnh CCCD mặt sau bị mờ, vui lòng chụp lại.')->assertOk();

        $this->actingAs($kyc->user)->getJson('/api/partner/application/current')->assertOk()
            ->assertJsonPath('data.status', 'revision_required')
            ->assertJsonPath('data.rejection_reason', 'Ảnh CCCD mặt sau bị mờ, vui lòng chụp lại.');
        $this->actingAs($kyc->user)->getJson("/api/partner/application/{$kyc->id}/history")->assertOk()
            ->assertJsonPath('data.0.decision', 'revision_required');
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $kyc->user_id]);
    }
}
