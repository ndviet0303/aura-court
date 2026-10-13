<?php

namespace App\Contracts\Repositories;

use App\Models\Refund;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RefundRepositoryInterface
{
    public function findById(int $id): ?Refund;

    public function update(Refund $refund, array $attributes): bool;

    public function paginatePending(int $perPage = 30): LengthAwarePaginator;

    public function paginateHistory(int $perPage = 30): LengthAwarePaginator;
}
