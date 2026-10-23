<?php

namespace App\Http\Controllers\Review;

use App\Contracts\Repositories\ReviewRepositoryInterface;
use App\Domain\Review\Actions\CreateReview;
use App\Domain\Review\Actions\DeleteReview;
use App\Domain\Review\Actions\ReplyToReview;
use App\Domain\Review\Actions\UpdateReview;
use App\Domain\Review\DTOs\ReviewData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\ReplyReviewRequest;
use App\Http\Requests\Review\SaveReviewRequest;
use App\Http\Resources\Review\ReviewResource;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** FR-115..FR-117 (UC-7.10–UC-7.12): venue reviews (D15) and the owner's reply. */
class ReviewController extends Controller
{
    public function index(int $venue, ReviewRepositoryInterface $reviews): JsonResponse
    {
        $page = $reviews->paginateForVenue($venue, 10);

        return response()->json([
            'data' => ReviewResource::collection($page->items()),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'summary' => $reviews->summary($venue),
        ]);
    }

    public function store(SaveReviewRequest $request, CreateReview $action): JsonResponse
    {
        $review = $action->execute($request->user(), (string) $request->validated('reservation_code'), ReviewData::from($request));

        return response()->json(['review' => ReviewResource::make($review)], 201);
    }

    public function update(SaveReviewRequest $request, Review $review, UpdateReview $action): JsonResponse
    {
        return response()->json(['review' => ReviewResource::make($action->execute($review, $request->user(), ReviewData::from($request)))]);
    }

    public function destroy(Request $request, Review $review, DeleteReview $action): Response
    {
        $action->execute($review, $request->user());

        return response()->noContent();
    }

    public function reply(ReplyReviewRequest $request, Review $review, ReplyToReview $action): JsonResponse
    {
        return response()->json(['review' => ReviewResource::make($action->execute($review, $request->user(), (string) $request->validated('body')))]);
    }
}
