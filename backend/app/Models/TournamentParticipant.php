<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'tournament_id', 'user_id', 'seed', 'status', 'checked_in_at', 'waiver_reason'])]
class TournamentParticipant extends Model
{
    use BelongsToTenant;
    protected function casts(): array { return ['seed' => 'integer', 'checked_in_at' => 'datetime']; }
    public function tournament(): BelongsTo { return $this->belongsTo(Tournament::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
