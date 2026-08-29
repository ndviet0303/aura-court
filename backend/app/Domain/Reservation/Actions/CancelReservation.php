<?php

namespace App\Domain\Reservation\Actions;

use App\Models\Reservation;
use App\Services\Reservation\ReservationService;

class CancelReservation
{
    public function __construct(
        private readonly ReservationService $reservations,
    ) {}

    public function execute(Reservation $reservation, int $actorId, ?string $reason = null): Reservation
    {
        return $this->reservations->cancel($reservation, $actorId, $reason);
    }
}
