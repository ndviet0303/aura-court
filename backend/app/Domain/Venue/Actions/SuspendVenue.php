<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\VenueSuspensionRepositoryInterface;
use App\Models\AffectedReservationCase;
use App\Models\User;
use App\Models\VenueSuspension;
use App\Services\Reservation\AffectedCaseOpener;
use App\Services\Reservation\AffectedReservationQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * D13: a platform admin suspends a venue. The suspension is its own record: no venue or court status changes, and
 * affected bookings keep their state with a pending case (D7). Lock order: courts by id, venue, venue_suspensions.
 */
class SuspendVenue
{
    public function __construct(
        private readonly VenueSuspensionRepositoryInterface $suspensions,
        private readonly AffectedReservationQuery $affected,
        private readonly AffectedCaseOpener $cases,
        private readonly DurableNotificationServiceInterface $notifications,
    ) {}

    public function execute(User $admin, int $venueId, string $reason, bool $acknowledged): VenueSuspension
    {
        $courtIds = $this->affected->courtIds($this->suspensions->venue($venueId));

        return DB::transaction(function () use ($admin, $venueId, $reason, $acknowledged, $courtIds): VenueSuspension {
            $venue = $this->cases->lockScope($courtIds, $venueId);
            abort_if($this->suspensions->active($venueId, true) !== null, 409, 'Cơ sở đang bị đình chỉ.');
            $now = now();
            $affected = $this->affected->overlapping($courtIds, $now);
            $this->cases->assertAcknowledged($affected, $acknowledged, false);

            $suspension = $this->suspensions->create([
                'venue_id' => $venueId, 'reason' => $reason, 'suspended_by' => $admin->id, 'suspended_at' => $now,
            ]);
            $this->cases->open($affected['reservations'], AffectedReservationCase::SOURCE_VENUE_SUSPENSION, (int) $suspension->id, $venueId, $now);
            $this->notifications->recordAudit($admin->id, 'venue.suspended', $venue, [
                'suspension_id' => $suspension->id,
                'affected_reservations' => $affected['reservations']->keys()->all(),
            ]);
            $this->notifications->notifyVenueSuspended($suspension->setRelation('venue', $venue));
            // A suspended venue stops taking bookings, so cached public lists must refresh.
            DB::afterCommit(fn () => [Cache::increment('courts_version'), Cache::increment('venues_version')]);

            return $suspension->load(['suspendedBy:id,name']);
        });
    }
}
