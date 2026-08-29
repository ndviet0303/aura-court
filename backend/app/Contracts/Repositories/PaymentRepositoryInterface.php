<?php

namespace App\Contracts\Repositories;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PaymentRepositoryInterface
{
    public function findById(int $id): ?Payment;

    public function createForBooking(Booking $booking, array $attributes): Payment;

    public function update(Payment $payment, array $attributes): bool;

    public function getLatestForBooking(Booking $booking): ?Payment;

    public function paginateManualReview(int $perPage = 30): LengthAwarePaginator;
}
