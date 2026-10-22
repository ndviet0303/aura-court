<?php

namespace App\Contracts\Repositories;

use App\Models\BookingAttendance;
use App\Models\Court;
use App\Models\Reservation;
use Carbon\CarbonInterface;

/** Module 06 D3: the attendance row is the operational state of a booking; reservations.status stays confirmed. */
interface BookingAttendanceRepositoryInterface
{
    /**
     * D7: a booking of the organization by its code, with or without the AURA_BOOKING_ prefix of its QR, or by the
     * code of the V1 booking it mirrors, with its court, venue, slots, booker and attendance. $lock locks the booking
     * row so commands on one booking run one at a time.
     */
    public function findByCode(string $code, int $organizationId, bool $lock = false): ?Reservation;

    /**
     * Creates or updates the booking's attendance row and writes its projection to reservations.metadata.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function record(Reservation $reservation, array $attributes): BookingAttendance;

    /** D6: a court serves when it is active and no stop of the court or its venue covers $at. */
    public function courtInService(Court $court, CarbonInterface $at): bool;
}
