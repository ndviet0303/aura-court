<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Repositories\OperationalStatusRepositoryInterface;
use App\Domain\Venue\DTOs\OperationalStopData;
use App\Domain\Venue\Rules\LifecycleGuard;
use App\Events\CourtStatusUpdated;
use App\Models\Court;
use App\Models\OperationalStop;
use App\Services\Reservation\CourtCommitmentConflictService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * D5: a maintenance or business pause, or a closure, of one court. A stop that has started sets the court status
 * now; a later one is applied by ApplyStartedOperationalStops. PauseRequest has already limited staff to maintenance.
 */
class StopCourt
{
    public function __construct(
        private readonly OperationalStatusRepositoryInterface $statuses,
        private readonly CourtCommitmentConflictService $commitments,
    ) {}

    public function execute(int $courtId, OperationalStopData $data): OperationalStop
    {
        return DB::transaction(function () use ($courtId, $data): OperationalStop {
            $court = $this->statuses->lockCourt($courtId) ?? abort(404, 'Sân không tồn tại.');
            $closing = $data->kind === OperationalStop::KIND_CLOSURE;
            $target = $closing ? Court::STATUS_CLOSED : Court::STATUS_PAUSED;
            LifecycleGuard::assertTransition((string) $court->status, $target);

            $group = $closing ? [OperationalStop::KIND_CLOSURE] : [OperationalStop::KIND_MAINTENANCE, OperationalStop::KIND_BUSINESS];
            if ($this->statuses->openStop((int) $court->venue_id, (int) $court->id, $group) !== null) {
                abort(409, $closing ? 'Sân đã có lịch ngừng kinh doanh.' : 'Sân đã có một lần tạm ngừng chưa kết thúc.');
            }
            $this->commitments->assertStopPreservesCommitments([(int) $court->id], $data->startsAt);

            $stop = $this->statuses->createStop($data->stopAttributes((int) $court->venue_id, (int) $court->id));
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
}
