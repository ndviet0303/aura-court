<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'venue_credit_account_id', 'user_id', 'type', 'balance_delta', 'reserved_delta', 'balance_after', 'reserved_after', 'source_type', 'source_id', 'reference', 'created_by', 'metadata', 'created_at'])]
class VenueCreditEntry extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['balance_delta' => 'integer', 'reserved_delta' => 'integer', 'balance_after' => 'integer', 'reserved_after' => 'integer', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Venue credit entries are immutable.'));
        static::deleting(fn () => throw new \LogicException('Venue credit entries are immutable.'));
    }

    /** @return BelongsTo<VenueCreditAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(VenueCreditAccount::class, 'venue_credit_account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
