<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'type', 'severity', 'status', 'amount', 'currency', 'reference', 'description', 'links', 'metadata', 'detected_at', 'due_at', 'resolved_at', 'resolved_by', 'resolution'])]
class FinanceException extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'links' => 'array', 'metadata' => 'array', 'detected_at' => 'datetime', 'due_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
