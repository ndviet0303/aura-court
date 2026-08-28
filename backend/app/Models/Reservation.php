<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'user_id', 'court_id', 'price_quote_id', 'code', 'status', 'currency', 'total_amount', 'hold_expires_at', 'confirmed_at', 'cancelled_at', 'metadata'])]
class Reservation extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::saved(function (Reservation $reservation): void {
            $court = $reservation->court ?? Court::find($reservation->court_id);
            if ($court && $court->venue_id) {
                \App\Events\CourtStatusUpdated::dispatch(
                    venueId: (int) $court->venue_id,
                    courtId: (int) $court->id,
                    status: $reservation->status === 'cancelled' ? 'available' : 'booked',
                    metadata: [
                        'reservation_id' => $reservation->id,
                        'code' => $reservation->code,
                        'status' => $reservation->status,
                    ]
                );
            }
        });

        static::deleted(function (Reservation $reservation): void {
            $court = $reservation->court ?? Court::find($reservation->court_id);
            if ($court && $court->venue_id) {
                \App\Events\CourtStatusUpdated::dispatch(
                    venueId: (int) $court->venue_id,
                    courtId: (int) $court->id,
                    status: 'available',
                    metadata: [
                        'reservation_id' => $reservation->id,
                        'event' => 'deleted',
                    ]
                );
            }
        });
    }

    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
            'hold_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(PriceQuote::class, 'price_quote_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(ReservationSlot::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(ReservationEntitlement::class);
    }

    public function legacyMapping(): HasMany
    {
        return $this->hasMany(LegacyBookingMapping::class);
    }
}
