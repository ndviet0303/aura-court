<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

#[Fillable(['owner_refund_obligation_id', 'event_type', 'actor_id', 'event_fingerprint', 'evidence_ciphertext', 'created_at'])]
class OwnerRefundEvidence extends Model
{
    public const UPDATED_AT = null;

    protected $hidden = ['evidence_ciphertext'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(OwnerRefundObligation::class, 'owner_refund_obligation_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function content(): array
    {
        return json_decode(Crypt::decryptString($this->evidence_ciphertext), true, 512, JSON_THROW_ON_ERROR);
    }
}
