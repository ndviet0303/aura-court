<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\AuditLogRepositoryInterface;
use App\Contracts\Repositories\DisputeRepositoryInterface;
use App\Domain\Admin\DTOs\ArbitrateDisputeData;
use App\Models\DisputeCase;
use App\Services\Infrastructure\OutboxService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ArbitrateDispute
{
    public function __construct(
        private readonly DisputeRepositoryInterface $disputeRepository,
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly OutboxService $outbox,
    ) {}

    public function execute(ArbitrateDisputeData $data): DisputeCase
    {
        $dispute = $this->disputeRepository->findById($data->disputeId);

        if (! $dispute) {
            throw new InvalidArgumentException("Hồ sơ tranh chấp #{$data->disputeId} không tồn tại.");
        }
        if ((int) $data->reviewerId === (int) $dispute->complainant_id || (int) $data->reviewerId === (int) $dispute->respondent_id) {
            abort(422, 'Conflict of interest: Reviewer cannot arbitrate a dispute in which they are an involved party.');
        }

        return DB::transaction(function () use ($dispute, $data) {
            $newStatus = match ($data->decision) {
                'resolve' => 'resolved',
                'dismiss' => 'dismissed',
                'investigate_further' => 'investigating',
                default => 'resolved',
            };

            // Track decision history & increment decision version if previous decision exists
            $history = $dispute->decision_history ?? [];
            $currentVersion = (int) ($dispute->decision_version ?? 1);
            if ($dispute->resolution_decision !== null || $dispute->arbitrated_at !== null) {
                $history[] = [
                    'version' => $currentVersion,
                    'reviewer_id' => $dispute->arbitrated_by,
                    'decision' => $dispute->resolution_decision,
                    'notes' => $dispute->arbitrator_notes,
                    'refund_amount' => (int) ($dispute->refund_amount ?? 0),
                    'arbitrated_at' => $dispute->arbitrated_at ? $dispute->arbitrated_at->toIso8601String() : null,
                ];
                $currentVersion++;
            }

            $events = $dispute->timeline_events ?? [];
            $events[] = [
                'event' => 'dispute_arbitrated',
                'actor_id' => $data->reviewerId,
                'decision' => $data->decision,
                'version' => $currentVersion,
                'timestamp' => now()->toIso8601String(),
            ];

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
                'decision_history' => $history,
                'decision_version' => $currentVersion,
                'timeline_events' => $events,
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

            // Notifications: Enqueue dispute.arbitrated notifications via OutboxService to both complainant and respondent
            if ($dispute->complainant_id) {
                $this->outbox->enqueue([
                    'organization_id' => $dispute->organization_id,
                    'message_key' => "dispute_arbitrated_complainant:{$dispute->id}:v{$currentVersion}:".uniqid(),
                    'channel' => 'notification',
                    'event_type' => 'dispute.arbitrated',
                    'aggregate_type' => DisputeCase::class,
                    'aggregate_id' => $dispute->id,
                ], [
                    'user_id' => $dispute->complainant_id,
                    'recipient_id' => $dispute->complainant_id,
                    'dispute_id' => $dispute->id,
                    'dispute_code' => $dispute->dispute_code,
                    'decision' => $data->decision,
                    'refund_amount' => (int) $data->refundAmount,
                    'title' => 'Phán quyết tranh chấp',
                    'body' => "Hồ sơ tranh chấp {$dispute->dispute_code} đã có kết quả phán quyết.",
                    'notes' => $data->notes,
                ]);
            }

            if ($dispute->respondent_id) {
                $this->outbox->enqueue([
                    'organization_id' => $dispute->organization_id,
                    'message_key' => "dispute_arbitrated_respondent:{$dispute->id}:v{$currentVersion}:".uniqid(),
                    'channel' => 'notification',
                    'event_type' => 'dispute.arbitrated',
                    'aggregate_type' => DisputeCase::class,
                    'aggregate_id' => $dispute->id,
                ], [
                    'user_id' => $dispute->respondent_id,
                    'recipient_id' => $dispute->respondent_id,
                    'dispute_id' => $dispute->id,
                    'dispute_code' => $dispute->dispute_code,
                    'decision' => $data->decision,
                    'refund_amount' => (int) $data->refundAmount,
                    'title' => 'Phán quyết tranh chấp',
                    'body' => "Hồ sơ tranh chấp {$dispute->dispute_code} đã có kết quả phán quyết.",
                    'notes' => $data->notes,
                ]);
            }

            return $updatedDispute;
        });
    }
}
