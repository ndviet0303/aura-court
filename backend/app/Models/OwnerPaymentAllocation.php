<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class OwnerPaymentAllocation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'venue_id',
        'receiving_connection_id',
        'payment_attempt_id',
        'payment_receipt_id',
        'purpose',
        'purpose_id',
        'amount',
        'currency',
        'credential_version',
        'receiver_snapshot_ciphertext',
        'purpose_snapshot_ciphertext',
        'allocated_at',
        'created_at',
    ];

    protected $hidden = ['receiver_snapshot_ciphertext', 'purpose_snapshot_ciphertext'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'credential_version' => 'integer',
            'allocated_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(OwnerPaymentAttempt::class, 'payment_attempt_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(OwnerPaymentReceipt::class, 'payment_receipt_id');
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
