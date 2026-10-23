<?php

namespace App\Domain\Review\Actions;

use App\Contracts\Repositories\ReviewRepositoryInterface;
use App\Domain\Community\Rules\CommunityAccess;
use App\Domain\Review\DTOs\ReviewData;
use App\Models\Review;
use App\Models\User;

/** FR-116, BR-147: the author edits stars, comment and images within 7 days of the first submission; editing never extends it. */
class UpdateReview
{
    public function __construct(private readonly ReviewRepositoryInterface $reviews) {}

    public function execute(Review $review, User $actor, ReviewData $data): Review
    {
        CommunityAccess::authorOrFail((int) $review->user_id, $actor, 'REVIEW_NOT_AUTHOR');
        if (now()->greaterThan($review->created_at->copy()->addDays(Review::WINDOW_DAYS))) {
            CommunityAccess::fail('REVIEW_EDIT_CLOSED', 'Chỉ sửa được đánh giá trong 7 ngày kể từ lần gửi đầu.');
        }

        return $this->reviews->update($review, $data->attributes());
    }
}
