<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\AuditLogRepositoryInterface;
use App\Contracts\Repositories\DisputeRepositoryInterface;
use App\Domain\Admin\DTOs\ArbitrateDisputeData;
use App\Models\DisputeCase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ArbitrateDispute
{
    public function __construct(
        private readonly DisputeRepositoryInterface $disputeRepository,
        private readonly AuditLogRepositoryInterface $auditLogs,
    ) {}

    public function execute(ArbitrateDisputeData $data): DisputeCase
    {
        $dispute = $this->disputeRepository->findById($data->disputeId);

        if (!$dispute) {
            throw new InvalidArgumentException("Hồ sơ tranh chấp #{$data->disputeId} không tồn tại.");
        }

        return DB::transaction(function () use ($dispute, $data) {
            $newStatus = match ($data->decision) {
                'resolve_and_refund', 'resolve' => 'resolved',
                'dismiss' => 'dismissed',
                'investigate_further' => 'investigating',
                default => 'resolved',
            };

            $updatePayload = [
                'status' => $newStatus,
                'resolution_decision' => $data->decision,
                'refund_method' => $data->refundMethod,
                'refund_amount' => $data->refundAmount,
                'compensation_type' => $data->compensationType,
                'compensation_value' => $data->compensationValue,
                'penalty_notes' => $data->penaltyNotes,
                'penalty_points' => $data->penaltyPoints,
                'arbitrated_by' => $data->reviewerId,
                'arbitrated_at' => now(),
                'arbitrator_notes' => $data->notes,
                'digital_signature_hash' => $data->digitalSignatureHash,
            ];

            $updatedDispute = $this->disputeRepository->update($dispute, $updatePayload);

            // Record immutable audit log
            $this->auditLogs->create([
                'user_id' => $data->reviewerId,
                'action' => "dispute.{$data->decision}",
                'auditable_type' => DisputeCase::class,
                'auditable_id' => $dispute->id,
                'old_values' => [
                    'status' => $dispute->status,
                ],
                'new_values' => [
                    'status' => $newStatus,
                    'decision' => $data->decision,
                    'refund_amount' => $data->refundAmount,
                    'refund_method' => $data->refundMethod,
                    'compensation' => $data->compensationValue,
                    'signature' => $data->digitalSignatureHash,
                ],
                'reason' => $data->notes ?: ("Phán quyết tranh chấp {$dispute->dispute_code}: {$data->decision}"),
            ]);

            return $updatedDispute;
        });
    }
}
