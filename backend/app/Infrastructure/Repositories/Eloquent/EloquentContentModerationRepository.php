<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\ContentModerationRepositoryInterface;
use App\Models\FeedComment;
use App\Models\FeedReport;
use App\Models\ModerationRecord;
use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

class EloquentContentModerationRepository implements ContentModerationRepositoryInterface
{
    public function findTarget(string $class, int $id): ?Model
    {
        /** @var class-string<Model> $class */
        return $class::query()->with('user:id,name,avatar_url')->find($id);
    }

    public function hasPendingReport(int $reporterId, string $class, int $id): bool
    {
        return FeedReport::query()
            ->where('reporter_id', $reporterId)
            ->where('reportable_type', $class)
            ->where('reportable_id', $id)
            ->where('status', FeedReport::STATUS_PENDING)
            ->exists();
    }

    public function createReport(array $attributes): FeedReport
    {
        return FeedReport::create($attributes);
    }

    public function paginateReports(?string $status, ?string $class, int $perPage): LengthAwarePaginator
    {
        return FeedReport::query()
            ->when($status, fn ($query, string $value) => $query->where('status', $value))
            ->when($class, fn ($query, string $value) => $query->where('reportable_type', $value))
            ->with(['reporter:id,name', 'reviewer:id,name', 'reportable' => fn (MorphTo $morph) => $morph->with('user:id,name,avatar_url')])
            ->latest()
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function pendingCount(string $class, int $id): int
    {
        return FeedReport::query()
            ->where('reportable_type', $class)
            ->where('reportable_id', $id)
            ->where('status', FeedReport::STATUS_PENDING)
            ->count();
    }

    public function nearbyComments(FeedComment $comment, int $each): Collection
    {
        $siblings = fn () => FeedComment::query()->where('feed_post_id', $comment->feed_post_id)->with('user:id,name,avatar_url');
        $before = $siblings()->where('id', '<', $comment->id)->orderByDesc('id')->limit($each)->get();
        $after = $siblings()->where('id', '>', $comment->id)->orderBy('id')->limit($each)->get();

        return $before->reverse()->concat($after)->values()->toBase();
    }

    public function history(string $class, int $id): Collection
    {
        return ModerationRecord::query()
            ->where('target_type', $class)
            ->where('target_id', $id)
            ->with('actor:id,name')
            ->orderBy('id')
            ->get()
            ->toBase();
    }

    public function setHidden(Model $target, bool $hidden, int $actorId, string $reason): void
    {
        if ($target instanceof Review) {
            $target->update(['is_published' => ! $hidden]);

            return;
        }
        $target->update($hidden
            ? ['is_hidden' => true, 'hidden_at' => now(), 'hidden_by' => $actorId, 'hidden_reason' => $reason]
            : ['is_hidden' => false, 'hidden_at' => null, 'hidden_by' => null, 'hidden_reason' => null]);
    }

    public function resolve(FeedReport $report, string $action, int $reviewerId): void
    {
        FeedReport::query()
            ->where(fn ($query) => $query->whereKey($report->id)->orWhere(fn ($pending) => $pending
                ->where('reportable_type', $report->reportable_type)
                ->where('reportable_id', $report->reportable_id)
                ->where('status', FeedReport::STATUS_PENDING)))
            ->update([
                'status' => FeedReport::STATUS_RESOLVED,
                'action_taken' => $action,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function record(array $attributes): ModerationRecord
    {
        return ModerationRecord::create($attributes + ['created_at' => now()]);
    }
}
