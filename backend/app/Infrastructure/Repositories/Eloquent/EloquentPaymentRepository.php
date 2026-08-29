<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentPaymentRepository implements PaymentRepositoryInterface
{
    public function findById(int $id): ?Payment
    {
        return Payment::find($id);
    }

    public function createForBooking(Booking $booking, array $attributes): Payment
    {
        return $booking->payments()->create($attributes);
    }

    public function update(Payment $payment, array $attributes): bool
    {
        return $payment->update($attributes);
    }

    public function getLatestForBooking(Booking $booking): ?Payment
    {
        return $booking->payments()->latest()->first();
    }

    public function paginateManualReview(int $perPage = 30): LengthAwarePaginator
    {
        return Payment::with(['booking.user', 'booking.court'])
            ->where('status', 'manual_review')
            ->latest()
            ->paginate($perPage);
    }
}
