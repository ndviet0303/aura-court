<?php

namespace App\Domain\Finance\DTOs;

use App\Http\Requests\Finance\AutoResolveReconciliationRequest;

readonly class AutoResolveReconciliationData
{
    public function __construct(
        public ?int $runId = null,
        public ?string $scope = 'safe_only',
        public ?string $notes = null
    ) {}

    public static function fromRequest(AutoResolveReconciliationRequest $request): self
    {
        return new self(
            runId: $request->validated('run_id'),
            scope: $request->validated('scope', 'safe_only'),
            notes: $request->validated('notes')
        );
    }
}
