<?php

namespace App\Contracts\Repositories;

use App\Models\RecurringSeries;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface RecurringContractRepositoryInterface
{
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function findById(int $id): ?RecurringSeries;

    /**
     * Contracts an admin cancelled whose released future sessions still hold their reservation (pre-fix admin path).
     *
     * @return Collection<int, RecurringSeries>
     */
    public function legacyCancelRepairCandidates(): Collection;

    public function getStats(?int $venueId = null): array;
}
