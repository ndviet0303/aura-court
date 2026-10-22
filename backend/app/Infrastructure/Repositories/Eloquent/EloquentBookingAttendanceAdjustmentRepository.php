<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\BookingAttendanceAdjustmentRepositoryInterface;
use App\Models\BookingAttendanceAdjustment;
use Illuminate\Support\Collection;

class EloquentBookingAttendanceAdjustmentRepository implements BookingAttendanceAdjustmentRepositoryInterface
{
    public function record(array $attributes): BookingAttendanceAdjustment
    {
        return BookingAttendanceAdjustment::query()->create($attributes)->load('adjustedBy:id,name');
    }

    public function forReservation(int $reservationId): Collection
    {
        return BookingAttendanceAdjustment::query()
            ->where('reservation_id', $reservationId)
            ->with('adjustedBy:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }
}
