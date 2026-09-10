<?php

namespace Tests\Feature\Partner;

use App\Models\HostKyc;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartnerApplicationSubmissionAndLockingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function validConsents(): array
    {
        return [
            ['consent_type' => 'cooperation_terms', 'terms_version' => '2026.1'],
            ['consent_type' => 'platform_fee_policy', 'terms_version' => '2026.1'],
            ['consent_type' => 'settlement_refund_terms', 'terms_version' => '2026.1'],
            ['consent_type' => 'data_processing_agreement', 'terms_version' => '2026.1'],
            ['consent_type' => 'service_responsibility', 'terms_version' => '2026.1'],
        ];
    }

    public function test_cannot_submit_incomplete_application(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($user);

        $startRes = $this->postJson('/api/v1/partner/application/start', []);
        $appId = $startRes->json('data.id');

        $submitRes = $this->postJson("/api/v1/partner/application/{$appId}/submit", [
            'expected_version' => 1,
            'consents' => $this->validConsents(),
        ]);

        $submitRes->assertStatus(422)
            ->assertJsonValidationErrors(['business_name', 'id_card_number', 'representative_id_front']);
    }

    public function test_cannot_submit_without_all_required_consents(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($user);

        $startRes = $this->postJson('/api/v1/partner/application/start', []);
        $appId = $startRes->json('data.id');

        $incompleteConsents = [
            ['consent_type' => 'cooperation_terms', 'terms_version' => '2026.1'],
        ];

        $submitRes = $this->postJson("/api/v1/partner/application/{$appId}/submit", [
            'expected_version' => 1,
            'consents' => $incompleteConsents,
        ]);

        $submitRes->assertStatus(422)
            ->assertJsonValidationErrors(['consents']);
    }

    public function test_complete_submission_snapshots_data_and_locks_mutations(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'name' => 'Tran Thi B']);
        Sanctum::actingAs($user);

        $startRes = $this->postJson('/api/v1/partner/application/start', []);
        $appId = $startRes->json('data.id');

        // Populate all business details for individual
        $this->putJson("/api/v1/partner/application/{$appId}", [
            'business_type' => 'individual',
            'business_name' => 'Sân Cầu Lông Hoa Lư',
            'legal_name' => 'Tran Thi B',
            'representative_name' => 'Tran Thi B',
            'id_card_number' => '079123456789',
            'bank_name' => 'VCB',
            'bank_code' => 'VCB',
            'bank_account_number' => '007100123456',
            'bank_account_holder' => 'TRAN THI B',
            'contact_name' => 'Tran Thi B',
            'contact_phone' => '0909123456',
            'contact_email' => 'hoalu@badminton.vn',
            'address_line' => 'Số 2 Đinh Tiên Hoàng',
            'ward' => 'Đa Kao',
            'district' => 'Quận 1',
            'city' => 'Hồ Chí Minh',
            'sports' => ['badminton'],
            'court_scale' => 4,
            'expected_version' => 1,
        ])->assertStatus(200);

        // Upload required documents for individual: id_front, id_back, bank confirmation, venue rights
        $requiredDocs = [
            'representative_id_front' => 'id_front.jpg',
            'representative_id_back' => 'id_back.jpg',
            'bank_account_confirmation' => 'bank.pdf',
            'venue_operation_rights' => 'lease.pdf',
        ];

        foreach ($requiredDocs as $type => $name) {
            $mime = str_ends_with($name, '.pdf') ? 'application/pdf' : 'image/jpeg';
            $file = UploadedFile::fake()->create($name, 200, $mime);
            $this->postJson("/api/v1/partner/application/{$appId}/documents", [
                'document_type' => $type,
                'file' => $file,
            ])->assertStatus(201);
        }

        // Current application version after 1 save + 4 document uploads = 1 + 1 + 4 = 6
        $kyc = HostKyc::find($appId);
        $currentVersion = $kyc->version;

        // Submit application
        $submitRes = $this->postJson("/api/v1/partner/application/{$appId}/submit", [
            'expected_version' => $currentVersion,
            'consents' => $this->validConsents(),
        ]);

        $submitRes->assertStatus(200)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.version', $currentVersion + 1);

        $freshKyc = HostKyc::find($appId);
        $this->assertTrue($freshKyc->isPending());
        $this->assertNotNull($freshKyc->submission_snapshot);
        $this->assertEquals('Sân Cầu Lông Hoa Lư', $freshKyc->submission_snapshot['business_name']);
        $this->assertCount(4, $freshKyc->submission_snapshot['documents']);
        $this->assertCount(5, $freshKyc->consents);

        // Test Mutation Locking: Cannot modify application while in pending status
        $blockedSave = $this->putJson("/api/v1/partner/application/{$appId}", [
            'business_name' => 'Tên Mới Sau Khi Nộp',
            'expected_version' => $freshKyc->version,
        ]);
        $blockedSave->assertStatus(409);

        // Cannot attach/replace documents while in pending status
        $blockedDoc = $this->postJson("/api/v1/partner/application/{$appId}/documents", [
            'document_type' => 'representative_id_front',
            'file' => UploadedFile::fake()->create('hack.jpg', 100, 'image/jpeg'),
        ]);
        $blockedDoc->assertStatus(409);
    }
}
