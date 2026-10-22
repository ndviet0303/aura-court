<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Repositories\OperationalStopRepositoryInterface;
use App\Domain\Venue\Rules\LifecycleGuard;
use App\Events\CourtStatusUpdated;
use App\Models\Court;
use App\Models\OperationalStop;
use App\Models\Venue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * D6, run every minute: once an open stop has started, its court (or its venue, for a venue-wide stop) moves to
 * paused (maintenance, business) or closed (closure) when LifecycleGuard allows that transition. Blocking never
 * waits for this run, because eligibility reads the stops directly; a late run only delays the status label.
 * A second run finds nothing, changes nothing and emits nothing.
 */
class ApplyStartedOperationalStops
{
    private const TARGET_STATUS = [
        OperationalStop::KIND_MAINTENANCE => Venue::STATUS_PAUSED,
        OperationalStop::KIND_BUSINESS => Venue::STATUS_PAUSED,
        OperationalStop::KIND_CLOSURE => Venue::STATUS_CLOSED,
    ];

    public function __construct(private readonly OperationalStopRepositoryInterface $stops) {}

    /** @return int how many courts and venues changed status */
    public function execute(): int
    {
        $statusesByKind = array_map(
            fn (string $target): array => array_values(array_filter(Venue::STATUSES, fn (string $from): bool => LifecycleGuard::allows($from, $target))),
            self::TARGET_STATUS,
        );

        $changed = 0;
        foreach ($this->stops->startedWithObjectIn(now(), $statusesByKind) as $stop) {
            $changed += $this->apply($stop) ? 1 : 0;
        }

        return $changed;
    }

    private function apply(OperationalStop $stop): bool
    {
        $target = self::TARGET_STATUS[$stop->kind];

        // One object per transaction: a court stop locks only its court and a venue stop only its venue, so no court is locked after a venue.
        $object = DB::transaction(function () use ($stop, $target): Court|Venue|null {
            $object = $this->stops->lockStoppedObject($stop);
            if ($object === null || ! LifecycleGuard::allows((string) $object->status, $target) || ! $this->stops->isOpen($stop)) {
                return null;
            }
            $this->stops->setObjectStatus($object, $target);

            return $object;
        });

        if ($object === null) {
            return false;
        }

        if ($object instanceof Court) {
            Cache::increment('courts_version');
            CourtStatusUpdated::dispatch(
                venueId: (int) $object->venue_id,
                courtId: (int) $object->id,
                status: $target,
                metadata: ['operational_stop_id' => $stop->id, 'kind' => $stop->kind],
            );
        } else {
            Cache::increment('venues_version');
        }

        return true;
    }
}
