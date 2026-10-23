<?php

namespace App\Contracts\Repositories;

use App\Models\FeedComment;
use App\Models\FeedPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** FR-112..FR-114: the platform-wide feed (D12); hidden posts and comments never reach a reader. */
interface CommunityFeedRepositoryInterface
{
    /** @return LengthAwarePaginator<int, FeedPost> newest first, with author, venue and comment count */
    public function paginate(?string $type, int $perPage): LengthAwarePaginator;

    /** A visible post with its author, venue, comment count and visible comments (oldest first). */
    public function findVisible(int $id): ?FeedPost;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): FeedPost;

    /** @param  array<string, mixed>  $attributes */
    public function update(FeedPost $post, array $attributes): FeedPost;

    public function delete(FeedPost $post): void;

    /** @param  array<string, mixed>  $attributes */
    public function addComment(FeedPost $post, array $attributes): FeedComment;

    public function updateComment(FeedComment $comment, string $content): FeedComment;

    public function deleteComment(FeedComment $comment): void;
}
