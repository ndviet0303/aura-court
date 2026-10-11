<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

#[Fillable([
    'organization_id',
    'venue_id',
    'reservation_id',
    'booking_id',
    'payer_id',
    'payment_attempt_id',
    'reference_code',
    'idempotency_key',
    'request_fingerprint',
    'slot_ids',
    'gross_amount',
    'fee_amount',
    'amount',
    'currency',
    'status',
    'reason',
    'policy_snapshot',
    'recipient_ciphertext',
    'execution_reference',
    'execution_evidence_ciphertext',
    'recipient_response_ciphertext',
    'decision_reason',
    'due_at',
    'requested_at',
    'approved_at',
    'rejected_at',
    'owner_reported_at',
    'recipient_acknowledged_at',
    'requested_by',
    'approved_by',
    'rejected_by',
    'owner_reported_by',
    'recipient_acknowledged_by',
])]
class OwnerRefundObligation extends Model
{
    use BelongsToTenant;

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_OWNER_REPORTED = 'owner_reported';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_DISPUTED = 'recipient_disputed';

    protected $hidden = [
        'idempotency_key',
        'request_fingerprint',
        'recipient_ciphertext',
        'execution_evidence_ciphertext',
        'recipient_response_ciphertext',
    ];

    protected function casts(): array
    {
        return [
            'slot_ids' => 'array',
            'gross_amount' => 'integer',
            'fee_amount' => 'integer',
            'amount' => 'integer',
            'policy_snapshot' => 'array',
            'due_at' => 'datetime',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'owner_reported_at' => 'datetime',
            'recipient_acknowledged_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(OwnerPaymentAttempt::class, 'payment_attempt_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(OwnerRefundAllocation::class, 'owner_refund_obligation_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(OwnerRefundEvidence::class, 'owner_refund_obligation_id')->orderBy('id');
    }

    public function recipient(): array
    {
        return json_decode(Crypt::decryptString($this->recipient_ciphertext), true, 512, JSON_THROW_ON_ERROR);
    }

    public function executionEvidence(): ?array
    {
        if (! $this->execution_evidence_ciphertext) {
            return null;
        }

        return json_decode(Crypt::decryptString($this->execution_evidence_ciphertext), true, 512, JSON_THROW_ON_ERROR);
    }

    public function recipientResponse(): ?array
    {
        if (! $this->recipient_response_ciphertext) {
            return null;
        }

        return json_decode(Crypt::decryptString($this->recipient_response_ciphertext), true, 512, JSON_THROW_ON_ERROR);
    }
}
