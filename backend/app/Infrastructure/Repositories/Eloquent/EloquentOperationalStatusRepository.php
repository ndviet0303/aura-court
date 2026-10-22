<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\OperationalStatusRepositoryInterface;
use App\Models\Court;
use App\Models\OperationalStop;
use App\Models\Venue;
use App\Models\VenueOperatingSchedule;

class EloquentOperationalStatusRepository implements OperationalStatusRepositoryInterface
{
    public function lockVenue(int $venueId): ?Venue
    {
        return Venue::query()->lockForUpdate()->find($venueId);
    }

    public function lockCourt(int $courtId): ?Court
    {
        return Court::query()->lockForUpdate()->find($courtId);
    }

    public function setStatus(Venue|Court $object, string $status): void
    {
        $object->update(['status' => $status]);
    }

    public function openStop(int $venueId, ?int $courtId, array $kinds): ?OperationalStop
    {
        // where('court_id', null) reads as IS NULL: the venue's own stops.
        return OperationalStop::query()
            ->open()
            ->where('venue_id', $venueId)
            ->where('court_id', $courtId)
            ->whereIn('kind', $kinds)
            ->orderBy('starts_at')
            ->first();
    }

    public function createStop(array $attributes): OperationalStop
    {
        return OperationalStop::create($attributes);
    }

    public function endStop(OperationalStop $stop, int $actorId): void
    {
        $stop->update(['ended_at' => now(), 'ended_by' => $actorId]);
    }

    public function venueSchedule(int $venueId): ?VenueOperatingSchedule
    {
        return VenueOperatingSchedule::query()->where('venue_id', $venueId)->where('scope_key', 'venue')->first();
    }
}
