<?php

namespace App\Contracts\Repositories;

use App\Models\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByIdWithLock(int $id): ?User;

    public function update(User $user, array $attributes): User;
}
