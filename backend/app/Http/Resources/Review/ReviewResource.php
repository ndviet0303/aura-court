<?php

namespace App\Http\Resources\Review;

use App\Http\Resources\Community\AuthorResource;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * FR-115..FR-117: a venue review with its owner's reply; editable_until is the BR-147 edit deadline.
 *
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'body' => $this->body,
            'images' => $this->images ?? [],
            'author' => AuthorResource::make($this->user),
            'venue_id' => $this->venue_id,
            'court' => $this->court ? ['id' => $this->court->id, 'code' => $this->court->code] : null,
            'reservation_code' => $this->reservation?->code,
            'reply' => $this->reply_body === null ? null : ['body' => $this->reply_body, 'replied_at' => $this->replied_at?->toIso8601String()],
            'is_mine' => $this->user_id === $request->user('sanctum')?->id,
            'editable_until' => $this->created_at?->copy()->addDays(Review::WINDOW_DAYS)->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
