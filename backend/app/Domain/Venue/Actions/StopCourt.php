<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Repositories\OperationalStatusRepositoryInterface;
use App\Domain\Venue\DTOs\OperationalStopData;
use App\Domain\Venue\Rules\LifecycleGuard;
use App\Events\CourtStatusUpdated;
use App\Models\AffectedReservationCase;
use App\Models\Court;
use App\Models\OperationalStop;
use App\Services\Reservation\AffectedCaseOpener;
use App\Services\Reservation\AffectedReservationQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * D5: a maintenance or business pause, or a closure, of one court. A stop that has started sets the court status
 * now; a later one is applied by ApplyStartedOperationalStops. PauseRequest has already limited staff to maintenance.
 * D7: bookings the stop touches need the acknowledgement, and each gets a pending case.
 */
class StopCourt
{
    public function __construct(
        private readonly OperationalStatusRepositoryInterface $statuses,
        private readonly AffectedReservationQuery $affected,
        private readonly AffectedCaseOpener $cases,
    ) {}

    public function execute(int $courtId, OperationalStopData $data): OperationalStop
    {
        return DB::transaction(function () use ($courtId, $data): OperationalStop {
            [$court, $target, $affected] = $this->prepare($courtId, $data);
            $this->cases->assertAcknowledged($affected, $data->acknowledgeAffected);

            $stop = $this->statuses->createStop($data->stopAttributes((int) $court->venue_id, (int) $court->id));
            $this->cases->open($affected['reservations'], AffectedReservationCase::SOURCE_OPERATIONAL_STOP, (int) $stop->id, (int) $court->venue_id, $data->startsAt->toMutable());
            $startsNow = $data->startsNow();
            if ($startsNow) {
                $this->statuses->setStatus($court, $target);
            }
            DB::afterCommit(function () use ($court, $stop, $startsNow, $target): void {
                Cache::increment('courts_version');
                if ($startsNow) {
                    CourtStatusUpdated::dispatch((int) $court->venue_id, (int) $court->id, $target, [
                        'operational_stop_id' => $stop->id,
                        'kind' => $stop->kind,
                    ]);
                }
            });

            return $stop->setRelation('court', $court);
        });
    }

    /** D7 preview: the same checks and affected list as the save, rolled back. */
    public function preview(int $courtId, OperationalStopData $data): array
    {
        DB::beginTransaction();
        try {
            return $this->affected->payload($this->prepare($courtId, $data)[2]);
        } finally {
            DB::rollBack();
        }
    }

    /** @return array{0: Court, 1: string, 2: array} */
    private function prepare(int $courtId, OperationalStopData $data): array
    {
        $court = $this->statuses->lockCourt($courtId) ?? abort(404, 'Sân không tồn tại.');
        $closing = $data->kind === OperationalStop::KIND_CLOSURE;
        $target = $closing ? Court::STATUS_CLOSED : Court::STATUS_PAUSED;
        LifecycleGuard::assertTransition((string) $court->status, $target);

        $group = $closing ? [OperationalStop::KIND_CLOSURE] : [OperationalStop::KIND_MAINTENANCE, OperationalStop::KIND_BUSINESS];
        if ($this->statuses->openStop((int) $court->venue_id, (int) $court->id, $group) !== null) {
            abort(409, $closing ? 'Sân đã có lịch ngừng kinh doanh.' : 'Sân đã có một lần tạm ngừng chưa kết thúc.');
        }

        return [$court, $target, $this->affected->forStop($court, $data->startsAt->toMutable())];
    }
}
