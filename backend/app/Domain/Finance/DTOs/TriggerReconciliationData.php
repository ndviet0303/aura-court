<?php

namespace App\Domain\Finance\DTOs;

use App\Http\Requests\Finance\TriggerReconciliationRequest;

readonly class TriggerReconciliationData
{
    public function __construct(
        public ?string $runDate = null,
        public bool $force = false
    ) {}

    public static function fromRequest(TriggerReconciliationRequest $request): self
    {
        return new self(
            runDate: $request->validated('run_date'),
            force: (bool) $request->validated('force', false)
        );
    }
}
