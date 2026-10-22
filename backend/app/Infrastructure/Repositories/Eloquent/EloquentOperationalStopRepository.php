<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\OperationalStopRepositoryInterface;
use App\Models\Court;
use App\Models\OperationalStop;
use App\Models\Venue;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EloquentOperationalStopRepository implements OperationalStopRepositoryInterface
{
    public function startedWithObjectIn(CarbonInterface $now, array $statusesByKind): Collection
    {
        return OperationalStop::query()
            ->open()
            ->where('starts_at', '<=', $now->copy()->utc())
            ->where(function (Builder $query) use ($statusesByKind): void {
                foreach ($statusesByKind as $kind => $statuses) {
                    $query->orWhere(fn (Builder $ofKind) => $ofKind->where('kind', $kind)->where(fn (Builder $object) => $object
                        ->whereHas('court', fn (Builder $court) => $court->whereIn('status', $statuses))
                        ->orWhere(fn (Builder $venueWide) => $venueWide
                            ->whereNull('court_id')
                            ->whereHas('venue', fn (Builder $venue) => $venue->whereIn('status', $statuses)))));
                }
            })
            ->orderByRaw("kind <> 'closure'")
            ->orderBy('id')
            ->get();
    }

    public function lockStoppedObject(OperationalStop $stop): Court|Venue|null
    {
        return $stop->court_id !== null
            ? Court::query()->whereKey($stop->court_id)->lockForUpdate()->first()
            : Venue::query()->whereKey($stop->venue_id)->lockForUpdate()->first();
    }

    public function isOpen(OperationalStop $stop): bool
    {
        return OperationalStop::query()->whereKey($stop->id)->whereNull('ended_at')->exists();
    }

    public function setObjectStatus(Court|Venue $object, string $status): void
    {
        $object->update(['status' => $status]);
    }
}
