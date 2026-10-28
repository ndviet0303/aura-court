<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** FR-140: one seller's offer of some of their bookings, each at its own price, with a note (module 08.2 D4). */
#[Fillable(['seller_id', 'note'])]
class BookingTransferListing extends Model
{
    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return HasMany<BookingTransferListingItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BookingTransferListingItem::class, 'listing_id')->orderBy('id');
    }
}
