<?php

namespace App\Domain\Review\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/** FR-116, BR-145: 1–5 stars, a comment up to 2,000 characters and up to 5 uploaded images. */
readonly class ReviewData
{
    /** @param  list<string>  $images */
    public function __construct(public int $rating, public ?string $body, public array $images) {}

    public static function from(FormRequest $request): self
    {
        $body = trim((string) $request->validated('body'));

        return new self((int) $request->validated('rating'), $body === '' ? null : $body, array_values((array) $request->validated('images', [])));
    }

    /** @return array{rating: int, body: ?string, images: list<string>} */
    public function attributes(): array
    {
        return ['rating' => $this->rating, 'body' => $this->body, 'images' => $this->images];
    }
}
