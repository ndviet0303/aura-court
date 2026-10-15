<?php

namespace App\Domain\Partner\DTOs;

use App\Http\Requests\Partner\ReviewPartnerApplicationRequest;

readonly class ReviewPartnerApplicationData
{
    public function __construct(
        public int $reviewerId,
        public int $kycId,
        public string $decision,
        public ?string $reason = null,
        public ?string $reviewerNotes = null,
        public ?array $feedbackFields = null,
        public int $expectedVersion = 1,
    ) {}

    public static function from(ReviewPartnerApplicationRequest $request, int $kycId): self
    {
        return new self(
            reviewerId: (int) $request->user()->id,
            kycId: $kycId,
            decision: (string) $request->input('decision'),
            reason: $request->input('reason'),
            reviewerNotes: $request->input('reviewer_notes'),
            feedbackFields: $request->input('feedback_fields'),
            expectedVersion: (int) $request->integer('expected_version', 1),
        );
    }

    public static function fromRequest(ReviewPartnerApplicationRequest $request, int $kycId): self
    {
        return self::from($request, $kycId);
    }
}
