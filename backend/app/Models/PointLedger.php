<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'user_id', 'loyalty_rule_id', 'delta', 'balance_after', 'source_type', 'source_id', 'expires_at', 'metadata'])]
class PointLedger extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['delta' => 'integer', 'balance_after' => 'integer', 'expires_at' => 'datetime', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Point ledger entries are immutable.'));
        static::deleting(fn () => throw new \LogicException('Point ledger entries are immutable.'));
    }
}
