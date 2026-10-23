<?php

namespace App\Http\Resources\Community;

use App\Models\FeedPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * FR-112, BR-146: a matchmaking post shows where and when only; it never holds slots or takes joins.
 *
 * @mixin FeedPost
 */
class FeedPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'content' => $this->content,
            'images' => $this->images ?? [],
            'sport' => $this->sport,
            'venue' => $this->venue ? ['id' => $this->venue->id, 'name' => $this->venue->name, 'slug' => $this->venue->slug] : null,
            'address' => $this->address,
            'court_code' => $this->court_code,
            'play_at' => $this->play_at?->toIso8601String(),
            'author' => AuthorResource::make($this->user),
            'comments_count' => (int) ($this->comments_count ?? 0),
            'comments' => FeedCommentResource::collection($this->whenLoaded('comments')),
            'is_mine' => $this->user_id === $request->user('sanctum')?->id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
