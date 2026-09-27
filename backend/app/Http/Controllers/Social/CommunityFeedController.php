<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesV2Organization;
use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\FeedReport;
use App\Models\ModerationRecord;
use App\Models\OrganizationMember;
use App\Services\Infrastructure\IdempotencyService;
use App\Services\Infrastructure\OutboxService;
use App\Services\Social\CommunityFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityFeedController extends Controller
{
    use ResolvesV2Organization;

    public function __construct(
        private readonly CommunityFeedService $feed,
        private readonly IdempotencyService $idempotency,
        private readonly OutboxService $outbox,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $organization = $this->organization($request);
        $perPage = (int) $request->query('per_page', 20);
        $posts = $this->feed->listPosts($organization->id, $request->user(), $perPage);

        return response()->json($posts);
    }

    public function show(Request $request, FeedPost $post): JsonResponse
    {
        $organization = $this->organization($request);
        abort_unless($post->organization_id === $organization->id, 404);

        $isModerator = $request->user()->role === 'admin'
            || OrganizationMember::where('organization_id', $organization->id)
                ->where('user_id', $request->user()->id)
                ->whereIn('role', [OrganizationMember::ROLE_ORGANIZATION_OWNER, OrganizationMember::ROLE_VENUE_MANAGER])
                ->where('status', 'active')
                ->exists();
        $loaded = $this->feed->getPost($post, $request->user(), $isModerator);

        return response()->json(['post' => $loaded]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'content' => 'required|string|min:1|max:2000',
            'audience' => 'nullable|in:organization,friends',
        ]);

        return $this->idempotency->run($request, function () use ($request, $organization, $data) {
            $post = $this->feed->createPost($request->user(), $organization->id, $data);

            return response()->json([
                'message' => 'Post created successfully.',
                'post' => $post,
            ], 201);
        });
    }

    public function update(Request $request, FeedPost $post): JsonResponse
    {
        $organization = $this->organization($request);
        abort_unless($post->organization_id === $organization->id, 404);

        $data = $request->validate([
            'content' => 'required|string|min:1|max:2000',
            'audience' => 'nullable|in:organization,friends',
        ]);

        return $this->idempotency->run($request, function () use ($request, $post, $data) {
            $updated = $this->feed->updatePost($post, $request->user(), $data);

            return response()->json([
                'message' => 'Post updated successfully.',
                'post' => $updated,
            ]);
        });
    }

    public function destroy(Request $request, FeedPost $post): JsonResponse
    {
        $organization = $this->organization($request);
        abort_unless($post->organization_id === $organization->id, 404);

        $this->feed->deletePost($post, $request->user());

        return response()->json([
            'message' => 'Post deleted successfully.',
        ]);
    }

    public function storeComment(Request $request, FeedPost $post): JsonResponse
    {
        $organization = $this->organization($request);
        abort_unless($post->organization_id === $organization->id, 404);

        $data = $request->validate([
            'content' => 'required|string|min:1|max:1000',
        ]);

        return $this->idempotency->run($request, function () use ($request, $post, $data) {
            $comment = $this->feed->addComment($post, $request->user(), $data['content']);

            return response()->json([
                'message' => 'Comment added successfully.',
                'comment' => $comment,
            ], 201);
        });
    }

    public function updateComment(Request $request, FeedComment $comment): JsonResponse
    {
        $data = $request->validate([
            'content' => 'required|string|min:1|max:1000',
        ]);

        return $this->idempotency->run($request, function () use ($request, $comment, $data) {
            $updated = $this->feed->updateComment($comment, $request->user(), $data['content']);

            return response()->json([
                'message' => 'Comment updated successfully.',
                'comment' => $updated,
            ]);
        });
    }

    public function destroyComment(Request $request, FeedComment $comment): JsonResponse
    {
        $this->feed->deleteComment($comment, $request->user());

        return response()->json([
            'message' => 'Comment deleted successfully.',
        ]);
    }

    public function reactPost(Request $request, FeedPost $post): JsonResponse
    {
        $organization = $this->organization($request);
        abort_unless($post->organization_id === $organization->id, 404);
        abort_unless($this->feed->canViewPost($post, $request->user()), 404);

        $data = $request->validate([
            'type' => 'required|string|in:like,love,celebrate,fire',
        ]);

        $res = $this->feed->toggleReaction(FeedPost::class, $post->id, $request->user(), $data['type']);

        return response()->json($res);
    }

    public function reactComment(Request $request, FeedComment $comment): JsonResponse
    {
        $post = $comment->post;
        abort_unless($this->feed->canViewPost($post, $request->user()), 404);

        $data = $request->validate([
            'type' => 'required|string|in:like,love,celebrate,fire',
        ]);

        $res = $this->feed->toggleReaction(FeedComment::class, $comment->id, $request->user(), $data['type']);

        return response()->json($res);
    }

    public function reportPost(Request $request, FeedPost $post): JsonResponse
    {
        $organization = $this->organization($request);
        abort_unless($post->organization_id === $organization->id, 404);

        $data = $request->validate([
            'reason' => 'required|string|min:1|max:500',
        ]);

        return $this->idempotency->run($request, function () use ($request, $organization, $post, $data) {
            $report = $this->feed->reportContent($organization->id, $request->user(), FeedPost::class, $post->id, $data['reason']);

            return response()->json([
                'message' => 'Report submitted successfully.',
                'report' => $report,
            ], 201);
        });
    }

    public function reportComment(Request $request, FeedComment $comment): JsonResponse
    {
        $post = $comment->post;
        $data = $request->validate([
            'reason' => 'required|string|min:1|max:500',
        ]);

        return $this->idempotency->run($request, function () use ($request, $post, $comment, $data) {
            $report = $this->feed->reportContent($post->organization_id, $request->user(), FeedComment::class, $comment->id, $data['reason']);

            return response()->json([
                'message' => 'Report submitted successfully.',
                'report' => $report,
            ], 201);
        });
    }

    public function hidePost(Request $request, FeedPost $post): JsonResponse
    {
        $organization = $this->organization($request);
        abort_unless($post->organization_id === $organization->id, 404);

        $data = $request->validate([
            'reason' => 'required|string|min:1|max:1000',
        ]);

        $this->feed->hideContent($organization->id, $request->user(), FeedPost::class, $post->id, $data['reason']);

        $this->outbox->enqueue([
            'organization_id' => $organization->id,
            'message_key' => "feed_post_hidden:{$post->id}:" . now()->timestamp . ':' . uniqid(),
            'channel' => 'notification',
            'event_type' => 'feed.post_hidden',
            'aggregate_type' => FeedPost::class,
            'aggregate_id' => $post->id,
        ], [
            'user_id' => $post->user_id,
            'recipient_id' => $post->user_id,
            'post_id' => $post->id,
            'action' => 'hidden',
            'reason' => $data['reason'],
            'moderator_id' => $request->user()->id,
            'title' => 'Bài viết bị ẩn',
            'body' => 'Bài viết của bạn đã bị ẩn do vi phạm tiêu chuẩn cộng đồng.',
        ]);

        return response()->json([
            'message' => 'Post hidden by moderation.',
            'post' => $post->fresh(),
        ]);
    }

    public function restorePost(Request $request, FeedPost $post): JsonResponse
    {
        $organization = $this->organization($request);
        abort_unless($post->organization_id === $organization->id, 404);

        $data = $request->validate([
            'reason' => 'required|string|min:1|max:1000',
        ]);

        $this->feed->restoreContent($organization->id, $request->user(), FeedPost::class, $post->id, $data['reason']);

        $this->outbox->enqueue([
            'organization_id' => $organization->id,
            'message_key' => "feed_post_restored:{$post->id}:" . now()->timestamp . ':' . uniqid(),
            'channel' => 'notification',
            'event_type' => 'feed.post_restored',
            'aggregate_type' => FeedPost::class,
            'aggregate_id' => $post->id,
        ], [
            'user_id' => $post->user_id,
            'recipient_id' => $post->user_id,
            'post_id' => $post->id,
            'action' => 'restored',
            'reason' => $data['reason'],
            'moderator_id' => $request->user()->id,
            'title' => 'Bài viết được khôi phục',
            'body' => 'Bài viết của bạn đã được khôi phục.',
        ]);

        return response()->json([
            'message' => 'Post restored by moderation.',
            'post' => $post->fresh(),
        ]);
    }

    public function hideComment(Request $request, FeedComment $comment): JsonResponse
    {
        $post = $comment->post;
        $organization = $this->organization($request);
        abort_unless($post->organization_id === $organization->id, 404);

        $data = $request->validate([
            'reason' => 'required|string|min:1|max:1000',
        ]);

        $this->feed->hideContent($organization->id, $request->user(), FeedComment::class, $comment->id, $data['reason']);

        $this->outbox->enqueue([
            'organization_id' => $organization->id,
            'message_key' => "feed_comment_hidden:{$comment->id}:" . now()->timestamp . ':' . uniqid(),
            'channel' => 'notification',
            'event_type' => 'feed.comment_hidden',
            'aggregate_type' => FeedComment::class,
            'aggregate_id' => $comment->id,
        ], [
            'user_id' => $comment->user_id,
            'recipient_id' => $comment->user_id,
            'comment_id' => $comment->id,
            'action' => 'hidden',
            'reason' => $data['reason'],
            'moderator_id' => $request->user()->id,
            'title' => 'Bình luận bị ẩn',
            'body' => 'Bình luận của bạn đã bị ẩn do vi phạm tiêu chuẩn cộng đồng.',
        ]);

        return response()->json([
            'message' => 'Comment hidden by moderation.',
            'comment' => $comment->fresh(),
        ]);
    }

    public function restoreComment(Request $request, FeedComment $comment): JsonResponse
    {
        $post = $comment->post;
        $organization = $this->organization($request);
        abort_unless($post->organization_id === $organization->id, 404);

        $data = $request->validate([
            'reason' => 'required|string|min:1|max:1000',
        ]);

        $this->feed->restoreContent($organization->id, $request->user(), FeedComment::class, $comment->id, $data['reason']);

        $this->outbox->enqueue([
            'organization_id' => $organization->id,
            'message_key' => "feed_comment_restored:{$comment->id}:" . now()->timestamp . ':' . uniqid(),
            'channel' => 'notification',
            'event_type' => 'feed.comment_restored',
            'aggregate_type' => FeedComment::class,
            'aggregate_id' => $comment->id,
        ], [
            'user_id' => $comment->user_id,
            'recipient_id' => $comment->user_id,
            'comment_id' => $comment->id,
            'action' => 'restored',
            'reason' => $data['reason'],
            'moderator_id' => $request->user()->id,
            'title' => 'Bình luận được khôi phục',
            'body' => 'Bình luận của bạn đã được khôi phục.',
        ]);

        return response()->json([
            'message' => 'Comment restored by moderation.',
            'comment' => $comment->fresh(),
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        $organization = $this->organization($request);
        $status = $request->query('status', 'pending');

        $reports = FeedReport::where('organization_id', $organization->id)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with(['reporter:id,name,email', 'reportable'])
            ->latest()
            ->paginate((int) $request->query('per_page', 20));

        return response()->json($reports);
    }

    public function showReport(Request $request, FeedReport $report): JsonResponse
    {
        $organization = $this->organization($request);
        abort_unless($report->organization_id === $organization->id, 404);

        $report->load(['reporter:id,name,email', 'reportable', 'reviewer:id,name,email']);

        return response()->json(['report' => $report]);
    }

    public function dismissReport(Request $request, FeedReport $report): JsonResponse
    {
        $organization = $this->organization($request);
        abort_unless($report->organization_id === $organization->id, 404);

        $data = $request->validate([
            'reason' => 'required|string|min:5|max:1000',
        ]);

        $user = $request->user();

        $report->update([
            'status' => FeedReport::STATUS_DISMISSED,
            'action_taken' => 'dismiss',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        ModerationRecord::create([
            'organization_id' => $organization->id,
            'actor_id' => $user->id,
            'target_type' => $report->reportable_type,
            'target_id' => $report->reportable_id,
            'action' => 'keep',
            'reason' => $data['reason'],
            'metadata' => [
                'reviewed_by' => $user->name,
                'reviewed_at' => now()->toIso8601String(),
                'report_id' => $report->id,
            ],
        ]);

        $this->outbox->enqueue([
            'organization_id' => $organization->id,
            'message_key' => "feed_report_dismissed:{$report->id}:" . now()->timestamp . ':' . uniqid(),
            'channel' => 'notification',
            'event_type' => 'feed.report_dismissed',
            'aggregate_type' => FeedReport::class,
            'aggregate_id' => $report->id,
        ], [
            'user_id' => $report->reporter_id,
            'recipient_id' => $report->reporter_id,
            'report_id' => $report->id,
            'action' => 'dismiss',
            'reason' => $data['reason'],
            'title' => 'Báo cáo đã được xem xét',
            'body' => 'Báo cáo của bạn đã được xem xét và giữ lại nội dung.',
            'message' => 'Báo cáo của bạn đã được xem xét và giữ lại nội dung.',
        ]);

        return response()->json([
            'message' => 'Report dismissed successfully.',
            'report' => $report->fresh(['reporter:id,name,email', 'reportable', 'reviewer:id,name,email']),
        ]);
    }
}
