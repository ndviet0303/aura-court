<?php

namespace App\Domain\Finance\DTOs;

use App\Http\Requests\Finance\ListFinanceExceptionsRequest;

readonly class FinanceExceptionFilterData
{
    public function __construct(
        public ?string $status = null,
        public ?string $severity = null,
        public ?string $search = null,
        public int $perPage = 30,
    ) {}

    public static function fromRequest(ListFinanceExceptionsRequest $request): self
    {
        return new self(
            status: $request->validated('status'),
            severity: $request->validated('severity'),
            search: $request->validated('search'),
            perPage: (int) $request->validated('per_page', 30),
        );
    }
}
