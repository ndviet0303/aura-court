<?php

namespace App\Http\Controllers\Community;

use App\Contracts\Repositories\CommunityFeedRepositoryInterface;
use App\Domain\Community\Actions\CreateComment;
use App\Domain\Community\Actions\DeleteComment;
use App\Domain\Community\Actions\UpdateComment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Community\SaveCommentRequest;
use App\Http\Resources\Community\FeedCommentResource;
use App\Models\FeedComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** FR-114 (UC-7.8): comments on a visible post; only the author edits or deletes one. */
class FeedCommentController extends Controller
{
    public function store(SaveCommentRequest $request, int $post, CommunityFeedRepositoryInterface $feed, CreateComment $action): JsonResponse
    {
        $found = $feed->findVisible($post) ?? abort(404, 'Bài viết không tồn tại hoặc đã bị ẩn.');

        return response()->json(['comment' => FeedCommentResource::make($action->execute($found, $request->user(), (string) $request->validated('content')))], 201);
    }

    public function update(SaveCommentRequest $request, FeedComment $comment, UpdateComment $action): JsonResponse
    {
        return response()->json(['comment' => FeedCommentResource::make($action->execute($comment, $request->user(), (string) $request->validated('content')))]);
    }

    public function destroy(Request $request, FeedComment $comment, DeleteComment $action): Response
    {
        $action->execute($comment, $request->user());

        return response()->noContent();
    }
}
