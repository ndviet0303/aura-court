<?php

namespace Tests\Feature\Partner;

use App\Models\HostKyc;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OutboxMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Partner review rules of module 1: BR-12, BR-14, BR-15. */
class PartnerReviewRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function pendingApplication(array $attributes = []): HostKyc
    {
        $applicant = User::factory()->create();

        return HostKyc::create($attributes + [
            'kyc_code' => 'KYC-RULES-'.$applicant->id, 'user_id' => $applicant->id, 'current_user_id' => $applicant->id,
            'business_type' => 'company', 'business_name' => 'Rules Arena', 'legal_name' => 'Rules Arena Ltd',
            'tax_code' => '', 'representative_name' => 'Rules Rep', 'id_card_number' => '', 'bank_name' => '',
            'bank_code' => '', 'bank_account_number' => '', 'bank_account_holder' => '',
            'status' => 'pending', 'version' => 1,
        ]);
    }

    public function test_reasons_for_revision_or_rejection_are_ten_to_one_thousand_characters_on_both_endpoints(): void
    {
        $kyc = $this->pendingApplication();
        Sanctum::actingAs($this->admin);

        foreach ([null, 'Quá ngắn', str_repeat('a', 1001)] as $reason) {
            $this->postJson("/api/admin/partner/applications/{$kyc->id}/review", [
                'decision' => 'reject', 'reason' => $reason, 'expected_version' => 1,
            ])->assertStatus(422)->assertJsonValidationErrors(['reason']);

            $this->postJson("/api/admin/kyc/{$kyc->id}/review", [
                'decision' => 'request_supplement', 'rejection_reason' => $reason,
            ])->assertStatus(422)->assertJsonValidationErrors(['rejection_reason']);
        }

        $this->postJson("/api/admin/kyc/{$kyc->id}/review", [
            'decision' => 'reject', 'rejection_reason' => 'Giấy phép kinh doanh đã hết hiệu lực.',
        ])->assertOk();

        $kyc->refresh();
        $this->assertSame('rejected', $kyc->status);
        $this->assertSame('Giấy phép kinh doanh đã hết hiệu lực.', $kyc->rejection_reason);
    }

    public function test_the_applicant_is_notified_of_the_decision_with_its_reason(): void
    {
        $kyc = $this->pendingApplication();
        Sanctum::actingAs($this->admin);

        $this->postJson("/api/admin/partner/applications/{$kyc->id}/review", [
            'decision' => 'revision_required', 'reason' => 'Vui lòng bổ sung ảnh CCCD mặt sau.', 'expected_version' => 1,
        ])->assertOk();

        $payload = OutboxMessage::query()->where('message_key', "kyc.reviewed.{$kyc->id}.v2.revision_required")->firstOrFail()->payload;
        $this->assertStringContainsString('Vui lòng bổ sung ảnh CCCD mặt sau.', $payload['body']);
        $this->assertSame('/partner/application-status', $payload['action_url']);
    }

    public function test_an_application_whose_business_identity_belongs_to_an_organization_is_flagged_and_never_approved(): void
    {
        Organization::create(['name' => 'Registered', 'slug' => 'registered', 'tax_code' => '0312345678', 'status' => 'active', 'partner_status' => 'approved']);

        $applicant = User::factory()->create();
        Sanctum::actingAs($applicant);
        $id = $this->postJson('/api/partner/application/start')->assertCreated()->json('data.id');
        $this->putJson("/api/partner/application/{$id}", ['tax_code' => '0312345678'])->assertOk();
        $this->assertSame('flagged_duplicate', HostKyc::find($id)->investigation_status);

        $kyc = $this->pendingApplication(['tax_code' => '0312345678']);
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/partner/applications/{$kyc->id}/review", ['decision' => 'approve', 'expected_version' => 1])
            ->assertStatus(422);

        $this->assertSame('pending', $kyc->fresh()->status);
        $this->assertSame(1, Organization::where('tax_code', '0312345678')->count());
    }

    /** BR-13: one account owns at most one active organization, re-checked at approval time. */
    public function test_approval_refuses_an_applicant_who_already_owns_an_active_organization(): void
    {
        $kyc = $this->pendingApplication();
        $owned = Organization::create(['name' => 'Owned', 'slug' => 'owned', 'status' => 'active', 'partner_status' => 'approved']);
        OrganizationMember::create(['organization_id' => $owned->id, 'user_id' => $kyc->user_id, 'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER, 'status' => 'active']);
        Sanctum::actingAs($this->admin);
        $organizations = Organization::count();

        $this->postJson("/api/admin/partner/applications/{$kyc->id}/review", ['decision' => 'approve', 'expected_version' => 1])
            ->assertStatus(422)->assertJsonPath('message', 'Người nộp hồ sơ đã là chủ sở hữu một tổ chức đang hoạt động.');

        $this->assertSame('pending', $kyc->fresh()->status);
        $this->assertSame($organizations, Organization::count());
    }

    public function test_an_admin_clears_a_duplicate_flag_only_on_a_flagged_application(): void
    {
        $flagged = $this->pendingApplication(['investigation_status' => 'flagged_duplicate']);
        $clean = $this->pendingApplication(['investigation_status' => 'clean']);
        Sanctum::actingAs($this->admin);

        $this->postJson("/api/admin/partner/applications/{$flagged->id}/clear-investigation", ['notes' => 'Đã xác minh giấy phép gốc.'])
            ->assertOk()
            ->assertJsonPath('data.investigation_status', 'cleared');

        $this->postJson("/api/admin/partner/applications/{$clean->id}/clear-investigation")->assertStatus(409);
        $this->assertSame('clean', $clean->fresh()->investigation_status);
    }
}
