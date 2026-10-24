<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** BR-152, UC-7.30: court time the owner reserves for a public tournament; ordinary bookings cannot take it. */
#[Fillable(['tournament_id', 'court_id', 'starts_at', 'ends_at', 'created_by'])]
class TournamentCourtAllocation extends Model
{
    protected function casts(): array
    {
        return ['starts_at' => UtcDateTime::class, 'ends_at' => UtcDateTime::class];
    }

    /** Whether any live tournament holds the court at some moment of [start, end). */
    public static function blocks(int $courtId, CarbonInterface $start, CarbonInterface $end): bool
    {
        return self::query()->overlapping($courtId, $start, $end)->exists();
    }

    /** Allocations of tournaments that have not been stopped, overlapping [start, end) on the court. */
    public function scopeOverlapping(Builder $query, int $courtId, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $query->where('court_id', $courtId)
            ->where('starts_at', '<', $end->copy()->utc())
            ->where('ends_at', '>', $start->copy()->utc())
            ->whereHas('tournament', fn (Builder $tournament) => $tournament->where('status', '!=', Tournament::STATUS_STOPPED));
    }

    /** @return BelongsTo<Tournament, $this> */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /** @return BelongsTo<Court, $this> */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}
