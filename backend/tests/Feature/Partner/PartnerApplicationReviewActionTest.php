<?php

namespace Tests\Feature\Partner;

use App\Domain\Partner\Actions\ReviewPartnerApplication;
use App\Domain\Partner\DTOs\ReviewPartnerApplicationData;
use App\Models\HostKyc;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PartnerApplicationReviewActionTest extends TestCase
{
    use RefreshDatabase;

    private ReviewPartnerApplication $reviewAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reviewAction = app(ReviewPartnerApplication::class);
    }

    public function test_reviewer_requests_corrections_with_mandatory_reason_and_history(): void
    {
        $applicant = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->create(['role' => 'admin']);

        $kyc = HostKyc::create([
            'kyc_code' => 'KYC-REVIEW-01',
            'user_id' => $applicant->id,
            'current_user_id' => $applicant->id,
            'business_type' => 'individual',
            'business_name' => 'Court Review Test',
            'legal_name' => 'Applicant Name',
            'tax_code' => '1234567890',
            'representative_name' => 'Applicant Name',
            'id_card_number' => '079123456789',
            'bank_name' => 'VCB',
            'bank_code' => 'VCB',
            'bank_account_number' => '111222',
            'bank_account_holder' => 'APPLICANT NAME',
            'status' => 'pending',
            'version' => 3,
        ]);

        $dto = new ReviewPartnerApplicationData(
            reviewerId: $admin->id,
            kycId: $kyc->id,
            decision: 'revision_required',
            reason: 'Hình chụp CCCD mặt sau bị lóa sáng, không đọc được số.',
            reviewerNotes: 'Đã kiểm tra hồ sơ, các mục khác hợp lệ.',
            feedbackFields: ['representative_id_back' => 'Vui lòng tải lại ảnh CCCD mặt sau rõ nét.'],
            expectedVersion: 3
        );

        $result = $this->reviewAction->execute($dto);

        $this->assertEquals('revision_required', $result->status);
        $this->assertEquals(4, $result->version);
        $this->assertEquals($applicant->id, $result->current_user_id); // Slot retained
        $this->assertCount(1, $result->reviewHistories);
        $this->assertEquals('revision_required', $result->reviewHistories->first()->decision);

        // Applicant can now save updates because status is revision_required
        Sanctum::actingAs($applicant);
        $saveRes = $this->putJson("/api/partner/application/{$kyc->id}", [
            'business_name' => 'Court Review Test Updated',
            'expected_version' => 4,
        ]);
        $saveRes->assertStatus(200);
    }

    public function test_a_rejected_applicant_can_start_a_new_application_at_once(): void
    {
        $applicant = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->create(['role' => 'admin']);

        $kyc = HostKyc::create([
            'kyc_code' => 'KYC-REJECT-01',
            'user_id' => $applicant->id,
            'current_user_id' => $applicant->id,
            'business_type' => 'individual',
            'business_name' => 'Fake Venue',
            'legal_name' => 'Fake Applicant',
            'tax_code' => '1234567891',
            'representative_name' => 'Fake Applicant',
            'id_card_number' => '079123456780',
            'bank_name' => 'VCB',
            'bank_code' => 'VCB',
            'bank_account_number' => '111223',
            'bank_account_holder' => 'FAKE APPLICANT',
            'status' => 'pending',
            'version' => 2,
        ]);

        $dto = new ReviewPartnerApplicationData(
            reviewerId: $admin->id,
            kycId: $kyc->id,
            decision: 'reject',
            reason: 'Phát hiện giấy phép kinh doanh giả mạo.',
            expectedVersion: 2
        );

        $result = $this->reviewAction->execute($dto);

        $this->assertEquals('rejected', $result->status);
        $this->assertNull($result->current_user_id); // Slot released!
        $this->assertFalse($result->isCurrent());

        // BR-14: the rejected application is kept, the status page still shows it,
        // and the applicant may start a new application at once.
        Sanctum::actingAs($applicant);
        $this->getJson('/api/partner/application/current')
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $startRes = $this->postJson('/api/partner/application/start', []);
        $startRes->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');

        $newApp = HostKyc::find($startRes->json('data.id'));
        $this->assertEquals($kyc->id, $newApp->parent_kyc_id);
        $this->assertSame('rejected', $kyc->fresh()->status);
    }

    public function test_stale_expected_version_or_invalid_state_returns_conflict(): void
    {
        $applicant = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->create(['role' => 'admin']);

        $kyc = HostKyc::create([
            'kyc_code' => 'KYC-STALE-01',
            'user_id' => $applicant->id,
            'current_user_id' => $applicant->id,
            'business_type' => 'individual',
            'business_name' => 'Draft Venue',
            'legal_name' => 'Name',
            'tax_code' => '1234567892',
            'representative_name' => 'Name',
            'id_card_number' => '079123456782',
            'bank_name' => 'VCB',
            'bank_code' => 'VCB',
            'bank_account_number' => '111224',
            'bank_account_holder' => 'NAME',
            'status' => 'draft', // Not pending!
            'version' => 1,
        ]);

        $dto = new ReviewPartnerApplicationData(
            reviewerId: $admin->id,
            kycId: $kyc->id,
            decision: 'revision_required',
            reason: 'Test reason',
            expectedVersion: 1
        );

        $this->expectException(HttpException::class);
        $this->reviewAction->execute($dto);
    }
}
