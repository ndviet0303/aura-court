<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'created_by', 'name', 'format', 'status', 'version', 'starts_at', 'settings'])]
class Tournament extends Model
{
    use BelongsToTenant;
    protected function casts(): array { return ['version' => 'integer', 'starts_at' => 'datetime', 'settings' => 'array']; }
    public function participants(): HasMany { return $this->hasMany(TournamentParticipant::class); }
    public function fixtures(): HasMany { return $this->hasMany(TournamentFixture::class); }
    public function standings(): HasMany { return $this->hasMany(TournamentStanding::class); }
    public function events(): HasMany { return $this->hasMany(TournamentEvent::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
