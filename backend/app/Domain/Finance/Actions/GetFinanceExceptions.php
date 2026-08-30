<?php

namespace App\Domain\Finance\Actions;

use App\Contracts\Repositories\FinanceExceptionRepositoryInterface;
use App\Domain\Finance\DTOs\FinanceExceptionFilterData;
use App\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetFinanceExceptions
{
    public function __construct(
        private readonly FinanceExceptionRepositoryInterface $exceptions,
    ) {}

    public function execute(Organization $organization, FinanceExceptionFilterData $filter): LengthAwarePaginator
    {
        return $this->exceptions->paginateForOrganization(
            organization: $organization,
            status: $filter->status,
            severity: $filter->severity,
            search: $filter->search,
            perPage: $filter->perPage,
        );
    }
}
