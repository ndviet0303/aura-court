<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeCase extends Model
{
    use HasFactory;

    protected $table = 'dispute_cases';

    protected $fillable = [
        'dispute_code',
        'organization_id',
        'venue_id',
        'booking_id',
        'reservation_id',
        'reference_type',
        'reference_id',
        'duplicate_of_id',
        'court_name',
        'dispute_type',
        'severity',
        'title',
        'summary',
        'status',
        'sla_deadline',
        'amount_disputed',
        'currency',
        'complainant_id',
        'complainant_name',
        'complainant_tier',
        'complainant_reputation_score',
        'complainant_phone',
        'respondent_id',
        'respondent_name',
        'respondent_tier',
        'respondent_reputation_score',
        'respondent_phone',
        'facility_name',
        'facility_operator_name',
        'timeline_events',
        'chat_messages',
        'evidence',
        'requested_evidence',
        'court_map_context',
        'ai_analysis',
        'resolution_decision',
        'refund_method',
        'refund_amount',
        'compensation_type',
        'compensation_value',
        'penalty_notes',
        'penalty_points',
        'arbitrated_by',
        'arbitrated_at',
        'arbitrator_notes',
        'digital_signature_hash',
        'decision_history',
        'decision_version',
        'refund_obligation_id',
        'reopened_at',
        'reopened_by',
        'reopen_reason',
        'assigned_reviewer_id',
        'conflict_reassigned_at',
        'conflict_reassigned_by',
        'reassignment_reason',
    ];

    protected $casts = [
        'timeline_events' => 'array',
        'chat_messages' => 'array',
        'evidence' => 'array',
        'requested_evidence' => 'array',
        'court_map_context' => 'array',
        'ai_analysis' => 'array',
        'decision_history' => 'array',
        'amount_disputed' => 'integer',
        'refund_amount' => 'integer',
        'penalty_points' => 'integer',
        'decision_version' => 'integer',
        'complainant_reputation_score' => 'integer',
        'respondent_reputation_score' => 'integer',
        'sla_deadline' => 'datetime',
        'arbitrated_at' => 'datetime',
        'reopened_at' => 'datetime',
        'conflict_reassigned_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function complainant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'complainant_id');
    }

    public function respondent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondent_id');
    }

    public function arbitrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'arbitrated_by');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function assignedReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_reviewer_id');
    }

    public function refundObligation(): BelongsTo
    {
        return $this->belongsTo(OwnerRefundObligation::class, 'refund_obligation_id');
    }

    public function conflictReassignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conflict_reassigned_by');
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'investigating', 'awaiting_response'], true);
    }

    public function isResolved(): bool
    {
        return in_array($this->status, ['resolved', 'dismissed'], true);
    }
}
