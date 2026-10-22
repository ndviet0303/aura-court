<?php

namespace App\Contracts\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByIdWithLock(int $id): ?User;

    public function update(User $user, array $attributes): User;

    /** @return LengthAwarePaginator<int, User> */
    public function paginateForAdmin(?string $search, ?string $status, int $perPage): LengthAwarePaginator;

    /**
     * Active users holding an active organization_owner membership, each once; only that organization's when one is given.
     *
     * @return list<int>
     */
    public function activeOwnerIds(?int $organizationId = null): array;
}
