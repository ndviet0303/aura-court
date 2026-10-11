<?php

namespace App\Http\Resources\Admin;

use App\Models\DisputeCase;
use App\Models\OwnerRefundObligation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DisputeCase
 */
class DisputeCaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dispute_code' => $this->dispute_code,
            'organization_id' => $this->organization_id,
            'venue_id' => $this->venue_id,
            'booking_id' => $this->booking_id,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'duplicate_of_id' => $this->duplicate_of_id,
            'court_name' => $this->court_name,
            'dispute_type' => $this->dispute_type,
            'severity' => $this->severity,
            'title' => $this->title,
            'summary' => $this->summary,
            'status' => $this->status,
            'sla_deadline' => $this->sla_deadline?->toIso8601String(),
            'amount_disputed' => (int) $this->amount_disputed,
            'currency' => $this->currency ?? 'VND',
            'complainant' => [
                'id' => $this->complainant_id,
                'name' => $this->complainant_name,
                'tier' => $this->complainant_tier,
                'reputation_score' => (int) $this->complainant_reputation_score,
                'phone' => $this->complainant_phone,
            ],
            'respondent' => [
                'id' => $this->respondent_id,
                'name' => $this->respondent_name,
                'tier' => $this->respondent_tier,
                'reputation_score' => (int) $this->respondent_reputation_score,
                'phone' => $this->respondent_phone,
            ],
            'facility' => [
                'name' => $this->facility_name,
                'operator_name' => $this->facility_operator_name,
            ],
            'timeline_events' => $this->timeline_events ?? [],
            'chat_messages' => $this->chat_messages ?? [],
            'evidence' => $this->evidence ?? [],
            'requested_evidence' => $this->requested_evidence ?? [],
            'court_map_context' => $this->court_map_context ?? [],
            'ai_analysis' => $this->ai_analysis ?? null,
            'decision_history' => $this->decision_history ?? [],
            'decision_version' => (int) ($this->decision_version ?? 1),
            'refund_obligation_id' => $this->refund_obligation_id,
            'payment_completed' => $this->refundObligation?->status === OwnerRefundObligation::STATUS_ACKNOWLEDGED,
            'refund_obligation_status' => $this->refundObligation?->status ?? ($this->refund_obligation_id ? 'approved' : null),
            'has_pending_execution' => $this->refund_obligation_id !== null && ! in_array($this->refundObligation?->status, [
                OwnerRefundObligation::STATUS_ACKNOWLEDGED,
                OwnerRefundObligation::STATUS_REJECTED,
            ], true),
            'reopened_at' => $this->reopened_at?->toIso8601String(),
            'reopened_by' => $this->reopened_by,
            'reopen_reason' => $this->reopen_reason,
            'assigned_reviewer_id' => $this->assigned_reviewer_id,
            'conflict_reassigned_at' => $this->conflict_reassigned_at?->toIso8601String(),
            'conflict_reassigned_by' => $this->conflict_reassigned_by,
            'reassignment_reason' => $this->reassignment_reason,
            'resolution_decision' => $this->resolution_decision,
            'refund_method' => $this->refund_method,
            'refund_amount' => (int) $this->refund_amount,
            'compensation_type' => $this->compensation_type,
            'compensation_value' => $this->compensation_value,
            'penalty_notes' => $this->penalty_notes,
            'penalty_points' => (int) $this->penalty_points,
            'arbitrated_by' => $this->arbitrated_by,
            'arbitrator_name' => $this->arbitrator?->name ?? 'Nguyễn Văn An',
            'arbitrated_at' => $this->arbitrated_at?->toIso8601String(),
            'arbitrator_notes' => $this->arbitrator_notes,
            'digital_signature_hash' => $this->digital_signature_hash,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
