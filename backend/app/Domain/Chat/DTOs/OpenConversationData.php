<?php

namespace App\Domain\Chat\DTOs;

use App\Http\Requests\Chat\OpenConversationRequest;

readonly class OpenConversationData
{
    public function __construct(
        public string $type,
        public ?int $userId,
        public ?int $venueId,
        public ?int $organizationId,
    ) {}

    public static function from(OpenConversationRequest $request): self
    {
        $id = fn (string $key) => $request->validated($key) === null ? null : (int) $request->validated($key);

        return new self((string) $request->validated('type'), $id('user_id'), $id('venue_id'), $id('organization_id'));
    }
}
