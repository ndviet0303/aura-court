<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BR-150: a unit (single, pair or team) whose athletes need no account. A public application belongs to the account
 * that submitted it (its representative); an internal roster entry has no account and is always confirmed.
 */
#[Fillable(['tournament_id', 'user_id', 'name', 'status', 'reject_reason', 'group', 'seed', 'approved_at', 'paid_at', 'withdrawn_at'])]
class TournamentEntry extends Model
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PAID = 'paid';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_CONFIRMED = 'confirmed';

    protected function casts(): array
    {
        return [
            'seed' => 'integer',
            'approved_at' => UtcDateTime::class,
            'paid_at' => UtcDateTime::class,
            'withdrawn_at' => UtcDateTime::class,
        ];
    }

    /** @return BelongsTo<Tournament, $this> */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<TournamentEntryMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(TournamentEntryMember::class, 'entry_id')->orderBy('position');
    }
}
