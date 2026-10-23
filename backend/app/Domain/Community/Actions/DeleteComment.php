<?php

namespace App\Domain\Community\Actions;

use App\Contracts\Repositories\CommunityFeedRepositoryInterface;
use App\Domain\Community\Rules\CommunityAccess;
use App\Models\FeedComment;
use App\Models\User;

/** FR-114, BR-144: only the comment's author deletes it; the post's author has no say over it. */
class DeleteComment
{
    public function __construct(private readonly CommunityFeedRepositoryInterface $feed) {}

    public function execute(FeedComment $comment, User $actor): void
    {
        CommunityAccess::authorOrFail((int) $comment->user_id, $actor);
        $this->feed->deleteComment($comment);
    }
}
