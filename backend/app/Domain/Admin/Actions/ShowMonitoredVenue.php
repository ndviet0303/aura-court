<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\VenueMonitoringRepositoryInterface;
use App\Models\User;
use App\Models\Venue;

/** D13 monitoring detail, read-only; each view leaves an audit row with the admin, the venue and the time. */
class ShowMonitoredVenue
{
    public function __construct(
        private readonly VenueMonitoringRepositoryInterface $venues,
        private readonly DurableNotificationServiceInterface $notifications,
    ) {}

    public function execute(User $admin, int $venueId): Venue
    {
        $venue = $this->venues->detail($venueId);
        $this->notifications->recordAudit($admin->id, 'venue.monitoring.viewed', $venue);

        return $venue;
    }
}
