<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\CourtRepositoryInterface;
use App\Models\Court;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Collection;

class EloquentCourtRepository implements CourtRepositoryInterface
{
    public function findById(int $id): ?Court
    {
        return Court::find($id);
    }

    public function findActiveWithActiveVenue(int $id): ?Court
    {
        return Court::query()
            ->whereKey($id)
            ->where('status', Court::STATUS_ACTIVE)
            ->whereHas('venue', fn ($query) => $query->where('status', Venue::STATUS_ACTIVE)->whereDoesntHave('activeSuspension'))
            ->first();
    }

    public function getByVenue(int $venueId): Collection
    {
        return Court::where('venue_id', $venueId)->get();
    }

    public function create(array $attributes): Court
    {
        return Court::create($attributes);
    }

    public function update(Court $court, array $attributes): bool
    {
        return $court->update($attributes);
    }
}
