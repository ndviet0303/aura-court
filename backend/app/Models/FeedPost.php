<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** D12: a post on the platform-wide feed; a matchmaking post only describes the play (BR-146), it books nothing. */
#[Fillable(['user_id', 'type', 'content', 'images', 'sport', 'venue_id', 'address', 'court_code', 'play_at', 'is_hidden', 'hidden_at', 'hidden_by', 'hidden_reason'])]
class FeedPost extends Model
{
    use HasFactory;

    public const TYPE_SHARE = 'share';

    public const TYPE_MATCHMAKING = 'matchmaking';

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'play_at' => 'datetime',
            'is_hidden' => 'boolean',
            'hidden_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<FeedComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(FeedComment::class);
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return MorphMany<FeedReport, $this> */
    public function reports(): MorphMany
    {
        return $this->morphMany(FeedReport::class, 'reportable');
    }

    /** @return BelongsTo<User, $this> */
    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }
}
