<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** FR-132, BR-160: a report of one post, comment or review; only the platform admin resolves it (D14). */
#[Fillable(['reporter_id', 'reportable_type', 'reportable_id', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'action_taken'])]
class FeedReport extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RESOLVED = 'resolved';

    public const ACTION_HIDDEN = 'hidden';

    public const ACTION_RESTORED = 'restored';

    public const ACTION_KEPT = 'kept';

    /** The report type a user picks, and the model it points at. */
    public const TYPES = ['post' => FeedPost::class, 'comment' => FeedComment::class, 'review' => Review::class];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
