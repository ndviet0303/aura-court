<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\ContentModerationRepositoryInterface;
use App\Domain\Community\Actions\ModerateReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListContentReportsRequest;
use App\Http\Requests\Admin\ModerateContentRequest;
use App\Http\Resources\Community\AuthorResource;
use App\Http\Resources\Community\ContentReportResource;
use App\Http\Resources\Community\FeedCommentResource;
use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\FeedReport;
use App\Models\ModerationRecord;
use App\Models\Review;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

/** FR-132, BR-160 (UC-7.16): only the platform admin reviews reports and hides, restores or keeps content (D14). */
class ContentModerationController extends Controller
{
    public function __construct(private readonly ContentModerationRepositoryInterface $moderation) {}

    public function index(ListContentReportsRequest $request): JsonResponse
    {
        $status = $request->validated('status', FeedReport::STATUS_PENDING);
        $type = $request->validated('type');
        $page = $this->moderation->paginateReports($status === 'all' ? null : $status, $type ? FeedReport::TYPES[$type] : null, 20);

        return response()->json([
            'data' => ContentReportResource::collection($page->items()),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function show(FeedReport $report): JsonResponse
    {
        $report->load(['reporter:id,name', 'reviewer:id,name', 'reportable']);
        $target = $this->moderation->findTarget($report->reportable_type, (int) $report->reportable_id);

        return response()->json([
            'report' => ContentReportResource::make($report),
            'target' => $target === null ? null : $this->target($report, $target),
            'context' => $target === null ? (object) [] : $this->context($target),
            'pending_reports' => $this->moderation->pendingCount($report->reportable_type, (int) $report->reportable_id),
            'history' => $this->moderation->history($report->reportable_type, (int) $report->reportable_id)->map(fn (ModerationRecord $record) => [
                'action' => $record->action,
                'reason' => $record->reason,
                'actor' => $record->actor ? ['id' => $record->actor->id, 'name' => $record->actor->name] : null,
                'created_at' => $record->created_at->toIso8601String(),
            ])->values(),
        ]);
    }

    public function hide(ModerateContentRequest $request, FeedReport $report, ModerateReport $action): JsonResponse
    {
        return $this->decide($request, $report, $action, ModerateReport::HIDE);
    }

    public function restore(ModerateContentRequest $request, FeedReport $report, ModerateReport $action): JsonResponse
    {
        return $this->decide($request, $report, $action, ModerateReport::RESTORE);
    }

    public function keep(ModerateContentRequest $request, FeedReport $report, ModerateReport $action): JsonResponse
    {
        return $this->decide($request, $report, $action, ModerateReport::KEEP);
    }

    private function decide(ModerateContentRequest $request, FeedReport $report, ModerateReport $action, string $decision): JsonResponse
    {
        $report = $action->execute($report, $request->user(), $decision, (string) $request->validated('note'));

        return response()->json(['report' => ContentReportResource::make($report->load(['reporter:id,name', 'reviewer:id,name', 'reportable']))]);
    }

    /** @return array<string, mixed> */
    private function target(FeedReport $report, Model $target): array
    {
        $review = $target instanceof Review;

        return [
            'type' => array_search($report->reportable_type, FeedReport::TYPES, true) ?: null,
            'id' => $target->getKey(),
            'author' => AuthorResource::make($target->getRelationValue('user')),
            'content' => $review ? $target->body : $target->getAttribute('content'),
            'images' => $target instanceof FeedComment ? [] : ($target->getAttribute('images') ?? []),
            'rating' => $review ? $target->rating : null,
            'is_hidden' => $review ? ! $target->is_published : (bool) $target->getAttribute('is_hidden'),
            'created_at' => $target->getAttribute('created_at')?->toIso8601String(),
        ];
    }

    /** BR-160: enough surroundings to judge the content — its post and nearby comments, or the reviewed venue. */
    private function context(Model $target): object
    {
        if ($target instanceof FeedComment) {
            $post = $target->post;

            return (object) [
                'post' => $post instanceof FeedPost ? ['id' => $post->id, 'content' => $post->content, 'author' => AuthorResource::make($post->user)] : null,
                'comments' => FeedCommentResource::collection($this->moderation->nearbyComments($target, 3)),
            ];
        }
        if ($target instanceof Review) {
            return (object) [
                'venue' => $target->venue ? ['id' => $target->venue->id, 'name' => $target->venue->name] : null,
                'reservation_code' => $target->reservation()->withoutGlobalScope('tenant_isolation')->value('code'),
            ];
        }

        return (object) [];
    }
}
