<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\AuditLogRepositoryInterface;
use App\Contracts\Repositories\DisputeRepositoryInterface;
use App\Domain\Admin\DTOs\ArbitrateDisputeData;
use App\Models\DisputeCase;
use App\Models\OwnerRefundObligation;
use App\Models\Venue;
use App\Services\Infrastructure\OutboxService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
                'resolve_and_refund', 'resolve' => 'resolved',
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

            // Linked OwnerRefundObligation on resolve_and_refund
            $refundObligationId = $dispute->refund_obligation_id;
            $payerId = null;
            if ($data->decision === 'resolve_and_refund') {
                $obligation = null;
                if ($refundObligationId) {
                    $obligation = OwnerRefundObligation::find($refundObligationId);
                }

                if (! $obligation) {
                    $orgId = $dispute->organization_id ?? $dispute->booking?->court?->venue?->organization_id;
                    $venueId = $dispute->venue_id ?? $dispute->booking?->court?->venue_id;
                    if (! $venueId && $orgId) {
                        $venueId = Venue::where('organization_id', $orgId)->value('id');
                    }

                    // organizations has no user_id column: the owner comes from organization_members.
                    $payerId = $dispute->respondent_id
                        ?? ($orgId ? $this->disputeRepository->findRespondentIdForOrganization($orgId) : null)
                        ?? $data->reviewerId;

                    $slotIds = $dispute->booking?->slots()->pluck('id')->all() ?? [];
                    $recipientData = [
                        'name' => $dispute->complainant_name ?? 'Complainant',
                        'user_id' => $dispute->complainant_id,
                        'phone' => $dispute->complainant_phone,
                    ];

                    $obligation = OwnerRefundObligation::create([
                        'organization_id' => $orgId,
                        'venue_id' => $venueId,
                        'booking_id' => $dispute->booking_id,
                        'payer_id' => $payerId,
                        'reference_code' => 'REF-DSP-'.strtoupper(Str::random(10)),
                        'idempotency_key' => "dispute_refund_{$dispute->id}_v{$currentVersion}",
                        'request_fingerprint' => hash('sha256', "dispute_refund_{$dispute->id}_v{$currentVersion}"),
                        'slot_ids' => $slotIds,
                        'gross_amount' => (int) $data->refundAmount,
                        'fee_amount' => 0,
                        'amount' => (int) $data->refundAmount,
                        'currency' => $dispute->currency ?? 'VND',
                        'status' => OwnerRefundObligation::STATUS_APPROVED,
                        'reason' => $data->notes ?: ("Phán quyết hoàn tiền tranh chấp {$dispute->dispute_code}"),
                        'policy_snapshot' => [
                            'dispute_code' => $dispute->dispute_code,
                            'arbitrated_at' => now()->toIso8601String(),
                        ],
                        'recipient_ciphertext' => Crypt::encryptString(json_encode($recipientData, JSON_THROW_ON_ERROR)),
                        'due_at' => now()->addDays(7),
                        'requested_at' => now(),
                        'approved_at' => now(),
                        'requested_by' => $data->reviewerId,
                        'approved_by' => $data->reviewerId,
                    ]);
                } else {
                    $payerId = $obligation->payer_id;
                    $obligation->update([
                        'amount' => (int) $data->refundAmount,
                        'gross_amount' => (int) $data->refundAmount,
                        'reason' => $data->notes ?: $obligation->reason,
                    ]);
                }

                $refundObligationId = $obligation->id;
            }

            $events = $dispute->timeline_events ?? [];
            $events[] = [
                'event' => 'dispute_arbitrated',
                'actor_id' => $data->reviewerId,
                'decision' => $data->decision,
                'version' => $currentVersion,
                'refund_obligation_id' => $refundObligationId,
                'timestamp' => now()->toIso8601String(),
            ];

            if ($data->decision === 'resolve_and_refund') {
                $events[] = [
                    'event' => 'refund_task_created',
                    'task_type' => 'owner_refund_execution',
                    'obligation_id' => $refundObligationId,
                    'status' => 'pending_financial_evidence',
                    'responsible_actor_id' => $payerId,
                    'amount' => (int) $data->refundAmount,
                    'due_at' => now()->addDays(7)->toIso8601String(),
                    'payment_completed' => false,
                    'timestamp' => now()->toIso8601String(),
                ];
            }

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
                'refund_obligation_id' => $refundObligationId,
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
