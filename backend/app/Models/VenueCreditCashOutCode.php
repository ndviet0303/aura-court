<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A 10-minute code a customer shows at the counter to take credit as cash (resale-venue-credit D7); only the hash is stored. */
#[Fillable(['organization_id', 'user_id', 'code_hash', 'amount', 'expires_at', 'used_at', 'used_by', 'cash_shift_id', 'cancelled_at'])]
class VenueCreditCashOutCode extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'expires_at' => UtcDateTime::class, 'used_at' => UtcDateTime::class, 'cancelled_at' => UtcDateTime::class];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isLive(): bool
    {
        return $this->used_at === null && $this->cancelled_at === null && $this->expires_at->isFuture();
    }
}
