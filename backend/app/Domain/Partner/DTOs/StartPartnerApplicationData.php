<?php

namespace App\Domain\Partner\DTOs;

use App\Http\Requests\Partner\StartPartnerApplicationRequest;

readonly class StartPartnerApplicationData
{
    public function __construct(
        public int $userId,
        public ?int $parentKycId = null,
    ) {}

    public static function from(StartPartnerApplicationRequest $request): self
    {
        return new self(
            userId: (int) $request->user()->id,
            parentKycId: $request->filled('parent_kyc_id') ? (int) $request->input('parent_kyc_id') : null,
        );
    }
}
