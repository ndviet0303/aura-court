<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * D15: a review of the venue from one completed reservation. is_published false means the platform admin hid it (D14).
 * A deleted review stays as a trashed row so the reservation can never be reviewed again (BR-147).
 */
#[Fillable(['user_id', 'venue_id', 'court_id', 'booking_id', 'reservation_id', 'rating', 'body', 'images', 'is_published', 'reply_body', 'replied_by', 'replied_at'])]
class Review extends Model
{
    use SoftDeletes;

    /** BR-147: days after check-out to create, and after the first submission to edit. */
    public const WINDOW_DAYS = 7;

    protected function casts(): array
    {
        return ['rating' => 'integer', 'is_published' => 'boolean', 'images' => 'array', 'replied_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return BelongsTo<Court, $this> */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function repliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    /** @return MorphMany<FeedReport, $this> */
    public function reports(): MorphMany
    {
        return $this->morphMany(FeedReport::class, 'reportable');
    }
}
