<?php

namespace App\Domain\Admin\DTOs;

use App\Http\Requests\Admin\ListDisputeRequest;

readonly class DisputeFilterData
{
    public function __construct(
        public ?string $status = null,
        public ?string $search = null,
        public ?string $severity = null,
        public ?string $disputeType = null,
        public int $perPage = 30,
        public int $page = 1,
    ) {}

    public static function fromRequest(ListDisputeRequest $request): self
    {
        return new self(
            status: $request->query('status'),
            search: $request->query('search'),
            severity: $request->query('severity'),
            disputeType: $request->query('dispute_type'),
            perPage: (int) $request->query('per_page', 30),
            page: (int) $request->query('page', 1),
        );
    }
}
