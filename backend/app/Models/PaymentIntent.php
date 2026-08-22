<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'user_id', 'reference', 'provider', 'provider_intent_id', 'idempotency_key', 'amount', 'currency', 'status', 'metadata', 'expires_at', 'succeeded_at', 'failed_at'])]
class PaymentIntent extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'metadata' => 'array',
            'expires_at' => 'datetime',
            'succeeded_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
