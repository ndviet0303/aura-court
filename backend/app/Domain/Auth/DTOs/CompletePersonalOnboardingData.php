<?php

namespace App\Domain\Auth\DTOs;

use App\Http\Requests\Auth\CompletePersonalOnboardingRequest;

readonly class CompletePersonalOnboardingData
{
    public function __construct(
        public int $userId,
        public string $accountType,
        public ?string $phone = null,
        public ?string $playLevel = null,
        public ?string $organizationName = null,
        public ?string $venueAddress = null,
        public ?int $courtsCount = null,
    ) {}

    public static function fromRequest(CompletePersonalOnboardingRequest $request): self
    {
        return new self(
            userId: (int) $request->user()->id,
            accountType: (string) $request->validated('account_type'),
            phone: $request->validated('phone'),
            playLevel: $request->validated('play_level'),
            organizationName: $request->validated('organization_name'),
            venueAddress: $request->validated('venue_address'),
            courtsCount: $request->filled('courts_count') ? (int) $request->validated('courts_count') : null,
        );
    }
}
