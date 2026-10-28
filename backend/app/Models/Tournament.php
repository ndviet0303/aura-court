<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Module 07 part C (D16–D18): one sport and one event. A public tournament is set up by the venue owner, published,
 * paid for on AuraCourt and played in reserved court time (BR-148, BR-152); an internal one is run by the renter's
 * representative inside the renter's confirmed bookings, with no public entry or fee.
 */
#[Fillable([
    'uuid', 'organization_id', 'venue_id', 'created_by', 'type', 'name', 'sport', 'entry_type', 'team_main', 'team_reserve',
    'max_entries', 'format', 'group_count', 'advance_per_group', 'games_to_win', 'points', 'tiebreakers', 'rules',
    'eligibility', 'athlete_fields', 'forfeit_rule', 'prizes', 'fee', 'registration_deadline', 'payment_deadline',
    'starts_at', 'ends_at', 'status', 'published_at', 'stopped_at', 'stopped_by', 'stop_reason',
])]
class Tournament extends Model
{
    public const TYPE_PUBLIC = 'public';

    public const TYPE_INTERNAL = 'internal';

    public const SPORTS = ['badminton', 'football'];

    public const ENTRY_TYPES = ['single', 'double', 'team'];

    public const FORMAT_GROUP = 'group';

    public const FORMAT_KNOCKOUT = 'knockout';

    public const TIEBREAKERS = ['head_to_head', 'score_diff', 'score_for'];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_ROSTER_LOCKED = 'roster_locked';

    public const STATUS_DRAWN = 'drawn';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_STOPPED = 'stopped';

    protected static function booted(): void
    {
        static::creating(function (Tournament $tournament): void {
            $tournament->uuid ??= (string) Str::uuid7();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'points' => 'array',
            'tiebreakers' => 'array',
            'athlete_fields' => 'array',
            'fee' => 'integer',
            'max_entries' => 'integer',
            'team_main' => 'integer',
            'team_reserve' => 'integer',
            'group_count' => 'integer',
            'advance_per_group' => 'integer',
            'games_to_win' => 'integer',
            'registration_deadline' => UtcDateTime::class,
            'payment_deadline' => UtcDateTime::class,
            'starts_at' => UtcDateTime::class,
            'ends_at' => UtcDateTime::class,
            'published_at' => UtcDateTime::class,
            'stopped_at' => UtcDateTime::class,
        ];
    }

    public function isPublic(): bool
    {
        return $this->type === self::TYPE_PUBLIC;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_FINISHED, self::STATUS_STOPPED], true);
    }

    /** Entries holding a place: paid public applications or the internal roster. */
    public function acceptedStatus(): string
    {
        return $this->isPublic() ? TournamentEntry::STATUS_PAID : TournamentEntry::STATUS_CONFIRMED;
    }

    /** UC-7.28 PRE-2, UC-7.29 PRE-3: the roster is locked and drawn only before the start time. */
    public function hasStarted(): bool
    {
        return $this->starts_at !== null && ! $this->starts_at->isFuture();
    }

    /** BR-153: public entry is open until the registration deadline. */
    public function acceptsEntries(): bool
    {
        return $this->isPublic() && $this->status === self::STATUS_OPEN && $this->registration_deadline?->isFuture() === true;
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return HasMany<TournamentGrant, $this> */
    public function grants(): HasMany
    {
        return $this->hasMany(TournamentGrant::class);
    }

    /** @return HasMany<TournamentEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(TournamentEntry::class);
    }

    /** @return HasMany<TournamentCourtAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(TournamentCourtAllocation::class);
    }

    /** @return HasMany<TournamentRental, $this> */
    public function rentals(): HasMany
    {
        return $this->hasMany(TournamentRental::class);
    }

    /** @return HasMany<TournamentFixture, $this> */
    public function fixtures(): HasMany
    {
        return $this->hasMany(TournamentFixture::class);
    }

    /** @return HasMany<TournamentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(TournamentEvent::class);
    }
}
