<?php

namespace App\Http\Controllers\Community;

use App\Contracts\Repositories\CommunityFeedRepositoryInterface;
use App\Domain\Community\Actions\CreatePost;
use App\Domain\Community\Actions\DeletePost;
use App\Domain\Community\Actions\UpdatePost;
use App\Domain\Community\DTOs\PostData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Community\SavePostRequest;
use App\Http\Resources\Community\FeedPostResource;
use App\Models\FeedPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** FR-112, FR-113 (UC-7.4–UC-7.7): the platform-wide feed (D12); reading needs no account, writing does. */
class FeedPostController extends Controller
{
    public function __construct(private readonly CommunityFeedRepositoryInterface $feed) {}

    public function index(Request $request): JsonResponse
    {
        $type = in_array($request->query('type'), [FeedPost::TYPE_SHARE, FeedPost::TYPE_MATCHMAKING], true) ? $request->query('type') : null;
        $page = $this->feed->paginate($type, 20);

        return response()->json([
            'data' => FeedPostResource::collection($page->items()),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function show(int $post): JsonResponse
    {
        $found = $this->feed->findVisible($post) ?? abort(404, 'Bài viết không tồn tại hoặc đã bị ẩn.');

        return response()->json(['post' => FeedPostResource::make($found)]);
    }

    public function store(SavePostRequest $request, CreatePost $action): JsonResponse
    {
        return response()->json(['post' => FeedPostResource::make($action->execute($request->user(), PostData::from($request)))], 201);
    }

    public function update(SavePostRequest $request, FeedPost $post, UpdatePost $action): JsonResponse
    {
        return response()->json(['post' => FeedPostResource::make($action->execute($post, $request->user(), PostData::from($request)))]);
    }

    public function destroy(Request $request, FeedPost $post, DeletePost $action): Response
    {
        $action->execute($post, $request->user());

        return response()->noContent();
    }
}
