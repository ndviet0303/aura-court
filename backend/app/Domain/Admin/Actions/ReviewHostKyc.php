<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\AuditLogRepositoryInterface;
use App\Contracts\Repositories\HostKycRepositoryInterface;
use App\Domain\Admin\DTOs\ReviewHostKycData;
use App\Models\HostKyc;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReviewHostKyc
{
    public function __construct(
        private readonly HostKycRepositoryInterface $kycRepository,
        private readonly AuditLogRepositoryInterface $auditLogs,
    ) {}

    public function execute(ReviewHostKycData $data): HostKyc
    {
        $kyc = $this->kycRepository->findById($data->kycId);

        if (!$kyc) {
            throw new InvalidArgumentException("Hồ sơ KYC #{$data->kycId} không tồn tại.");
        }

        return DB::transaction(function () use ($kyc, $data) {
            $newStatus = match ($data->decision) {
                'approve' => 'approved',
                'reject' => 'rejected',
                'request_supplement' => 'needs_supplement',
                default => throw new InvalidArgumentException("Quyết định thẩm định không hợp lệ: {$data->decision}"),
            };

            $updatePayload = [
                'status' => $newStatus,
                'rejection_reason' => $data->rejectionReason,
                'reviewer_notes' => $data->reviewerNotes,
                'checklist_state' => $data->checklistState,
                'reviewed_by' => $data->reviewerId,
                'reviewed_at' => now(),
            ];

            $updatedKyc = $this->kycRepository->update($kyc, $updatePayload);

            // If approved, update organization status to active / verified if exists
            if ($newStatus === 'approved' && $kyc->organization) {
                $kyc->organization->update([
                    'status' => 'active',
                ]);
            }

            // Create immutable audit log
            $this->auditLogs->create([
                'user_id' => $data->reviewerId,
                'action' => "kyc.{$data->decision}",
                'auditable_type' => HostKyc::class,
                'auditable_id' => $kyc->id,
                'old_values' => ['status' => $kyc->status],
                'new_values' => [
                    'status' => $newStatus,
                    'reason' => $data->rejectionReason,
                    'notes' => $data->reviewerNotes,
                ],
                'reason' => $data->rejectionReason ?: ($data->reviewerNotes ?: "Thẩm định hồ sơ KYC {$kyc->kyc_code}"),
            ]);

            return $updatedKyc;
        });
    }
}
