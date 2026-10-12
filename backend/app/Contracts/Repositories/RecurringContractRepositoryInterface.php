<?php

namespace App\Contracts\Repositories;

use App\Models\RecurringSeries;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RecurringContractRepositoryInterface
{
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function findById(int $id): ?RecurringSeries;

    public function getStats(?int $venueId = null): array;
}
