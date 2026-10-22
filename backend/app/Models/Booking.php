<?php

namespace App\Models;

use App\Events\CourtStatusUpdated;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

#[Fillable(['code', 'user_id', 'court_id', 'coupon_id', 'total_amount', 'discount_amount', 'membership_discount_amount', 'status', 'payment_model', 'payment_expires_at', 'payment_expiring_notified_at', 'coupon_usage_released_at', 'cancelled_at', 'cancellation_reason', 'policy_snapshot', 'price_snapshot', 'metadata'])]
class Booking extends Model
{
    protected $appends = ['loyalty_discount_amount', 'loyalty_points_redeemed'];

    protected static function booted(): void
    {
        static::saved(function (Booking $booking): void {
            DB::afterCommit(function () use ($booking): void {
                $court = $booking->court ?? Court::find($booking->court_id);
                if ($court && $court->venue_id) {
                    CourtStatusUpdated::dispatch(
                        venueId: (int) $court->venue_id,
                        courtId: (int) $court->id,
                        status: in_array($booking->status, ['cancelled', 'refunded'], true) ? 'available' : 'booked',
                        metadata: [
                            'booking_id' => $booking->id,
                            'code' => $booking->code,
                            'status' => $booking->status,
                        ]
                    );
                }
            });
        });

        static::deleted(function (Booking $booking): void {
            DB::afterCommit(function () use ($booking): void {
                $court = $booking->court ?? Court::find($booking->court_id);
                if ($court && $court->venue_id) {
                    CourtStatusUpdated::dispatch(
                        venueId: (int) $court->venue_id,
                        courtId: (int) $court->id,
                        status: 'available',
                        metadata: [
                            'booking_id' => $booking->id,
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
            'discount_amount' => 'integer',
            'membership_discount_amount' => 'integer',
            'payment_expires_at' => 'datetime',
            'payment_expiring_notified_at' => 'datetime',
            'coupon_usage_released_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'policy_snapshot' => 'array',
            'price_snapshot' => 'array',
            'metadata' => 'array',
        ];
    }

    public function getLoyaltyDiscountAmountAttribute(): int
    {
        return (int) ($this->metadata['loyalty_discount_amount']
            ?? $this->metadata['loyalty_snapshot']['discount_vnd']
            ?? $this->policy_snapshot['loyalty_snapshot']['discount_vnd']
            ?? 0);
    }

    public function getLoyaltyPointsRedeemedAttribute(): int
    {
        return (int) ($this->metadata['loyalty_points_redeemed']
            ?? $this->metadata['loyalty_snapshot']['points_spent']
            ?? $this->policy_snapshot['loyalty_snapshot']['points_spent']
            ?? 0);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(BookingSlot::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function legacyMapping(): HasMany
    {
        return $this->hasMany(LegacyBookingMapping::class, 'legacy_booking_id');
    }
}
