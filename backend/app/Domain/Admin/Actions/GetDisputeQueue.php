<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\DisputeRepositoryInterface;
use App\Domain\Admin\DTOs\DisputeFilterData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetDisputeQueue
{
    public function __construct(
        private readonly DisputeRepositoryInterface $disputeRepository,
    ) {}

    public function execute(DisputeFilterData $filter): LengthAwarePaginator
    {
        return $this->disputeRepository->paginate(
            status: $filter->status,
            search: $filter->search,
            severity: $filter->severity,
            disputeType: $filter->disputeType,
            perPage: $filter->perPage
        );
    }
}
