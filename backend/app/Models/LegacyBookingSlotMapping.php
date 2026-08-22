<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'legacy_booking_mapping_id', 'reservation_slot_id', 'legacy_booking_slot_id', 'source_checksum'])]
class LegacyBookingSlotMapping extends Model
{
    use BelongsToTenant;

    public function bookingMapping(): BelongsTo
    {
        return $this->belongsTo(LegacyBookingMapping::class, 'legacy_booking_mapping_id');
    }

    public function reservationSlot(): BelongsTo
    {
        return $this->belongsTo(ReservationSlot::class);
    }
}
