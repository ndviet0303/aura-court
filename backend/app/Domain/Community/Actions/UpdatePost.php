<?php

namespace App\Domain\Community\Actions;

use App\Contracts\Repositories\CommunityFeedRepositoryInterface;
use App\Domain\Community\DTOs\PostData;
use App\Domain\Community\Rules\CommunityAccess;
use App\Models\FeedPost;
use App\Models\User;

/** FR-113: the author edits under the same limits as posting (BR-145). */
class UpdatePost
{
    public function __construct(private readonly CommunityFeedRepositoryInterface $feed) {}

    public function execute(FeedPost $post, User $actor, PostData $data): FeedPost
    {
        CommunityAccess::authorOrFail((int) $post->user_id, $actor);

        return $this->feed->update($post, $data->attributes());
    }
}
