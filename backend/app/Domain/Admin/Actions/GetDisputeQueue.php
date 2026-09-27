<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\DisputeRepositoryInterface;
use App\Domain\Admin\DTOs\DisputeFilterData;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetDisputeQueue
{
    public function __construct(
        private readonly DisputeRepositoryInterface $disputeRepository,
    ) {}

    public function execute(
        DisputeFilterData $filter,
        ?User $user = null,
        ?int $organizationId = null,
        ?int $venueId = null
    ): LengthAwarePaginator {
        return $this->disputeRepository->paginate(
            status: $filter->status,
            search: $filter->search,
            severity: $filter->severity,
            disputeType: $filter->disputeType,
            perPage: $filter->perPage,
            user: $user,
            organizationId: $organizationId,
            venueId: $venueId
        );
    }
}
