<?php

namespace App\Domain\Reservation\DTOs;

use App\Http\Requests\Court\StoreReviewRequest;

readonly class CreateReviewData
{
    public function __construct(
        public int $userId,
        public string $reservationCode,
        public int $rating,
        public ?string $body = null,
    ) {}

    public static function from(StoreReviewRequest $request): self
    {
        return new self(
            userId: (int) $request->user()->id,
            reservationCode: (string) $request->validated('reservation_code'),
            rating: (int) $request->validated('rating'),
            body: $request->validated('body'),
        );
    }
}
