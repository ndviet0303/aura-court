<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'reservation_id', 'legacy_booking_id', 'legacy_code', 'legacy_status', 'legacy_total_amount', 'legacy_slot_count', 'source_checksum', 'migrated_at'])]
class LegacyBookingMapping extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'legacy_total_amount' => 'integer',
            'legacy_slot_count' => 'integer',
            'migrated_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(LegacyBookingSlotMapping::class);
    }
}
