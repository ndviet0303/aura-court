<?php

namespace App\Domain\Finance\DTOs;

use App\Http\Requests\Finance\ResolveFinanceExceptionRequest;

readonly class ResolveFinanceExceptionData
{
    public function __construct(
        public int $exceptionId,
        public int $userId,
        public string $resolution,
        public string $actionType,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $evidenceReference = null,
    ) {}

    public static function fromRequest(ResolveFinanceExceptionRequest $request, int $exceptionId): self
    {
        return new self(
            exceptionId: $exceptionId,
            userId: (int) $request->user()->id,
            resolution: (string) $request->validated('resolution'),
            actionType: (string) $request->validated('action_type', 'manual_override'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            evidenceReference: $request->validated('evidence_reference') ?? $request->input('evidence_reference'),
        );
    }
}
