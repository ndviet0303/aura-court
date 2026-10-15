<?php

namespace App\Domain\Partner\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\HostKycRepositoryInterface;
use App\Domain\Partner\DTOs\ReviewPartnerApplicationData;
use App\Models\HostKyc;
use Illuminate\Support\Facades\DB;

class ReviewPartnerApplication
{
    public function __construct(
        private readonly HostKycRepositoryInterface $kycRepo,
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

            // Optimistic concurrency check
            if ($kyc->version !== $data->expectedVersion) {
                // Idempotency: If decision was already applied identically, return current record
                if ($kyc->status === $data->decision && $kyc->rejection_reason === $data->reason) {
                    return $kyc;
                }
                abort(409, 'Dữ liệu hồ sơ đã thay đổi từ một phiên làm việc khác. Vui lòng tải lại trang.');
            }

            if (! $kyc->isPending()) {
                if ($kyc->status === $data->decision) {
                    return $kyc;
                }
                abort(409, 'Chỉ có thể xét duyệt hồ sơ đang ở trạng thái chờ duyệt (pending).');
            }

            if ($data->decision === 'revision_required') {
                return $this->handleRevisionRequired($kyc, $data);
            }

            if ($data->decision === 'reject') {
                return $this->handleReject($kyc, $data);
            }

            if ($data->decision === 'approve') {
                /** @var ApprovePartnerApplication $approveAction */
                $approveAction = app(ApprovePartnerApplication::class);

                return $approveAction->execute($data);
            }

            abort(422, "Quyết định xét duyệt không hợp lệ [{$data->decision}].");
        });
    }

    private function handleRevisionRequired(HostKyc $kyc, ReviewPartnerApplicationData $data): HostKyc
    {
        $payload = [
            'status' => 'revision_required',
            'rejection_reason' => $data->reason,
            'reviewer_notes' => $data->reviewerNotes,
            'reviewed_by' => $data->reviewerId,
            'reviewed_at' => now(),
            'version' => $kyc->version + 1,
        ];

        // Record immutable review history
        $this->kycRepo->recordReview($kyc, [
            'reviewer_id' => $data->reviewerId,
            'decision' => 'revision_required',
            'reason' => $data->reason,
            'reviewer_notes' => $data->reviewerNotes,
            'feedback_fields' => $data->feedbackFields,
            'reapplication_allowed' => true,
            'reapplication_cooloff_days' => 0,
            'application_version' => $kyc->version,
        ]);

        $updated = $this->kycRepo->update($kyc, $payload);

        // Durable notification and audit log
        $this->notif->notifyApplicationReviewed($updated, 'revision_required', $data->reason);
        $this->notif->recordAudit(
            userId: $data->reviewerId,
            action: 'kyc.reviewed.revision_required',
            auditable: $updated,
            metadata: ['reason' => $data->reason, 'notes' => $data->reviewerNotes]
        );

        return $updated;
    }

    private function handleReject(HostKyc $kyc, ReviewPartnerApplicationData $data): HostKyc
    {
        $eligibleAt = $data->reapplicationCooloffDays > 0
            ? now()->addDays($data->reapplicationCooloffDays)->toIso8601String()
            : now()->toIso8601String();

        $policy = [
            'allowed' => $data->reapplicationAllowed,
            'cooloff_days' => $data->reapplicationCooloffDays,
            'eligible_at' => $eligibleAt,
        ];

        $payload = [
            'status' => 'rejected',
            'current_user_id' => null, // Release unique active application slot
            'rejection_reason' => $data->reason,
            'reviewer_notes' => $data->reviewerNotes,
            'reviewed_by' => $data->reviewerId,
            'reviewed_at' => now(),
            'reapplication_policy' => $policy,
            'version' => $kyc->version + 1,
        ];

        // Record immutable review history
        $this->kycRepo->recordReview($kyc, [
            'reviewer_id' => $data->reviewerId,
            'decision' => 'reject',
            'reason' => $data->reason,
            'reviewer_notes' => $data->reviewerNotes,
            'feedback_fields' => $data->feedbackFields,
            'reapplication_allowed' => $data->reapplicationAllowed,
            'reapplication_cooloff_days' => $data->reapplicationCooloffDays,
            'application_version' => $kyc->version,
        ]);

        $updated = $this->kycRepo->update($kyc, $payload);

        // Durable notification and audit log
        $this->notif->notifyApplicationReviewed($updated, 'reject', $data->reason);
        $this->notif->recordAudit(
            userId: $data->reviewerId,
            action: 'kyc.reviewed.rejected',
            auditable: $updated,
            metadata: ['reason' => $data->reason, 'policy' => $policy]
        );

        return $updated;
    }
}
