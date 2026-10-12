<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'user_id', 'currency', 'balance', 'status'])]
class WalletAccount extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['balance' => 'integer'];
    }

    /** @return HasMany<WalletTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_account_id');
    }
}
