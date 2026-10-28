<?php

namespace App\Models;

use App\Events\CourtStatusUpdated;
use App\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

#[Fillable(['organization_id', 'user_id', 'court_id', 'price_quote_id', 'code', 'status', 'currency', 'total_amount', 'hold_expires_at', 'confirmed_at', 'cancelled_at', 'metadata', 'payment_model'])]
class Reservation extends Model
{
    use BelongsToTenant;

    protected $appends = ['loyalty_discount_amount', 'loyalty_points_redeemed', 'holder'];

    /** Module 08.2 D5: the QR value; only the holder's and the venue's own reads make it visible. */
    protected $hidden = ['check_in_code'];

    protected static function booted(): void
    {
        // Module 08.2 D5: the QR starts as the booking code; a transfer issues the new holder a new one.
        static::creating(function (Reservation $reservation): void {
            $reservation->check_in_code ??= $reservation->code;
        });

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

    /** @return array{name: string, phone: string, email: string}|null contact recorded when the booking was created */
    public function getBookerAttribute(): ?array
    {
        $booker = $this->metadata['booker'] ?? null;

        return is_array($booker) ? $booker : null;
    }

    /**
     * Module 08.2 D6: who holds the booking now: the transferee once transferred (BR-188), else the booker.
     *
     * @return array{name: string, phone: string, email: string}|null
     */
    public function getHolderAttribute(): ?array
    {
        $holder = $this->metadata['holder'] ?? null;

        return is_array($holder) ? $holder : $this->booker;
    }

    /** D6: once transferred, the holder no longer reads the first booker's contact from the booking. */
    public function toArray(): array
    {
        $array = parent::toArray();
        if (isset($array['metadata']['holder'])) {
            unset($array['metadata']['booker']);
        }

        return $array;
    }

    /**
     * Bookings that still own their court-time: confirmed, or held before their payment deadline.
     * A hold past its deadline no longer excludes anyone, whether or not the expiry job has run.
     *
     * @param  Builder<Reservation>  $query
     * @return Builder<Reservation>
     */
    public function scopeBlocksInventory(Builder $query): Builder
    {
        return $query->where(fn (Builder $active) => $active
            ->where('status', 'confirmed')
            ->orWhere(fn (Builder $held) => $held
                ->where('status', 'held')
                ->where(fn (Builder $deadline) => $deadline->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>', now()))));
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Court, $this> */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(PriceQuote::class, 'price_quote_id');
    }

    /** @return HasMany<ReservationSlot, $this> */
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

    public function splitGroup(): HasOne
    {
        return $this->hasOne(SplitGroup::class);
    }

    /** @return HasOne<BookingAttendance, $this> */
    public function attendance(): HasOne
    {
        return $this->hasOne(BookingAttendance::class);
    }

    /** The newest affected-booking case (module 2 D7), shown in the customer's booking detail. */
    public function latestAffectedCase(): HasOne
    {
        return $this->hasOne(AffectedReservationCase::class)->latestOfMany();
    }

    /** @return HasMany<AffectedReservationCase, $this> */
    public function affectedCases(): HasMany
    {
        return $this->hasMany(AffectedReservationCase::class);
    }

    /** The booking's voucher use (module 3 D11, D17), whatever its status. */
    public function voucherUse(): HasOne
    {
        return $this->hasOne(VoucherUse::class);
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
