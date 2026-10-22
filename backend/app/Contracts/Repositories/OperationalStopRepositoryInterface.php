<?php

namespace App\Contracts\Repositories;

use App\Models\Court;
use App\Models\OperationalStop;
use App\Models\Venue;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

interface OperationalStopRepositoryInterface
{
    /**
     * Open stops that started at or before $now while their object still has one of the statuses listed for the
     * stop's kind: the court for a court stop, the venue for a venue-wide stop. Closures come first.
     *
     * @param  array<string, list<string>>  $statusesByKind
     * @return Collection<int, OperationalStop>
     */
    public function startedWithObjectIn(CarbonInterface $now, array $statusesByKind): Collection;

    /** Locks the stop's court, or its venue for a venue-wide stop. */
    public function lockStoppedObject(OperationalStop $stop): Court|Venue|null;

    /** Re-read: true while the stop has no recorded end. */
    public function isOpen(OperationalStop $stop): bool;

    public function setObjectStatus(Court|Venue $object, string $status): void;
}
