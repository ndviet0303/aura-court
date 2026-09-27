<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class OwnerPaymentAttempt extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'venue_id',
        'user_id',
        'receiving_connection_id',
        'payment_model',
        'provider',
        'purpose',
        'purpose_id',
        'reference',
        'idempotency_key',
        'request_fingerprint',
        'amount',
        'currency',
        'status',
        'credential_version',
        'receiver_snapshot_ciphertext',
        'purpose_snapshot_ciphertext',
        'expires_at',
        'paid_at',
    ];

    protected $hidden = [
        'idempotency_key',
        'request_fingerprint',
        'receiver_snapshot_ciphertext',
        'purpose_snapshot_ciphertext',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'credential_version' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function receivingConnection(): BelongsTo
    {
        return $this->belongsTo(ReceivingConnection::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(OwnerPaymentReceipt::class, 'payment_attempt_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(OwnerPaymentAllocation::class, 'payment_attempt_id');
    }

    public function receiverSnapshot(): array
    {
        return json_decode(Crypt::decryptString($this->receiver_snapshot_ciphertext), true, 512, JSON_THROW_ON_ERROR);
    }

    public function purposeSnapshot(): array
    {
        return json_decode(Crypt::decryptString($this->purpose_snapshot_ciphertext), true, 512, JSON_THROW_ON_ERROR);
    }
}
