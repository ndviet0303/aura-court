<?php

namespace App\Http\Resources\Community;

use App\Models\FeedComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FeedComment */
class FeedCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'post_id' => $this->feed_post_id,
            'content' => $this->content,
            'author' => AuthorResource::make($this->user),
            'is_mine' => $this->user_id === $request->user('sanctum')?->id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
