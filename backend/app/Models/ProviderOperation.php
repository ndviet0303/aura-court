<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'provider', 'type', 'idempotency_key', 'reference', 'provider_resource_id', 'status', 'request_payload', 'response_payload', 'attempts', 'next_attempt_at', 'last_error', 'submitted_at', 'completed_at'])]
class ProviderOperation extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'next_attempt_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
