<?php

namespace App\Domain\Admin\DTOs;

use App\Http\Requests\Admin\ReviewHostKycRequest;

readonly class ReviewHostKycData
{
    public function __construct(
        public int $kycId,
        public string $decision, // 'approve', 'reject', 'request_supplement'
        public ?string $rejectionReason = null,
        public ?string $reviewerNotes = null,
        public ?array $checklistState = null,
        public ?int $reviewerId = null,
    ) {}

    public static function fromRequest(ReviewHostKycRequest $request, int $kycId): self
    {
        return new self(
            kycId: $kycId,
            decision: $request->validated('decision'),
            rejectionReason: $request->validated('rejection_reason'),
            reviewerNotes: $request->validated('reviewer_notes'),
            checklistState: $request->validated('checklist_state'),
            reviewerId: $request->user()?->id,
        );
    }
}
