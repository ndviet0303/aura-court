<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stop of a venue (court_id null) or of one court (design D5). Stops are never deleted; resume records ended_at.
 * It blocks [starts_at, ended_at ?? infinity). expected_end_at is informational only and never blocks or ends a stop.
 * Times are UTC, like reservation_slots.
 */
#[Fillable(['venue_id', 'court_id', 'kind', 'reason', 'starts_at', 'expected_end_at', 'ended_at', 'created_by', 'ended_by'])]
class OperationalStop extends Model
{
    public const KIND_MAINTENANCE = 'maintenance';

    public const KIND_BUSINESS = 'business';

    public const KIND_CLOSURE = 'closure';

    public const KINDS = [self::KIND_MAINTENANCE, self::KIND_BUSINESS, self::KIND_CLOSURE];

    protected function casts(): array
    {
        return [
            'starts_at' => UtcDateTime::class,
            'expected_end_at' => UtcDateTime::class,
            'ended_at' => UtcDateTime::class,
        ];
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

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    /** D5: a booking [start, end) overlaps when end > starts_at and (no recorded end or start < ended_at); a booking ending at starts_at does not. */
    public function scopeOverlapping(Builder $query, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $query
            ->where('starts_at', '<', $end->copy()->utc())
            ->where(fn (Builder $q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $start->copy()->utc()));
    }

    /** The court's own stops and the stops of its whole venue. */
    public function scopeAffectingCourt(Builder $query, Court|int $court, int $venueId): Builder
    {
        $courtId = $court instanceof Court ? (int) $court->id : $court;

        return $query->where(fn (Builder $q) => $q
            ->where('court_id', $courtId)
            ->orWhere(fn (Builder $venueWide) => $venueWide->where('venue_id', $venueId)->whereNull('court_id')));
    }
}
