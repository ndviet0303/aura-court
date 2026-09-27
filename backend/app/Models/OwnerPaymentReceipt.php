<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Crypt;

class OwnerPaymentReceipt extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ALLOCATED = 'allocated';

    public const STATUS_UNMATCHED = 'unmatched';

    public const STATUS_EXCEPTION = 'exception';

    public const STATUS_LATE = 'late';

    protected $fillable = [
        'organization_id',
        'venue_id',
        'receiving_connection_id',
        'receiving_connection_evidence_id',
        'payment_attempt_id',
        'provider',
        'source',
        'provider_event_id',
        'event_fingerprint',
        'purpose',
        'purpose_id',
        'payment_code',
        'reference_code',
        'transfer_type',
        'amount',
        'currency',
        'event_at',
        'credential_version',
        'status',
        'exception_code',
        'receiver_snapshot_ciphertext',
        'purpose_snapshot_ciphertext',
        'received_at',
        'processed_at',
        'processing_attempts',
        'next_attempt_at',
        'processing_error',
    ];

    protected $hidden = [
        'provider_event_id',
        'event_fingerprint',
        'reference_code',
        'receiver_snapshot_ciphertext',
        'purpose_snapshot_ciphertext',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'credential_version' => 'integer',
            'event_at' => 'datetime',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'processing_attempts' => 'integer',
            'next_attempt_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(OwnerPaymentAttempt::class, 'payment_attempt_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ReceivingConnection::class, 'receiving_connection_id');
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(ReceivingConnectionEvidence::class, 'receiving_connection_evidence_id');
    }

    public function allocation(): HasOne
    {
        return $this->hasOne(OwnerPaymentAllocation::class, 'payment_receipt_id');
    }

    public function receiverSnapshot(): array
    {
        return json_decode(Crypt::decryptString($this->receiver_snapshot_ciphertext), true, 512, JSON_THROW_ON_ERROR);
    }

    public function purposeSnapshot(): ?array
    {
        if (! $this->purpose_snapshot_ciphertext) {
            return null;
        }

        return json_decode(Crypt::decryptString($this->purpose_snapshot_ciphertext), true, 512, JSON_THROW_ON_ERROR);
    }
}
