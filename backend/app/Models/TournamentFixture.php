<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A match of the draw (UC-7.29). Group stage: round-robin pairs of one group. Knockout: round 1 holds the drawn
 * pairs, and the winner of position p moves to position ceil(p/2) of the next round. forfeit_entry_id is the side
 * that did not show and lost by walkover (BR-156).
 */
#[Fillable(['tournament_id', 'stage', 'group', 'round', 'position', 'entry_one_id', 'entry_two_id', 'winner_entry_id', 'forfeit_entry_id', 'court_id', 'starts_at', 'ends_at', 'score', 'status'])]
class TournamentFixture extends Model
{
    public const STAGE_GROUP = 'group';

    public const STAGE_KNOCKOUT = 'knockout';

    public const STATUS_PENDING = 'pending';

    public const STATUS_BYE = 'bye';

    public const STATUS_COMPLETED = 'completed';

    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'position' => 'integer',
            'score' => 'array',
            'starts_at' => UtcDateTime::class,
            'ends_at' => UtcDateTime::class,
        ];
    }

    /** @return BelongsTo<Tournament, $this> */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /** @return BelongsTo<TournamentEntry, $this> */
    public function entryOne(): BelongsTo
    {
        return $this->belongsTo(TournamentEntry::class, 'entry_one_id');
    }

    /** @return BelongsTo<TournamentEntry, $this> */
    public function entryTwo(): BelongsTo
    {
        return $this->belongsTo(TournamentEntry::class, 'entry_two_id');
    }

    /** @return BelongsTo<Court, $this> */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}
