<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'tournament_id', 'round', 'position', 'participant_one_id', 'participant_two_id', 'winner_id', 'score', 'status', 'version'])]
class TournamentFixture extends Model
{
    use BelongsToTenant;
    protected function casts(): array { return ['round' => 'integer', 'position' => 'integer', 'score' => 'array', 'version' => 'integer']; }
    public function tournament(): BelongsTo { return $this->belongsTo(Tournament::class); }
    public function participantOne(): BelongsTo { return $this->belongsTo(TournamentParticipant::class, 'participant_one_id'); }
    public function participantTwo(): BelongsTo { return $this->belongsTo(TournamentParticipant::class, 'participant_two_id'); }
    public function winner(): BelongsTo { return $this->belongsTo(TournamentParticipant::class, 'winner_id'); }
}
