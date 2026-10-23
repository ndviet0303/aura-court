<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\CommunityFeedRepositoryInterface;
use App\Models\FeedComment;
use App\Models\FeedPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentCommunityFeedRepository implements CommunityFeedRepositoryInterface
{
    private const AUTHOR = 'user:id,name,avatar_url';

    private const VENUE = 'venue:id,name,slug';

    public function paginate(?string $type, int $perPage): LengthAwarePaginator
    {
        return FeedPost::query()
            ->where('is_hidden', false)
            ->when($type, fn ($query, string $value) => $query->where('type', $value))
            ->with([self::AUTHOR, self::VENUE])
            ->withCount(['comments' => fn ($query) => $query->where('is_hidden', false)])
            ->latest()
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findVisible(int $id): ?FeedPost
    {
        return FeedPost::query()
            ->where('is_hidden', false)
            ->with([self::AUTHOR, self::VENUE, 'comments' => fn ($query) => $query->where('is_hidden', false)->with(self::AUTHOR)->oldest()->orderBy('id')])
            ->withCount(['comments' => fn ($query) => $query->where('is_hidden', false)])
            ->find($id);
    }

    public function create(array $attributes): FeedPost
    {
        return $this->loaded(FeedPost::create($attributes));
    }

    public function update(FeedPost $post, array $attributes): FeedPost
    {
        $post->update($attributes);

        return $this->loaded($post);
    }

    public function delete(FeedPost $post): void
    {
        $post->delete();
    }

    public function addComment(FeedPost $post, array $attributes): FeedComment
    {
        return $post->comments()->create($attributes)->load(self::AUTHOR);
    }

    public function updateComment(FeedComment $comment, string $content): FeedComment
    {
        $comment->update(['content' => $content]);

        return $comment->load(self::AUTHOR);
    }

    public function deleteComment(FeedComment $comment): void
    {
        $comment->delete();
    }

    private function loaded(FeedPost $post): FeedPost
    {
        return $post->load([self::AUTHOR, self::VENUE])
            ->loadCount(['comments' => fn ($query) => $query->where('is_hidden', false)]);
    }
}
