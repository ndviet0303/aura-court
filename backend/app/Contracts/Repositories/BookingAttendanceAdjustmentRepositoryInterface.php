<?php

namespace App\Contracts\Repositories;

use App\Models\BookingAttendanceAdjustment;
use Illuminate\Support\Collection;

/** FR-109, BR-136: the append-only history of owner adjustments to a booking's operational record. */
interface BookingAttendanceAdjustmentRepositoryInterface
{
    /** @param  array<string, mixed>  $attributes */
    public function record(array $attributes): BookingAttendanceAdjustment;

    /**
     * The booking's adjustments with who made them, newest first.
     *
     * @return Collection<int, BookingAttendanceAdjustment>
     */
    public function forReservation(int $reservationId): Collection;
}
