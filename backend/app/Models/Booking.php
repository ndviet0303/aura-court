<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['code', 'user_id', 'court_id', 'coupon_id', 'total_amount', 'discount_amount', 'membership_discount_amount', 'status', 'payment_expires_at', 'payment_expiring_notified_at', 'coupon_usage_released_at', 'cancelled_at', 'cancellation_reason'])]
class Booking extends Model
{
    protected static function booted(): void
    {
        static::saved(function (Booking $booking): void {
            $court = $booking->court ?? Court::find($booking->court_id);
            if ($court && $court->venue_id) {
                \App\Events\CourtStatusUpdated::dispatch(
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

        static::deleted(function (Booking $booking): void {
            $court = $booking->court ?? Court::find($booking->court_id);
            if ($court && $court->venue_id) {
                \App\Events\CourtStatusUpdated::dispatch(
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

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }
}
