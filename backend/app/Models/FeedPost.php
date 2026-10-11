<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['organization_id', 'user_id', 'audience', 'content', 'is_hidden', 'hidden_at', 'hidden_by', 'hidden_reason'])]
class FeedPost extends Model
{
    use BelongsToTenant, HasFactory;

    public const AUDIENCE_ORGANIZATION = 'organization';

    public const AUDIENCE_FRIENDS = 'friends';

    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
            'hidden_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(FeedComment::class);
    }

    public function reactions(): MorphMany
    {
        return $this->morphMany(FeedReaction::class, 'reactable');
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(FeedReport::class, 'reportable');
    }

    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }
}
