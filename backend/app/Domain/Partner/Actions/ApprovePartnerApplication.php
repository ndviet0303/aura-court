<?php

namespace App\Domain\Partner\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\HostKycRepositoryInterface;
use App\Contracts\Repositories\OrganizationLockRepositoryInterface;
use App\Domain\Partner\DTOs\ReviewPartnerApplicationData;
use App\Models\HostKyc;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApprovePartnerApplication
{
    private const DUPLICATE_TAX_CODE = 'Mã định danh kinh doanh đã thuộc về một tổ chức đã đăng ký. Không thể phê duyệt hồ sơ trùng tổ chức.';

    public function __construct(
        private readonly HostKycRepositoryInterface $kycRepo,
        private readonly OrganizationLockRepositoryInterface $orgLocks,
        private readonly DurableNotificationServiceInterface $notif,
    ) {}

    public function execute(ReviewPartnerApplicationData $data): HostKyc
    {
        return DB::transaction(function () use ($data) {
            $kyc = $this->kycRepo->findByIdWithLock($data->kycId);
            if (! $kyc) {
                abort(404, 'Hồ sơ đối tác không tồn tại.');
            }
            // BR-15: a reviewer never decides their own application.
            abort_if((int) $kyc->user_id === (int) $data->reviewerId, 403, 'Không thể tự xét duyệt hồ sơ đối tác của chính mình.');

            // Idempotent retry: if already approved, return recorded result without duplicate provisioning
            if ($kyc->isApproved()) {
                return $kyc;
            }

            // Optimistic concurrency check
            if ($kyc->version !== $data->expectedVersion) {
                abort(409, 'Dữ liệu hồ sơ đã thay đổi từ một phiên làm việc khác. Vui lòng tải lại trang.');
            }

            if (! $kyc->isPending()) {
                abort(409, 'Chỉ có thể phê duyệt hồ sơ đang chờ xét duyệt (pending).');
            }

            // Check if organization was already provisioned for this KYC
            $existingOrg = $this->orgLocks->findBySourceKycId($kyc->id);
            if ($existingOrg) {
                return $kyc;
            }

            // BR-13: one account owns one organization; the applicant may have become an owner since applying.
            $this->orgLocks->lockUserForUpdate((int) $kyc->user_id);
            abort_if($this->orgLocks->ownsActiveOrganization((int) $kyc->user_id), 422, 'Người nộp hồ sơ đã là chủ sở hữu một tổ chức đang hoạt động.');

            // BR-15: the business identity must not belong to a registered organization.
            if (trim((string) $kyc->tax_code) !== '' && $this->orgLocks->findByTaxCode((string) $kyc->tax_code)) {
                abort(422, self::DUPLICATE_TAX_CODE);
            }

            // Generate organization slug
            $baseName = $kyc->business_name ?: $kyc->legal_name ?: 'club';
            $slugBase = Str::slug($baseName);
            if (empty($slugBase)) {
                $slugBase = 'club';
            }
            $slug = $slugBase.'-'.substr(uniqid(), -5);

            // 1. Atomically provision Organization; the unique tax code index settles a concurrent approval.
            try {
                $org = $this->orgLocks->createOrganization([
                    'name' => $kyc->business_name ?: $kyc->legal_name,
                    'slug' => $slug,
                    'tax_code' => $kyc->tax_code,
                    'status' => 'active',
                    'partner_status' => 'approved',
                    'partner_status_changed_at' => now(),
                    'source_kyc_id' => $kyc->id,
                    'settings' => [
                        'phone' => $kyc->contact_phone,
                        'address' => $kyc->address_line.($kyc->city ? ', '.$kyc->city : ''),
                        'contact_name' => $kyc->contact_name,
                        'sports' => $kyc->sports,
                        'court_scale' => $kyc->court_scale,
                    ],
                ]);
            } catch (UniqueConstraintViolationException $e) {
                abort_unless(str_contains($e->getMessage(), 'organizations_tax_code_unique'), 500, $e->getMessage());
                abort(422, self::DUPLICATE_TAX_CODE);
            }

            // 2. Provision owner membership (user's personal role remains customer!)
            $this->orgLocks->addMember($org, (int) $kyc->user_id, 'organization_owner', 'active');

            // 3. Update KYC application record
            $payload = [
                'status' => 'approved',
                'organization_id' => $org->id,
                'current_user_id' => null, // Release unique active application slot
                'reviewed_by' => $data->reviewerId,
                'reviewed_at' => now(),
                'reviewer_notes' => $data->reviewerNotes,
                'version' => $kyc->version + 1,
            ];

            // 4. Record review history
            $this->kycRepo->recordReview($kyc, [
                'reviewer_id' => $data->reviewerId,
                'decision' => 'approve',
                'reason' => $data->reason ?: 'Hồ sơ đạt yêu cầu phê duyệt đối tác.',
                'reviewer_notes' => $data->reviewerNotes,
                'feedback_fields' => $data->feedbackFields,
                'reapplication_allowed' => true,
                'reapplication_cooloff_days' => 0,
                'application_version' => $kyc->version,
            ]);

            $updated = $this->kycRepo->update($kyc, $payload);

            // 5. Durable notification & audit log
            $this->notif->notifyApplicationReviewed($updated, 'approve', $data->reason);
            $this->notif->recordAudit(
                userId: $data->reviewerId,
                action: 'kyc.reviewed.approved',
                auditable: $updated,
                metadata: [
                    'organization_id' => $org->id,
                    'organization_name' => $org->name,
                    'owner_user_id' => $kyc->user_id,
                ]
            );

            return $updated;
        });
    }
}
