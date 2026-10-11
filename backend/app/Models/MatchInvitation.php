<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'match_post_id', 'invitee_id', 'invited_by', 'status', 'responded_at'])]
class MatchInvitation extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(MatchPost::class, 'match_post_id');
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }
}
