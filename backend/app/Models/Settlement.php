<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'period_start', 'period_end', 'eligible_at', 'gross_amount', 'refund_amount', 'provider_fee_amount', 'commission_amount', 'tenant_payable', 'currency', 'status', 'snapshot', 'snapshotted_at'])]
class Settlement extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'eligible_at' => 'datetime', 'gross_amount' => 'integer', 'refund_amount' => 'integer', 'provider_fee_amount' => 'integer', 'commission_amount' => 'integer', 'tenant_payable' => 'integer', 'snapshot' => 'array', 'snapshotted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $settlement): void {
            if ($settlement->getOriginal('snapshotted_at') && $settlement->isDirty([
                'period_start', 'period_end', 'eligible_at', 'gross_amount', 'refund_amount',
                'provider_fee_amount', 'commission_amount', 'tenant_payable', 'currency', 'snapshot',
                'snapshotted_at',
            ])) {
                throw new \LogicException('Settlement snapshots are immutable.');
            }
        });
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function payouts(): HasMany { return $this->hasMany(Payout::class); }
}
