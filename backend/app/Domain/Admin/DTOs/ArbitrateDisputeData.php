<?php

namespace App\Domain\Admin\DTOs;

use App\Http\Requests\Admin\ArbitrateDisputeRequest;

readonly class ArbitrateDisputeData
{
    public function __construct(
        public int $disputeId,
        public string $decision,
        public ?string $refundMethod = null,
        public int $refundAmount = 0,
        public ?string $compensationType = null,
        public ?string $compensationValue = null,
        public ?string $penaltyNotes = null,
        public int $penaltyPoints = 0,
        public ?string $notes = null,
        public ?int $reviewerId = null,
        public ?string $digitalSignatureHash = null,
    ) {}

    public static function fromRequest(ArbitrateDisputeRequest $request, int $disputeId): self
    {
        return new self(
            disputeId: $disputeId,
            decision: (string) $request->input('decision'),
            refundMethod: $request->input('refund_method'),
            refundAmount: (int) $request->input('refund_amount', 0),
            compensationType: $request->input('compensation_type'),
            compensationValue: $request->input('compensation_value'),
            penaltyNotes: $request->input('penalty_notes'),
            penaltyPoints: (int) $request->input('penalty_points', 0),
            notes: $request->input('notes'),
            reviewerId: $request->user()?->id,
            digitalSignatureHash: $request->input('digital_signature_hash') ?: ('SIG-'.strtoupper(bin2hex(random_bytes(8)))),
        );
    }
}
