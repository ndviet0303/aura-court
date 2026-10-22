<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Repositories\CourtRepositoryInterface;
use App\Contracts\Repositories\OperationalStatusRepositoryInterface;
use App\Domain\Venue\DTOs\OperationalStopData;
use App\Domain\Venue\Rules\LifecycleGuard;
use App\Models\AffectedReservationCase;
use App\Models\OperationalStop;
use App\Models\Venue;
use App\Services\Reservation\AffectedCaseOpener;
use App\Services\Reservation\AffectedReservationQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * D5: the owner pauses (business) or closes a whole venue. The stop blocks every court of the venue from its start,
 * without changing any court's status. A stop that has started sets the venue status now; a later one is applied
 * by ApplyStartedOperationalStops. D7: the courts are locked by id before the venue, then the affected list is
 * computed, acknowledged and given one pending case per booking.
 */
class StopVenue
{
    public function __construct(
        private readonly OperationalStatusRepositoryInterface $statuses,
        private readonly CourtRepositoryInterface $courts,
        private readonly AffectedReservationQuery $affected,
        private readonly AffectedCaseOpener $cases,
    ) {}

    public function execute(int $venueId, OperationalStopData $data): OperationalStop
    {
        return DB::transaction(function () use ($venueId, $data): OperationalStop {
            [$venue, $target, $affected] = $this->prepare($venueId, $data);
            $this->cases->assertAcknowledged($affected, $data->acknowledgeAffected);

            $stop = $this->statuses->createStop($data->stopAttributes((int) $venue->id, null));
            $this->cases->open($affected['reservations'], AffectedReservationCase::SOURCE_OPERATIONAL_STOP, (int) $stop->id, (int) $venue->id, $data->startsAt->toMutable());
            if ($data->startsNow()) {
                $this->statuses->setStatus($venue, $target);
            }
            DB::afterCommit(fn () => Cache::increment('venues_version'));

            return $stop->setRelation('venue', $venue);
        });
    }

    /** D7 preview: the same checks and affected list as the save, rolled back. */
    public function preview(int $venueId, OperationalStopData $data): array
    {
        DB::beginTransaction();
        try {
            return $this->affected->payload($this->prepare($venueId, $data)[2]);
        } finally {
            DB::rollBack();
        }
    }

    /** @return array{0: Venue, 1: string, 2: array} */
    private function prepare(int $venueId, OperationalStopData $data): array
    {
        $courtIds = $this->courts->getByVenue($venueId)->modelKeys();
        sort($courtIds);
        $venue = $this->cases->lockScope($courtIds, $venueId);
        $closing = $data->kind === OperationalStop::KIND_CLOSURE;
        $target = $closing ? Venue::STATUS_CLOSED : Venue::STATUS_PAUSED;
        LifecycleGuard::assertTransition((string) $venue->status, $target);

        $group = $closing ? [OperationalStop::KIND_CLOSURE] : [OperationalStop::KIND_MAINTENANCE, OperationalStop::KIND_BUSINESS];
        if ($this->statuses->openStop((int) $venue->id, null, $group) !== null) {
            abort(409, $closing ? 'Cơ sở đã có lịch ngừng kinh doanh.' : 'Cơ sở đã có một lần tạm ngừng chưa kết thúc.');
        }

        return [$venue, $target, $this->affected->overlapping($courtIds, $data->startsAt->toMutable())];
    }
}
