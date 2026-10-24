<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** UC-7.18: an official runs a public tournament's matches; a representative runs an internal tournament. */
#[Fillable(['tournament_id', 'user_id', 'role', 'granted_by', 'revoked_at'])]
class TournamentGrant extends Model
{
    public const ROLE_OFFICIAL = 'official';

    public const ROLE_REPRESENTATIVE = 'representative';

    protected function casts(): array
    {
        return ['revoked_at' => UtcDateTime::class];
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
}
