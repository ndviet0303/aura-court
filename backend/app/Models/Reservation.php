<?php

namespace App\Models;

use App\Events\CourtStatusUpdated;
use App\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

#[Fillable(['organization_id', 'user_id', 'court_id', 'price_quote_id', 'code', 'status', 'currency', 'total_amount', 'hold_expires_at', 'confirmed_at', 'cancelled_at', 'metadata', 'payment_model'])]
class Reservation extends Model
{
    use BelongsToTenant;

    protected $appends = ['loyalty_discount_amount', 'loyalty_points_redeemed'];

    protected static function booted(): void
    {
        static::saved(function (Reservation $reservation): void {
            DB::afterCommit(function () use ($reservation): void {
                $court = $reservation->court ?? Court::find($reservation->court_id);
                if ($court && $court->venue_id) {
                    CourtStatusUpdated::dispatch(
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
        });

        static::deleted(function (Reservation $reservation): void {
            DB::afterCommit(function () use ($reservation): void {
                $court = $reservation->court ?? Court::find($reservation->court_id);
                if ($court && $court->venue_id) {
                    CourtStatusUpdated::dispatch(
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

    public function getLoyaltyDiscountAmountAttribute(): int
    {
        return (int) ($this->metadata['loyalty_discount_amount']
            ?? $this->metadata['loyalty_snapshot']['discount_vnd']
            ?? 0);
    }

    public function getLoyaltyPointsRedeemedAttribute(): int
    {
        return (int) ($this->metadata['loyalty_points_redeemed']
            ?? $this->metadata['loyalty_snapshot']['points_spent']
            ?? 0);
    }

    public function getStartsAtAttribute(): ?Carbon
    {
        if (isset($this->attributes['starts_at'])) {
            return Carbon::parse($this->attributes['starts_at']);
        }

        return $this->slots->sortBy('starts_at')->first()?->starts_at;
    }

    public function getEndsAtAttribute(): ?Carbon
    {
        if (isset($this->attributes['ends_at'])) {
            return Carbon::parse($this->attributes['ends_at']);
        }

        return $this->slots->sortByDesc('ends_at')->first()?->ends_at;
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

    /** @return HasMany<ReservationEntitlement, $this> */
    public function entitlements(): HasMany
    {
        return $this->hasMany(ReservationEntitlement::class);
    }

    public function legacyMapping(): HasMany
    {
        return $this->hasMany(LegacyBookingMapping::class);
    }

    public function recurringOccurrence(): HasOne
    {
        return $this->hasOne(RecurringOccurrence::class);
    }

    public function splitGroup(): HasOne
    {
        return $this->hasOne(SplitGroup::class);
    }

    public function attendance(): HasOne
    {
        return $this->hasOne(BookingAttendance::class);
    }

    public function serviceTabs(): HasMany
    {
        return $this->hasMany(ServiceTab::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function isDisputed(): bool
    {
        if ($this->status === 'disputed' || (bool) data_get($this->metadata, 'is_disputed', false)) {
            return true;
        }

        $legacyBookingId = LegacyBookingMapping::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('reservation_id', $this->id)
            ->value('legacy_booking_id');

        return DisputeCase::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where(function ($q) use ($legacyBookingId) {
                $q->where('reservation_id', $this->id);
                if ($legacyBookingId) {
                    $q->orWhere('booking_id', $legacyBookingId);
                }
            })
            ->whereNotIn('status', ['resolved', 'dismissed'])
            ->exists();
    }
}
