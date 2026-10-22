<?php

namespace App\Contracts\Repositories;

use App\Models\BookingAttendance;
use App\Models\Court;
use App\Models\Reservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

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

    /** FR-108: an adjustment back to confirmed removes the attendance row and its projection. */
    public function clear(Reservation $reservation): void;

    /**
     * FR-101: the venue's confirmed bookings with time in [$from, $to), with the details of findByCode, by start.
     *
     * @return Collection<int, Reservation>
     */
    public function daySchedule(int $venueId, CarbonInterface $from, CarbonInterface $to): Collection;

    /**
     * FR-104: the venue's bookings checked in and not checked out, whatever their day, by booked end.
     *
     * @return Collection<int, Reservation>
     */
    public function inUse(int $venueId): Collection;

    /**
     * BR-131: bookings of every organization still in use whose booked end is in ($since, $until].
     *
     * @return Collection<int, Reservation>
     */
    public function inUseEndingBetween(CarbonInterface $since, CarbonInterface $until): Collection;

    /** D6: a court serves when it is active and no stop of the court or its venue covers $at. */
    public function courtInService(Court $court, CarbonInterface $at): bool;
}
