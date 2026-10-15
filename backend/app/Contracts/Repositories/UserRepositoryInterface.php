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
}
