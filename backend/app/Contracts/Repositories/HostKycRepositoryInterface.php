<?php

namespace App\Contracts\Repositories;

use App\Models\HostKyc;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface HostKycRepositoryInterface
{
    public function findById(int $id): ?HostKyc;

    public function findByCode(string $code): ?HostKyc;

    public function paginate(
        ?string $status = null,
        ?string $search = null,
        int $perPage = 30
    ): LengthAwarePaginator;

    public function getStats(): array;

    public function create(array $attributes): HostKyc;

    public function update(HostKyc $kyc, array $attributes): HostKyc;
}
