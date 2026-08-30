<?php

namespace App\Domain\Finance\Actions;

use App\Contracts\Repositories\ReconciliationRepositoryInterface;
use App\Domain\Finance\DTOs\ReconciliationFilterData;
use App\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetReconciliationRuns
{
    public function __construct(
        private readonly ReconciliationRepositoryInterface $reconciliationRepository
    ) {}

    public function execute(Organization $organization, ReconciliationFilterData $filter): LengthAwarePaginator
    {
        return $this->reconciliationRepository->paginateForOrganization($organization, $filter);
    }
}
