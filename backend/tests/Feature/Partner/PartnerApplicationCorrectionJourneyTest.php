<?php

namespace Tests\Feature\Partner;

use App\Models\HostKyc;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartnerApplicationCorrectionJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_applicant_can_follow_real_routes_through_a_requested_correction(): void
    {
        Storage::fake('local');

        $applicant = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($applicant);

        $this->getJson('/api/v1/partner/application/current')
            ->assertOk()
            ->assertJsonPath('data', null);

        $applicationId = $this->postJson('/api/v1/partner/application/start', [])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->json('data.id');

        $applicationPath = "/api/v1/partner/application/{$applicationId}";
        $this->putJson($applicationPath, [
            'business_type' => 'individual',
            'business_name' => 'Hoa Lu Sports Club',
            'legal_name' => 'Nguyen Van A',
            'representative_name' => 'Nguyen Van A',
            'id_card_number' => '079123456789',
            'bank_name' => 'VCB',
            'bank_code' => 'VCB',
            'bank_account_number' => '007100123456',
            'bank_account_holder' => 'NGUYEN VAN A',
            'contact_name' => 'Nguyen Van A',
            'contact_phone' => '0909123456',
            'contact_email' => 'hoa-lu@example.test',
            'address_line' => '2 Dinh Tien Hoang',
            'city' => 'Ho Chi Minh City',
            'sports' => ['badminton'],
            'expected_version' => 1,
        ])->assertOk();

        $firstUpload = null;
        foreach ([
            'representative_id_front',
            'representative_id_back',
            'bank_account_confirmation',
            'venue_operation_rights',
        ] as $documentType) {
            $uploaded = $this->postJson("{$applicationPath}/documents", [
                'document_type' => $documentType,
                'file' => UploadedFile::fake()->image("{$documentType}.jpg"),
            ])->assertCreated()->assertJsonStructure(['data' => [
                'id', 'document_type', 'file_name', 'mime_type', 'file_size', 'version',
                'status', 'read_url', 'created_at', 'application_version',
            ]]);
            $firstUpload ??= $uploaded;
        }

        $readUrl = $firstUpload->json('data.read_url');
        $parts = parse_url($readUrl);
        $this->get($parts['path'].'?'.$parts['query'])->assertOk();

        $application = HostKyc::findOrFail($applicationId);
        $consents = array_map(fn (string $type) => [
            'consent_type' => $type,
            'terms_version' => '2026.1',
        ], [
            'cooperation_terms',
            'platform_fee_policy',
            'settlement_refund_terms',
            'data_processing_agreement',
            'service_responsibility',
        ]);

        $this->postJson("{$applicationPath}/submit", [
            'expected_version' => $application->version,
            'consents' => $consents,
        ])->assertOk()->assertJsonPath('data.status', 'pending');

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/partner/applications/{$applicationId}/review", [
            'decision' => 'revision_required',
            'reason' => 'Please clarify the venue address.',
            'expected_version' => $application->version + 1,
        ])->assertOk()->assertJsonPath('data.status', 'revision_required');

        Sanctum::actingAs($applicant);
        $current = $this->getJson('/api/v1/partner/application/current')
            ->assertOk()
            ->assertJsonPath('data.status', 'revision_required')
            ->json('data');
        $this->assertSame('079123456789', $current['id_card_number']);
        $this->assertSame('007100123456', $current['bank_account_number']);

        $this->putJson($applicationPath, [
            'business_name' => 'Hoa Lu Sports Club Updated',
            'expected_version' => $current['version'],
        ])->assertOk();

        $updated = HostKyc::findOrFail($applicationId);
        $this->postJson("{$applicationPath}/submit", [
            'expected_version' => $updated->version,
            'consents' => $consents,
        ])->assertOk()->assertJsonPath('data.status', 'pending');

        $this->getJson("{$applicationPath}/history")
            ->assertOk()
            ->assertJsonPath('data.0.decision', 'revision_required');
    }
}
