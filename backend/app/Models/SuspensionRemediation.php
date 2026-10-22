<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design D14: an immutable remediation submission; only the admin's response fields are written once, at review.
 * The request's state is its latest row's. Partial unique index: one pending row per suspension.
 */
#[Fillable(['venue_suspension_id', 'content', 'files', 'status', 'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note'])]
class SuspensionRemediation extends Model
{
    public const STATUSES = ['pending', 'more_info_requested', 'rejected', 'approved'];

    /** Review decision => resulting state. */
    public const DECISIONS = ['request_more_info' => 'more_info_requested', 'reject' => 'rejected', 'approve' => 'approved'];

    protected function casts(): array
    {
        return ['files' => 'array', 'submitted_at' => UtcDateTime::class, 'reviewed_at' => UtcDateTime::class];
    }

    /** @return BelongsTo<VenueSuspension, $this> */
    public function suspension(): BelongsTo
    {
        return $this->belongsTo(VenueSuspension::class, 'venue_suspension_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** A new submission is blocked while the latest one is pending or approved. */
    public function blocksResubmission(): bool
    {
        return in_array($this->status, ['pending', 'approved'], true);
    }
}
