<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\BookingRepositoryInterface;
use App\Models\Booking;
use App\Models\BookingSlot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentBookingRepository implements BookingRepositoryInterface
{
    public function findById(int $id): ?Booking
    {
        return Booking::find($id);
    }

    public function findByCode(string $code): ?Booking
    {
        return Booking::where('code', $code)->first();
    }

    public function create(array $attributes): Booking
    {
        return Booking::create($attributes);
    }

    public function createSlots(Booking $booking, array $slots): void
    {
        foreach ($slots as $slot) {
            BookingSlot::create(array_merge($slot, ['booking_id' => $booking->id]));
        }
    }

    public function updateStatus(Booking $booking, string $status): bool
    {
        return $booking->update(['status' => $status]);
    }

    public function paginateUserBookings(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return Booking::where('user_id', $userId)
            ->with(['court.venue', 'slots', 'refunds', 'review'])
            ->latest()
            ->paginate($perPage);
    }

    public function paginateAdminBookings(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        return Booking::with(['user', 'court', 'slots', 'refunds'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['date'] ?? null, fn ($q, $date) => $q->whereHas('slots', fn ($s) => $s->whereDate('starts_at', $date)))
            ->when($filters['venue_id'] ?? null, fn ($q, $vId) => $q->whereHas('court', fn ($c) => $c->where('venue_id', (int) $vId)))
            ->when($filters['venue_ids'] ?? null, fn ($q, $vIds) => $q->whereHas('court', fn ($c) => $c->whereIn('venue_id', (array) $vIds)))
            ->latest()
            ->paginate($perPage);
    }
}
