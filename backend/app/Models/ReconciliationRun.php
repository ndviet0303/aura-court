<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'run_date', 'status', 'provider_total', 'journal_total', 'settlement_total', 'payout_total', 'mismatch_amount', 'details', 'started_at', 'completed_at'])]
class ReconciliationRun extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['run_date' => 'date', 'provider_total' => 'integer', 'journal_total' => 'integer', 'settlement_total' => 'integer', 'payout_total' => 'integer', 'mismatch_amount' => 'integer', 'details' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
