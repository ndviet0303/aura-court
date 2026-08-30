<?php

namespace App\Http\Resources\Admin;

use App\Models\DisputeCase;
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
            'court_map_context' => $this->court_map_context ?? [],
            'ai_analysis' => $this->ai_analysis ?? null,
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
