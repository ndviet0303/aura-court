<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivingConnectionEvidence extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'receiving_connection_id',
        'actor_id',
        'event_type',
        'credential_version',
        'credential_type',
        'event_fingerprint',
        'payload_fingerprint',
        'evidence_ciphertext',
        'created_at',
    ];

    protected $hidden = ['evidence_ciphertext'];

    protected function casts(): array
    {
        return [
            'credential_version' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function receivingConnection(): BelongsTo
    {
        return $this->belongsTo(ReceivingConnection::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
