<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['organization_id', 'reservation_entitlement_id', 'seller_id', 'original_price', 'asking_price', 'fee_bps', 'status', 'cutoff_at', 'sold_at', 'metadata'])]
class MarketplaceListing extends Model
{
    use BelongsToTenant;

    protected $appends = ['effective_status'];

    /** Who locked, who tried, the payment reference and release reasons never leave the server; parties get them from ResaleListingState. */
    private const PRIVATE_METADATA = ['locked_by', 'reference', 'buyer_ids', 'last_release'];

    public function toArray(): array
    {
        $array = parent::toArray();
        if (is_array($array['metadata'] ?? null)) {
            $array['metadata'] = array_diff_key($array['metadata'], array_flip(self::PRIVATE_METADATA));
        }

        return $array;
    }

    protected function casts(): array
    {
        return ['original_price' => 'integer', 'asking_price' => 'integer', 'cutoff_at' => UtcDateTime::class, 'sold_at' => UtcDateTime::class, 'metadata' => 'array'];
    }

    /** @return BelongsTo<ReservationEntitlement, $this> */
    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(ReservationEntitlement::class, 'reservation_entitlement_id');
    }

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return HasMany<EntitlementTransfer, $this> */
    public function transfers(): HasMany
    {
        return $this->hasMany(EntitlementTransfer::class);
    }

    /** A checkout lock counts only until locked_until; an expired one reads as released before any sweep runs. */
    public function lockActive(): bool
    {
        $until = data_get($this->metadata, 'locked_until');

        return $this->status === 'checkout' && $until && Carbon::parse($until)->isFuture();
    }

    public function lockedBy(): ?int
    {
        return $this->lockActive() ? (int) data_get($this->metadata, 'locked_by') : null;
    }

    public function getEffectiveStatusAttribute(): string
    {
        return $this->status === 'checkout' && ! $this->lockActive() ? 'listed' : (string) $this->status;
    }

    /** Listings open to buyers: listed or with a lapsed lock, before cutoff, plus any the given user holds. */
    public function scopeAvailableTo(Builder $query, ?int $userId): Builder
    {
        $now = now()->utc()->toIso8601String();

        return $query->where('cutoff_at', '>', now())->where(fn (Builder $q) => $q
            ->where('status', 'listed')
            ->orWhere(fn (Builder $locked) => $locked->where('status', 'checkout')->where(fn (Builder $lock) => $lock
                ->where('metadata->locked_until', '<=', $now)
                ->when($userId, fn (Builder $mine) => $mine->orWhere('metadata->locked_by', (string) $userId)))));
    }
}
