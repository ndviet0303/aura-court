<?php

namespace App\Domain\Community\Actions;

use App\Contracts\Repositories\CommunityFeedRepositoryInterface;
use App\Domain\Community\Rules\CommunityAccess;
use App\Models\FeedComment;
use App\Models\User;

/** FR-114: the comment's author edits it, within 2,000 characters. */
class UpdateComment
{
    public function __construct(private readonly CommunityFeedRepositoryInterface $feed) {}

    public function execute(FeedComment $comment, User $actor, string $content): FeedComment
    {
        CommunityAccess::authorOrFail((int) $comment->user_id, $actor);

        return $this->feed->updateComment($comment, $content);
    }
}
