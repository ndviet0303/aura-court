<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A customer's venue credit at one organization; only VenueCreditService writes it (resale-venue-credit D2). */
#[Fillable(['organization_id', 'user_id', 'balance', 'reserved'])]
class VenueCreditAccount extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['balance' => 'integer', 'reserved' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<VenueCreditEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(VenueCreditEntry::class);
    }
}
