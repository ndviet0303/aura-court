<?php

namespace App\Contracts\Repositories;

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PaymentRepositoryInterface
{
    public function findById(int $id): ?Payment;

    public function update(Payment $payment, array $attributes): bool;

    public function paginateManualReview(int $perPage = 30): LengthAwarePaginator;
}
