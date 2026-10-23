<?php

namespace App\Domain\Review\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\ReviewRepositoryInterface;
use App\Domain\Community\Rules\CommunityAccess;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * FR-117: the owner of the organization that runs the venue replies and may rewrite the reply; the reviewer gets an
 * in-app notice of the first reply (D11). The reply never changes the review itself.
 */
class ReplyToReview
{
    public function __construct(
        private readonly ReviewRepositoryInterface $reviews,
        private readonly DurableNotificationServiceInterface $notifications,
    ) {}

    public function execute(Review $review, User $owner, string $body): Review
    {
        if ($review->venue === null || ! $owner->canManageVenue($review->venue)) {
            CommunityAccess::fail('REVIEW_REPLY_FORBIDDEN', 'Chỉ chủ cơ sở được phản hồi đánh giá tại cơ sở của mình.', 403);
        }
        $review = $this->reviews->update($review, ['reply_body' => $body, 'replied_by' => $owner->id, 'replied_at' => now()]);
        $this->notifications->recordOutboxNotification(
            // Only the first reply notifies; rewriting it later does not ping the reviewer again.
            key: "review.reply.{$review->id}",
            userId: (int) $review->user_id,
            eventType: 'review.reply',
            payload: [
                'title' => 'Cơ sở đã phản hồi đánh giá',
                'body' => "{$review->venue->name}: ".Str::limit($body, 80),
                'action_url' => "/courts/{$review->court_id}#reviews",
                'aggregate_type' => Review::class,
                'aggregate_id' => $review->id,
            ],
        );

        return $review;
    }
}
