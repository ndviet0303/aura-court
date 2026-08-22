<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'match_post_id', 'user_id', 'status', 'joined_at', 'verified_at'])]
class MatchParticipation extends Model
{
    use BelongsToTenant;
    protected function casts(): array { return ['joined_at' => 'datetime', 'verified_at' => 'datetime']; }
    public function post(): BelongsTo { return $this->belongsTo(MatchPost::class, 'match_post_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
