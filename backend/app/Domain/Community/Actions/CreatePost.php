<?php

namespace App\Domain\Community\Actions;

use App\Contracts\Repositories\CommunityFeedRepositoryInterface;
use App\Domain\Community\DTOs\PostData;
use App\Models\FeedPost;
use App\Models\User;

/** FR-113, BR-144: a valid post shows at once, without review; it never creates or changes a booking (BR-146). */
class CreatePost
{
    public function __construct(private readonly CommunityFeedRepositoryInterface $feed) {}

    public function execute(User $author, PostData $data): FeedPost
    {
        return $this->feed->create(['user_id' => $author->id] + $data->attributes());
    }
}
