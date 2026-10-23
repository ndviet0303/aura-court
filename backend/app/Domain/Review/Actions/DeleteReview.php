<?php

namespace App\Domain\Review\Actions;

use App\Contracts\Repositories\ReviewRepositoryInterface;
use App\Domain\Community\Rules\CommunityAccess;
use App\Models\Review;
use App\Models\User;

/** FR-116, BR-147: only the author deletes a review, at any time; the venue owner cannot (FR-117). */
class DeleteReview
{
    public function __construct(private readonly ReviewRepositoryInterface $reviews) {}

    public function execute(Review $review, User $actor): void
    {
        CommunityAccess::authorOrFail((int) $review->user_id, $actor, 'REVIEW_NOT_AUTHOR');
        $this->reviews->delete($review);
    }
}
