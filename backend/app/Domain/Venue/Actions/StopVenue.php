<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Repositories\CourtRepositoryInterface;
use App\Contracts\Repositories\OperationalStatusRepositoryInterface;
use App\Domain\Venue\DTOs\OperationalStopData;
use App\Domain\Venue\Rules\LifecycleGuard;
use App\Models\OperationalStop;
use App\Models\Venue;
use App\Services\Reservation\CourtCommitmentConflictService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * D5: the owner pauses (business) or closes a whole venue. The stop blocks every court of the venue from its start,
 * without changing any court's status. A stop that has started sets the venue status now; a later one is applied
 * by ApplyStartedOperationalStops.
 */
class StopVenue
{
    public function __construct(
        private readonly OperationalStatusRepositoryInterface $statuses,
        private readonly CourtRepositoryInterface $courts,
        private readonly CourtCommitmentConflictService $commitments,
    ) {}

    public function execute(int $venueId, OperationalStopData $data): OperationalStop
    {
        return DB::transaction(function () use ($venueId, $data): OperationalStop {
            $venue = $this->statuses->lockVenue($venueId) ?? abort(404, 'Cơ sở không tồn tại.');
            $closing = $data->kind === OperationalStop::KIND_CLOSURE;
            $target = $closing ? Venue::STATUS_CLOSED : Venue::STATUS_PAUSED;
            LifecycleGuard::assertTransition((string) $venue->status, $target);

            $group = $closing ? [OperationalStop::KIND_CLOSURE] : [OperationalStop::KIND_MAINTENANCE, OperationalStop::KIND_BUSINESS];
            if ($this->statuses->openStop((int) $venue->id, null, $group) !== null) {
                abort(409, $closing ? 'Cơ sở đã có lịch ngừng kinh doanh.' : 'Cơ sở đã có một lần tạm ngừng chưa kết thúc.');
            }
            // A plain read: a venue command never locks a court after its venue.
            $this->commitments->assertStopPreservesCommitments($this->courts->getByVenue((int) $venue->id)->modelKeys(), $data->startsAt);

            $stop = $this->statuses->createStop($data->stopAttributes((int) $venue->id, null));
            if ($data->startsNow()) {
                $this->statuses->setStatus($venue, $target);
            }
            DB::afterCommit(fn () => Cache::increment('venues_version'));

            return $stop->setRelation('venue', $venue);
        });
    }
}
