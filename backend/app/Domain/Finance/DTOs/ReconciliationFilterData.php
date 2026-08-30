<?php

namespace App\Domain\Finance\DTOs;

use App\Http\Requests\Finance\ReconciliationFilterRequest;

readonly class ReconciliationFilterData
{
    public function __construct(
        public ?string $status = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?string $channel = null,
        public ?string $search = null,
        public int $perPage = 15
    ) {}

    public static function fromRequest(ReconciliationFilterRequest $request): self
    {
        return new self(
            status: $request->validated('status'),
            dateFrom: $request->validated('date_from'),
            dateTo: $request->validated('date_to'),
            channel: $request->validated('channel'),
            search: $request->validated('search'),
            perPage: (int) $request->validated('per_page', 15)
        );
    }
}
