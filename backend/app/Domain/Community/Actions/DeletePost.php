<?php

namespace App\Domain\Community\Actions;

use App\Contracts\Repositories\CommunityFeedRepositoryInterface;
use App\Domain\Community\Rules\CommunityAccess;
use App\Models\FeedPost;
use App\Models\User;

/** FR-113, BR-144: only the author deletes a post; its comments go with it. */
class DeletePost
{
    public function __construct(private readonly CommunityFeedRepositoryInterface $feed) {}

    public function execute(FeedPost $post, User $actor): void
    {
        CommunityAccess::authorOrFail((int) $post->user_id, $actor);
        $this->feed->delete($post);
    }
}
