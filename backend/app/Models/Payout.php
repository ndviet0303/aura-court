<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'settlement_id', 'beneficiary_id', 'amount', 'currency', 'provider', 'provider_payout_id', 'idempotency_key', 'status', 'settlement_snapshot', 'failure_reason', 'approved_at', 'submitted_at', 'paid_at', 'failed_at', 'reversed_at'])]
class Payout extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'settlement_snapshot' => 'array', 'approved_at' => 'datetime', 'submitted_at' => 'datetime', 'paid_at' => 'datetime', 'failed_at' => 'datetime', 'reversed_at' => 'datetime'];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function settlement(): BelongsTo { return $this->belongsTo(Settlement::class); }
    public function beneficiary(): BelongsTo { return $this->belongsTo(Beneficiary::class); }
}
