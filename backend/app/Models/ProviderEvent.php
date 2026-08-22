<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'provider', 'event_id', 'type', 'status', 'reference', 'provider_payment_id', 'amount', 'currency', 'payload', 'verified_at', 'processed_at', 'processing_error', 'attempts', 'next_attempt_at'])]
class ProviderEvent extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'payload' => 'array',
            'verified_at' => 'datetime',
            'processed_at' => 'datetime',
            'next_attempt_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
