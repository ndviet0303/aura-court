<?php

namespace Tests\Feature\Partner;

use App\Domain\Partner\Actions\StartPartnerApplication;
use App\Domain\Partner\Actions\SubmitPartnerApplication;
use App\Domain\Partner\DTOs\StartPartnerApplicationData;
use App\Domain\Partner\DTOs\SubmitPartnerApplicationData;
use App\Models\HostKyc;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartnerPhoneVerificationEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_merely_entered_without_verification_evidence_cannot_start_partner_application(): void
    {
        $applicant = User::factory()->create([
            'email_verified_at' => null,
            'phone' => '0901234567',
            'phone_verified_at' => null,
            'status' => 'active',
        ]);

        Sanctum::actingAs($applicant);

        $response = $this->postJson('/api/v1/partner/application/start', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user']);
        $this->assertStringContainsString('bằng chứng xác thực', $response->json('errors.user.0'));
    }

    public function test_direct_action_call_with_unverified_phone_and_email_throws_validation_exception(): void
    {
        $applicant = User::factory()->create([
            'email_verified_at' => null,
            'phone' => '0987654321',
            'phone_verified_at' => null,
            'status' => 'active',
        ]);

        $action = app(StartPartnerApplication::class);

        $this->expectException(ValidationException::class);
        $action->execute(new StartPartnerApplicationData(userId: $applicant->id));
    }

    public function test_applicant_with_phone_verification_evidence_can_start_application(): void
    {
        $applicant = User::factory()->create([
            'email_verified_at' => null,
            'phone' => '0901234567',
            'phone_verified_at' => now(),
            'status' => 'active',
        ]);

        Sanctum::actingAs($applicant);

        $response = $this->postJson('/api/v1/partner/application/start', []);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.is_current', true);

        $this->assertSame(1, HostKyc::query()->where('user_id', $applicant->id)->count());
    }

    public function test_applicant_with_verified_email_can_start_application(): void
    {
        $applicant = User::factory()->create([
            'email_verified_at' => now(),
            'phone' => '0901234567',
            'phone_verified_at' => null,
            'status' => 'active',
        ]);

        Sanctum::actingAs($applicant);

        $response = $this->postJson('/api/v1/partner/application/start', []);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_submission_fails_if_applicant_has_neither_verified_email_nor_verified_phone(): void
    {
        $applicant = User::factory()->create([
            'email_verified_at' => now(),
            'phone' => '0901234567',
            'phone_verified_at' => null,
            'status' => 'active',
        ]);

        Sanctum::actingAs($applicant);
        $startRes = $this->postJson('/api/v1/partner/application/start', []);
        $startRes->assertStatus(201);
        $kycId = $startRes->json('data.id');

        // Revoke email verification before submission
        $applicant->update([
            'email_verified_at' => null,
            'phone_verified_at' => null,
        ]);

        $action = app(SubmitPartnerApplication::class);

        $this->expectException(ValidationException::class);
        $action->execute(new SubmitPartnerApplicationData(
            kycId: $kycId,
            userId: $applicant->id,
            expectedVersion: 1,
            consents: [
                ['consent_type' => 'data_processing_agreement', 'version' => '1.0'],
                ['consent_type' => 'service_responsibility', 'version' => '1.0'],
            ]
        ));
    }
}
