<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One booking of a transfer at its agreed price; its status follows the transfer's (D4). */
#[Fillable(['booking_transfer_id', 'reservation_id', 'organization_id', 'price', 'status'])]
class BookingTransferItem extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    protected function casts(): array
    {
        return ['price' => 'integer'];
    }

    /** @return BelongsTo<BookingTransfer, $this> */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(BookingTransfer::class, 'booking_transfer_id');
    }

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class)->withoutGlobalScope('tenant_isolation');
    }
}
