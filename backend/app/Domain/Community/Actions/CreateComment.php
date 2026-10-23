<?php

namespace App\Domain\Community\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\CommunityFeedRepositoryInterface;
use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\User;
use Illuminate\Support\Str;

/** FR-114 comment; D11: the post's author gets an in-app notice unless they commented themselves. */
class CreateComment
{
    public function __construct(
        private readonly CommunityFeedRepositoryInterface $feed,
        private readonly DurableNotificationServiceInterface $notifications,
    ) {}

    public function execute(FeedPost $post, User $author, string $content): FeedComment
    {
        $comment = $this->feed->addComment($post, ['user_id' => $author->id, 'content' => $content]);
        if ((int) $post->user_id !== (int) $author->id) {
            $this->notifications->recordOutboxNotification(
                key: "community.comment.{$comment->id}",
                userId: (int) $post->user_id,
                eventType: 'community.comment',
                payload: [
                    'title' => 'Bình luận mới',
                    'body' => "{$author->name} đã bình luận: ".Str::limit($content, 80),
                    'action_url' => "/community/posts/{$post->id}",
                    'aggregate_type' => FeedPost::class,
                    'aggregate_id' => $post->id,
                ],
            );
        }

        return $comment;
    }
}
