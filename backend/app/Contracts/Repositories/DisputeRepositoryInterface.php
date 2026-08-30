<?php

namespace App\Contracts\Repositories;

use App\Models\DisputeCase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DisputeRepositoryInterface
{
    public function findById(int $id): ?DisputeCase;

    public function findByCode(string $code): ?DisputeCase;

    public function paginate(
        ?string $status = null,
        ?string $search = null,
        ?string $severity = null,
        ?string $disputeType = null,
        int $perPage = 30
    ): LengthAwarePaginator;

    public function getStats(): array;

    public function create(array $attributes): DisputeCase;

    public function update(DisputeCase $dispute, array $attributes): DisputeCase;
}
