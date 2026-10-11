<?php

namespace App\Services\Social;

use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\FeedReaction;
use App\Models\FeedReport;
use App\Models\ModerationRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class CommunityFeedService
{
    public function __construct(
        private readonly FriendshipService $friendships
    ) {}

    /**
     * Check if a user can view a post based on audience and hidden status.
     */
    public function canViewPost(FeedPost $post, User $viewer, bool $isModerator = false): bool
    {
        if ($post->is_hidden && ! $isModerator && $post->user_id !== $viewer->id) {
            return false;
        }

        if ($post->user_id === $viewer->id) {
            return true;
        }

        if ($post->audience === FeedPost::AUDIENCE_ORGANIZATION) {
            return true;
        }

        if ($post->audience === FeedPost::AUDIENCE_FRIENDS) {
            // Controlled friends-only report review: authorized moderator can inspect reported content
            if ($isModerator && $post->reports()->exists()) {
                return true;
            }

            return $this->friendships->areFriends($post->user_id, $viewer->id);
        }

        return false;
    }

    /**
     * List posts in the organization visible to the viewer.
     */
    public function listPosts(int $organizationId, User $viewer, int $perPage = 20): LengthAwarePaginator
    {
        // Get accepted friend IDs of viewer
        $friendIds = $this->friendships->getFriends($viewer)->pluck('id')->all();

        return FeedPost::query()
            ->where('organization_id', $organizationId)
            ->where('is_hidden', false)
            ->where(function (Builder $q) use ($viewer, $friendIds) {
                // Organization posts visible to everyone in org
                $q->where('audience', FeedPost::AUDIENCE_ORGANIZATION)
                    // Or viewer's own posts
                    ->orWhere('user_id', $viewer->id)
                    // Or friends-only posts where author is an accepted friend
                    ->orWhere(function (Builder $sub) use ($friendIds) {
                        $sub->where('audience', FeedPost::AUDIENCE_FRIENDS)
                            ->whereIn('user_id', $friendIds);
                    });
            })
            ->with([
                'user:id,name,email,phone,avatar_url,play_level',
                'reactions',
                'comments' => function ($q) {
                    $q->where('is_hidden', false)
                        ->with(['user:id,name,email,phone,avatar_url,play_level', 'reactions'])
                        ->latest()
                        ->limit(5);
                },
            ])
            ->withCount([
                'comments' => fn ($q) => $q->where('is_hidden', false),
            ])
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Get a single post with audience permission check.
     */
    public function getPost(FeedPost $post, User $viewer, bool $isModerator = false): FeedPost
    {
        abort_unless($this->canViewPost($post, $viewer, $isModerator), 404, 'Post not found or access denied.');

        $post->load([
            'user:id,name,email,phone,avatar_url,play_level',
            'reactions',
            'comments' => function ($q) {
                $q->where('is_hidden', false)
                    ->with(['user:id,name,email,phone,avatar_url,play_level', 'reactions'])
                    ->oldest();
            },
        ]);

        return $post;
    }

    /**
     * Create a new feed post.
     */
    public function createPost(User $author, int $organizationId, array $data): FeedPost
    {
        $audience = $data['audience'] ?? FeedPost::AUDIENCE_ORGANIZATION;
        abort_unless(in_array($audience, [FeedPost::AUDIENCE_ORGANIZATION, FeedPost::AUDIENCE_FRIENDS], true), 422, 'Invalid audience.');

        $content = trim((string) ($data['content'] ?? ''));
        abort_if($content === '' || mb_strlen($content) > 2000, 422, 'Content must be between 1 and 2000 characters.');

        $post = FeedPost::create([
            'organization_id' => $organizationId,
            'user_id' => $author->id,
            'audience' => $audience,
            'content' => $content,
        ]);

        return $post->fresh(['user:id,name,email,phone,avatar_url,play_level']);
    }

    /**
     * Update an existing feed post (author only).
     */
    public function updatePost(FeedPost $post, User $user, array $data): FeedPost
    {
        abort_unless($post->user_id === $user->id, 403, 'Unauthorized to edit this post.');

        $content = trim((string) ($data['content'] ?? ''));
        abort_if($content === '' || mb_strlen($content) > 2000, 422, 'Content must be between 1 and 2000 characters.');

        $audience = $data['audience'] ?? $post->audience;
        abort_unless(in_array($audience, [FeedPost::AUDIENCE_ORGANIZATION, FeedPost::AUDIENCE_FRIENDS], true), 422, 'Invalid audience.');

        $post->update([
            'content' => $content,
            'audience' => $audience,
        ]);

        return $post->fresh(['user:id,name,email,phone,avatar_url,play_level']);
    }

    /**
     * Delete a feed post (author only).
     */
    public function deletePost(FeedPost $post, User $user): void
    {
        abort_unless($post->user_id === $user->id, 403, 'Unauthorized to delete this post.');

        $post->delete();
    }

    /**
     * Add comment to a post.
     */
    public function addComment(FeedPost $post, User $user, string $content): FeedComment
    {
        abort_unless($this->canViewPost($post, $user), 404, 'Post not found or access denied.');

        $content = trim($content);
        abort_if($content === '' || mb_strlen($content) > 1000, 422, 'Comment must be between 1 and 1000 characters.');

        return FeedComment::create([
            'feed_post_id' => $post->id,
            'user_id' => $user->id,
            'content' => $content,
        ])->fresh(['user:id,name,email,phone,avatar_url,play_level']);
    }

    /**
     * Update comment (author only).
     */
    public function updateComment(FeedComment $comment, User $user, string $content): FeedComment
    {
        abort_unless($comment->user_id === $user->id, 403, 'Unauthorized to edit this comment.');

        $content = trim($content);
        abort_if($content === '' || mb_strlen($content) > 1000, 422, 'Comment must be between 1 and 1000 characters.');

        $comment->update(['content' => $content]);

        return $comment->fresh(['user:id,name,email,phone,avatar_url,play_level']);
    }

    /**
     * Delete comment (author only).
     */
    public function deleteComment(FeedComment $comment, User $user): void
    {
        abort_unless($comment->user_id === $user->id, 403, 'Unauthorized to delete this comment.');

        $comment->delete();
    }

    /**
     * Toggle reaction on post or comment (once per reaction type).
     */
    public function toggleReaction(string $modelClass, int $modelId, User $user, string $type): array
    {
        abort_unless(in_array($type, FeedReaction::TYPES, true), 422, 'Invalid reaction type.');

        $existing = FeedReaction::query()
            ->where('reactable_type', $modelClass)
            ->where('reactable_id', $modelId)
            ->where('user_id', $user->id)
            ->where('reaction_type', $type)
            ->first();

        if ($existing) {
            $existing->delete();
            $action = 'removed';
        } else {
            FeedReaction::create([
                'reactable_type' => $modelClass,
                'reactable_id' => $modelId,
                'user_id' => $user->id,
                'reaction_type' => $type,
            ]);
            $action = 'added';
        }

        $reactions = FeedReaction::query()
            ->where('reactable_type', $modelClass)
            ->where('reactable_id', $modelId)
            ->get()
            ->groupBy('reaction_type')
            ->map(fn ($group) => $group->count());

        return [
            'action' => $action,
            'reaction_type' => $type,
            'counts' => $reactions,
        ];
    }

    /**
     * Report post or comment.
     */
    public function reportContent(int $organizationId, User $reporter, string $modelClass, int $modelId, string $reason): FeedReport
    {
        $reason = trim($reason);
        abort_if($reason === '' || mb_strlen($reason) > 500, 422, 'Reason must be between 1 and 500 characters.');

        return FeedReport::create([
            'organization_id' => $organizationId,
            'reporter_id' => $reporter->id,
            'reportable_type' => $modelClass,
            'reportable_id' => $modelId,
            'reason' => $reason,
            'status' => FeedReport::STATUS_PENDING,
        ]);
    }

    /**
     * Moderate content: Hide post or comment with audit reason.
     */
    public function hideContent(int $organizationId, User $moderator, string $modelClass, int $modelId, string $reason): void
    {
        $item = $modelClass::findOrFail($modelId);
        $reason = trim($reason);
        abort_if($reason === '', 422, 'Audit reason is required to hide content.');

        $item->update([
            'is_hidden' => true,
            'hidden_at' => now(),
            'hidden_by' => $moderator->id,
            'hidden_reason' => $reason,
        ]);

        ModerationRecord::create([
            'organization_id' => $organizationId,
            'actor_id' => $moderator->id,
            'target_type' => $modelClass,
            'target_id' => $modelId,
            'action' => 'content_hidden',
            'reason' => $reason,
            'metadata' => [
                'hidden_by' => $moderator->name,
                'hidden_at' => now()->toIso8601String(),
            ],
        ]);

        // Update pending reports for this item
        FeedReport::where('reportable_type', $modelClass)
            ->where('reportable_id', $modelId)
            ->where('status', FeedReport::STATUS_PENDING)
            ->update([
                'status' => FeedReport::STATUS_RESOLVED,
                'reviewed_by' => $moderator->id,
                'reviewed_at' => now(),
                'action_taken' => 'hidden',
            ]);
    }

    /**
     * Moderate content: Restore hidden post or comment with audit reason.
     */
    public function restoreContent(int $organizationId, User $moderator, string $modelClass, int $modelId, string $reason): void
    {
        $item = $modelClass::findOrFail($modelId);
        $reason = trim($reason);
        abort_if($reason === '', 422, 'Audit reason is required to restore content.');

        $item->update([
            'is_hidden' => false,
            'hidden_at' => null,
            'hidden_by' => null,
            'hidden_reason' => null,
        ]);

        ModerationRecord::create([
            'organization_id' => $organizationId,
            'actor_id' => $moderator->id,
            'target_type' => $modelClass,
            'target_id' => $modelId,
            'action' => 'content_restored',
            'reason' => $reason,
            'metadata' => [
                'restored_by' => $moderator->name,
                'restored_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
