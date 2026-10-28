<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FR-143..FR-147: the bookings a seller agreed to hand to one buyer, at the agreed prices. Pending holds them for the
 * buyer (BR-184); the seller's confirmation completes it (BR-186); either party may cancel it while pending (D9).
 */
#[Fillable(['listing_id', 'seller_id', 'buyer_id', 'status', 'total', 'confirmed_at', 'cancelled_at', 'cancelled_by'])]
class BookingTransfer extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return ['total' => 'integer', 'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /** @return HasMany<BookingTransferItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BookingTransferItem::class)->orderBy('id');
    }

    public function isParty(User $user): bool
    {
        return in_array((int) $user->id, [(int) $this->seller_id, (int) $this->buyer_id], true);
    }
}
