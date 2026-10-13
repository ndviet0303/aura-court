<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\RefundRepositoryInterface;
use App\Models\Refund;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentRefundRepository implements RefundRepositoryInterface
{
    public function findById(int $id): ?Refund
    {
        return Refund::find($id);
    }

    public function update(Refund $refund, array $attributes): bool
    {
        return $refund->update($attributes);
    }

    public function paginatePending(int $perPage = 30): LengthAwarePaginator
    {
        return Refund::with(['booking.user', 'booking.court'])
            ->where('status', 'pending')
            ->latest()
            ->paginate($perPage);
    }

    public function paginateHistory(int $perPage = 30): LengthAwarePaginator
    {
        return Refund::with(['booking.user', 'booking.court'])
            ->latest()
            ->paginate($perPage);
    }
}
