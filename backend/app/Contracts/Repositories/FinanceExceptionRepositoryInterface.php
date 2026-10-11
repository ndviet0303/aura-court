<?php

namespace App\Contracts\Repositories;

use App\Models\FinanceException;
use App\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface FinanceExceptionRepositoryInterface
{
    public function findById(int $id): ?FinanceException;

    public function findForOrganization(Organization $organization, int $id): ?FinanceException;

    public function paginateForOrganization(
        Organization $organization,
        ?string $status = null,
        ?string $severity = null,
        ?string $search = null,
        int $perPage = 30
    ): LengthAwarePaginator;

    public function getStatsForOrganization(Organization $organization): array;

    public function create(array $attributes): FinanceException;

    public function update(FinanceException $exception, array $attributes): FinanceException;
}
