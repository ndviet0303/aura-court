<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Repositories\VenueSuspensionRepositoryInterface;
use App\Services\Reservation\AffectedReservationQuery;

/** D13 impact preview for a platform admin: court count and affected bookings, without customer contacts. */
class PreviewVenueSuspension
{
    public function __construct(
        private readonly VenueSuspensionRepositoryInterface $suspensions,
        private readonly AffectedReservationQuery $affected,
    ) {}

    public function execute(int $venueId): array
    {
        $venue = $this->suspensions->venue($venueId);
        $courtIds = $this->affected->courtIds($venue);

        return [
            'venue_id' => $venue->id,
            'court_count' => count($courtIds),
            'already_suspended' => $this->suspensions->active((int) $venue->id) !== null,
        ] + $this->affected->payload($this->affected->overlapping($courtIds, now()), false);
    }
}
