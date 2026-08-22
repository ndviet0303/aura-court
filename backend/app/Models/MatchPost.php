<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'creator_id', 'title', 'visibility', 'starts_at', 'ends_at', 'city', 'approximate_latitude', 'approximate_longitude', 'min_skill', 'max_skill', 'required_participants', 'joined_count', 'status', 'availability', 'metadata'])]
class MatchPost extends Model
{
    use BelongsToTenant;
    protected function casts(): array { return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'approximate_latitude' => 'float', 'approximate_longitude' => 'float', 'availability' => 'array', 'metadata' => 'array']; }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'creator_id'); }
    public function invitations(): HasMany { return $this->hasMany(MatchInvitation::class); }
    public function participations(): HasMany { return $this->hasMany(MatchParticipation::class); }
}
