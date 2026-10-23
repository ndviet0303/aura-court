<?php

namespace App\Contracts\Repositories;

use App\Models\FeedComment;
use App\Models\FeedReport;
use App\Models\ModerationRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** FR-132, BR-160: reports of posts, comments and reviews, and the platform admin's decisions on them (D14). */
interface ContentModerationRepositoryInterface
{
    /** The reported post, comment or review, hidden or not; null once its author deleted it. */
    public function findTarget(string $class, int $id): ?Model;

    public function hasPendingReport(int $reporterId, string $class, int $id): bool;

    /** @param  array<string, mixed>  $attributes */
    public function createReport(array $attributes): FeedReport;

    /** @return LengthAwarePaginator<int, FeedReport> newest first; status null lists every report */
    public function paginateReports(?string $status, ?string $class, int $perPage): LengthAwarePaginator;

    public function pendingCount(string $class, int $id): int;

    /** @return Collection<int, FeedComment> up to $each comments before and after it, oldest first */
    public function nearbyComments(FeedComment $comment, int $each): Collection;

    /** @return Collection<int, ModerationRecord> oldest first, with the admin who decided */
    public function history(string $class, int $id): Collection;

    /** Hides or shows the target to readers; a review is hidden by unpublishing it. */
    public function setHidden(Model $target, bool $hidden, int $actorId, string $reason): void;

    /** Closes $report and every pending report of the same content with the admin's decision. */
    public function resolve(FeedReport $report, string $action, int $reviewerId): void;

    /** @param  array<string, mixed>  $attributes */
    public function record(array $attributes): ModerationRecord;
}
