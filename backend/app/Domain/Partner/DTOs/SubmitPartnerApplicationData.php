<?php

namespace App\Domain\Partner\DTOs;

use App\Http\Requests\Partner\SubmitPartnerApplicationRequest;

readonly class SubmitPartnerApplicationData
{
    /**
     * @param  array<int, array{consent_type: string, terms_version: string}>  $consents
     */
    public function __construct(
        public int $userId,
        public int $kycId,
        public int $expectedVersion,
        public array $consents,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {}

    public static function from(SubmitPartnerApplicationRequest $request, int $kycId): self
    {
        return new self(
            userId: (int) $request->user()->id,
            kycId: $kycId,
            expectedVersion: (int) $request->input('expected_version'),
            consents: (array) $request->input('consents', []),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}
