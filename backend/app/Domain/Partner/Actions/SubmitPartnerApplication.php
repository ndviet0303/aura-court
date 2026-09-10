<?php

namespace App\Domain\Partner\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\HostKycRepositoryInterface;
use App\Domain\Partner\DTOs\SubmitPartnerApplicationData;
use App\Domain\Partner\Rules\PartnerCompletenessPolicy;
use App\Models\HostKyc;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitPartnerApplication
{
    public const REQUIRED_CONSENT_TYPES = [
        'cooperation_terms',
        'platform_fee_policy',
        'settlement_refund_terms',
        'data_processing_agreement',
        'service_responsibility',
    ];

    public function __construct(
        private readonly HostKycRepositoryInterface $kycRepo,
        private readonly DurableNotificationServiceInterface $notif,
    ) {}

    public function execute(SubmitPartnerApplicationData $data): HostKyc
    {
        return DB::transaction(function () use ($data) {
            $kyc = $this->kycRepo->findByIdWithLock($data->kycId);

            if (! $kyc || $kyc->user_id !== $data->userId) {
                abort(403, 'Bạn không có quyền gửi duyệt hồ sơ này.');
            }

            if (! $kyc->isDraft() && ! $kyc->isRevisionRequired()) {
                abort(409, 'Hồ sơ đang trong quá trình xét duyệt hoặc đã có kết quả.');
            }

            if ($kyc->version !== $data->expectedVersion) {
                abort(409, 'Dữ liệu hồ sơ đã thay đổi từ phiên làm việc khác. Vui lòng tải lại trang.');
            }

            if ($kyc->investigation_status === 'flagged_duplicate') {
                throw ValidationException::withMessages([
                    'investigation' => 'Hồ sơ đang trong quá trình xác minh thông tin đăng ký trùng lặp. Vui lòng chờ bộ phận hỗ trợ liên hệ.',
                ]);
            }

            // Completeness evaluation
            $completeness = PartnerCompletenessPolicy::evaluate($kyc);
            if (! $completeness['is_complete']) {
                $messages = [];
                foreach ($completeness['missing_fields'] as $field) {
                    $messages[$field] = "Trường [{$field}] là bắt buộc trước khi nộp hồ sơ.";
                }
                foreach ($completeness['missing_documents'] as $docType) {
                    $messages[$docType] = "Tài liệu [{$docType}] là bắt buộc đối với loại hình kinh doanh này.";
                }

                throw ValidationException::withMessages($messages);
            }

            // Verify all required consents are present
            $providedTypes = array_column($data->consents, 'consent_type');
            $missingConsents = array_diff(self::REQUIRED_CONSENT_TYPES, $providedTypes);
            if (! empty($missingConsents)) {
                throw ValidationException::withMessages([
                    'consents' => 'Vui lòng đồng ý đầy đủ các điều khoản và chính sách bắt buộc: '.implode(', ', $missingConsents),
                ]);
            }

            // Record consents
            foreach ($data->consents as $consent) {
                $this->kycRepo->recordConsent($kyc, [
                    'user_id' => $data->userId,
                    'consent_type' => $consent['consent_type'],
                    'terms_version' => $consent['terms_version'],
                    'agreed_at' => now(),
                    'ip_address' => $data->ipAddress,
                    'user_agent' => $data->userAgent,
                ]);
            }

            // Create immutable submission snapshot
            $documentsSnapshot = $kyc->documents()
                ->whereIn('status', ['uploaded', 'verified'])
                ->get()
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'document_type' => $d->document_type,
                    'file_path' => $d->file_path,
                    'file_name' => $d->file_name,
                    'version' => $d->version,
                ])
                ->all();

            $snapshot = [
                'business_type' => $kyc->business_type,
                'business_name' => $kyc->business_name,
                'legal_name' => $kyc->legal_name,
                'tax_code' => $kyc->tax_code,
                'tax_status' => $kyc->tax_status,
                'representative_name' => $kyc->representative_name,
                'id_card_number' => $kyc->id_card_number,
                'bank_name' => $kyc->bank_name,
                'bank_code' => $kyc->bank_code,
                'bank_account_number' => $kyc->bank_account_number,
                'bank_account_holder' => $kyc->bank_account_holder,
                'contact_name' => $kyc->contact_name,
                'contact_phone' => $kyc->contact_phone,
                'contact_email' => $kyc->contact_email,
                'address_line' => $kyc->address_line,
                'ward' => $kyc->ward,
                'district' => $kyc->district,
                'city' => $kyc->city,
                'postal_code' => $kyc->postal_code,
                'sports' => $kyc->sports,
                'court_scale' => $kyc->court_scale,
                'lighting_spec' => $kyc->lighting_spec,
                'documents' => $documentsSnapshot,
                'submitted_at' => now()->toIso8601String(),
                'version' => $kyc->version,
            ];

            $updated = $this->kycRepo->update($kyc, [
                'status' => 'pending',
                'submitted_at' => now(),
                'submission_snapshot' => $snapshot,
                'submitted_version' => $kyc->version,
                'version' => $kyc->version + 1,
            ]);

            // Enqueue durable receipt notification
            $this->notif->notifyApplicationSubmitted($updated);

            // Record audit log
            $this->notif->recordAudit(
                userId: $data->userId,
                action: 'kyc.submitted',
                auditable: $updated,
                metadata: [
                    'kyc_code' => $updated->kyc_code,
                    'submitted_version' => $kyc->version,
                ]
            );

            return $updated;
        });
    }
}
