<?php

namespace App\Models;

use App\Domain\BookingTransfer\Rules\TransferEligibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One booking offered in a listing at the seller's price (BR-180, BR-181); its state is read, not stored (D4). */
#[Fillable(['listing_id', 'reservation_id', 'organization_id', 'price', 'status'])]
class BookingTransferListingItem extends Model
{
    public const STATUS_LISTED = 'listed';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_TRANSFERRED = 'transferred';

    protected function casts(): array
    {
        return ['price' => 'integer'];
    }

    /** @return BelongsTo<BookingTransferListing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(BookingTransferListing::class, 'listing_id');
    }

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class)->withoutGlobalScope('tenant_isolation');
    }

    /** BR-184: a transaction waiting for its seller's confirmation holds the booking for its buyer. */
    public function pendingTransferItems(): HasMany
    {
        return $this->hasMany(BookingTransferItem::class, 'reservation_id', 'reservation_id')
            ->where('status', BookingTransferItem::STATUS_PENDING);
    }

    /**
     * FR-141: available | held | transferred | withdrawn | expired. Needs the reservation with its slots and the
     * `held` flag (withExists pendingTransferItems as held).
     */
    public function state(): string
    {
        if ($this->status !== self::STATUS_LISTED) {
            return $this->status;
        }
        $startsAt = $this->reservation?->starts_at;
        if ($this->reservation?->status !== 'confirmed' || $startsAt === null || ! TransferEligibility::beforeCutoff($startsAt)) {
            return 'expired';
        }

        return $this->getAttribute('held') ? 'held' : 'available';
    }
}
