<?php

namespace App\Contracts\Repositories;

use App\Models\Court;
use App\Models\OperationalStop;
use App\Models\Venue;
use App\Models\VenueOperatingSchedule;

/**
 * Status commands of venues and courts (D3, D5). Lock order (design "Kiểm eligibility dưới lock"): a court
 * command locks its court, then may lock the venue; a venue command locks only the venue, never a court after it.
 */
interface OperationalStatusRepositoryInterface
{
    public function lockVenue(int $venueId): ?Venue;

    public function lockCourt(int $courtId): ?Court;

    public function setStatus(Venue|Court $object, string $status): void;

    /**
     * The earliest open stop of the given kinds of a whole venue ($courtId null) or of one court.
     *
     * @param  list<string>  $kinds
     */
    public function openStop(int $venueId, ?int $courtId, array $kinds): ?OperationalStop;

    /** @param  array<string, mixed>  $attributes */
    public function createStop(array $attributes): OperationalStop;

    /** Stops are never deleted: ending one records the time and the actor. */
    public function endStop(OperationalStop $stop, int $actorId): void;

    /** The venue's own weekly operating hours. */
    public function venueSchedule(int $venueId): ?VenueOperatingSchedule;
}
