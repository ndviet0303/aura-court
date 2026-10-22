<?php

namespace Tests\Concerns;

use App\Domain\Partner\Actions\SubmitPartnerApplication;
use App\Models\HostKyc;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/**
 * Partner applications driven through the real API (module 01). Fakes the private document disk.
 *
 *     $kyc = $this->submittedApplication($applicant);   // start, fill, attach ID documents, submit → pending
 *     $this->reviewApplication($kyc, 'approve');        // as a new platform admin
 */
trait AppliesAsPartner
{
    protected function startApplication(User $applicant): HostKyc
    {
        Storage::fake((string) config('filesystems.private_disk', 'local'));
        $id = $this->actingAs($applicant)->postJson('/api/partner/application/start')->assertCreated()->json('data.id');

        return HostKyc::findOrFail($id);
    }

    /** @param  array<string, mixed>  $overrides */
    protected function completeApplication(HostKyc $kyc, array $overrides = []): HostKyc
    {
        $this->actingAs($kyc->user)->putJson("/api/partner/application/{$kyc->id}", $overrides + [
            'business_type' => 'individual',
            'business_name' => 'CLB Cầu Lông Ánh Dương',
            'representative_name' => 'Nguyễn Văn A',
            'contact_phone' => '0912345678',
            'contact_email' => 'lienhe@example.com',
            'address_line' => '12 Lê Lợi',
            'city' => 'Hồ Chí Minh',
            'tax_code' => 'TAX-'.$kyc->id,
            'sports' => ['badminton'],
        ])->assertOk();
        foreach (['representative_id_front', 'representative_id_back'] as $type) {
            $this->attachDocument($kyc, $type)->assertCreated();
        }

        return $kyc->fresh();
    }

    protected function attachDocument(HostKyc $kyc, string $type): TestResponse
    {
        return $this->actingAs($kyc->user)->post("/api/partner/application/{$kyc->id}/documents", [
            'document_type' => $type,
            'file' => UploadedFile::fake()->create("{$type}.pdf", 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);
    }

    protected function submitApplication(HostKyc $kyc): TestResponse
    {
        $kyc->refresh();

        return $this->actingAs($kyc->user)->postJson("/api/partner/application/{$kyc->id}/submit", [
            'expected_version' => $kyc->version,
            'consents' => array_map(fn ($type) => ['consent_type' => $type, 'terms_version' => 'v1'], SubmitPartnerApplication::REQUIRED_CONSENT_TYPES),
        ]);
    }

    protected function submittedApplication(?User $applicant = null): HostKyc
    {
        $kyc = $this->completeApplication($this->startApplication($applicant ?? User::factory()->create()));
        $this->submitApplication($kyc)->assertOk()->assertJsonPath('data.status', 'pending');

        return $kyc->fresh();
    }

    protected function reviewApplication(HostKyc $kyc, string $decision, ?string $reason = null, ?User $reviewer = null): TestResponse
    {
        return $this->actingAs($reviewer ?? $this->platformAdmin())->postJson("/api/admin/partner/applications/{$kyc->id}/review", [
            'decision' => $decision,
            'reason' => $reason ?? ($decision === 'approve' ? null : 'Thiếu giấy tờ chứng minh quyền sử dụng sân.'),
            'expected_version' => $kyc->fresh()->version,
        ]);
    }
}
