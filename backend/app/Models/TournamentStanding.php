<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'tournament_id', 'participant_id', 'played', 'wins', 'draws', 'losses', 'points', 'rank', 'version'])]
class TournamentStanding extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['played' => 'integer', 'wins' => 'integer', 'draws' => 'integer', 'losses' => 'integer', 'points' => 'integer', 'rank' => 'integer', 'version' => 'integer'];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class);
    }
}
