<?php

namespace App\Domain\Finance\DTOs;

use App\Http\Requests\Finance\AutoResolveReconciliationRequest;

readonly class AutoResolveReconciliationData
{
    public function __construct(
        public ?int $runId = null,
        public ?string $scope = 'safe_only',
        public ?string $notes = null,
        public ?string $evidenceReference = null,
        public ?string $correctionType = null,
        public ?array $correctionDetails = null,
    ) {}

    public static function fromRequest(AutoResolveReconciliationRequest $request): self
    {
        return new self(
            runId: $request->validated('run_id'),
            scope: $request->validated('scope', 'safe_only'),
            notes: $request->validated('notes'),
            evidenceReference: $request->validated('evidence_reference'),
            correctionType: $request->validated('correction_type'),
            correctionDetails: $request->validated('correction_details'),
        );
    }
}
