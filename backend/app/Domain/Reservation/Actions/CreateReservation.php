<?php

namespace App\Domain\Reservation\Actions;

use App\Models\Reservation;
use App\Services\Reservation\ReservationService;

class CreateReservation
{
    public function __construct(
        private readonly ReservationService $reservations,
    ) {}

    public function execute(int $userId, int $organizationId, array $data): Reservation
    {
        return $this->reservations->create($userId, $organizationId, $data);
    }
}
