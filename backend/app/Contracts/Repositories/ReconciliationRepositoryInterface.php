<?php

namespace App\Contracts\Repositories;

use App\Domain\Finance\DTOs\ReconciliationFilterData;
use App\Models\Organization;
use App\Models\ReconciliationRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ReconciliationRepositoryInterface
{
    public function findById(int $id): ?ReconciliationRun;

    public function findForOrganization(Organization $organization, int $id): ?ReconciliationRun;

    public function findByDateForOrganization(Organization $organization, string $runDate): ?ReconciliationRun;

    public function paginateForOrganization(
        Organization $organization,
        ReconciliationFilterData $filter
    ): LengthAwarePaginator;

    public function getStatsForOrganization(Organization $organization): array;

    public function getDetailForRun(Organization $organization, ReconciliationRun $run): array;

    public function createOrUpdateForDate(Organization $organization, string $runDate, array $attributes): ReconciliationRun;
}
