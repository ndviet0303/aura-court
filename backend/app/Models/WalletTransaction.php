<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'wallet_account_id', 'type', 'amount', 'balance_after', 'source_type', 'source_id', 'reference'])]
class WalletTransaction extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_after' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Wallet transactions are immutable.'));
        static::deleting(fn () => throw new \LogicException('Wallet transactions are immutable.'));
    }
}
