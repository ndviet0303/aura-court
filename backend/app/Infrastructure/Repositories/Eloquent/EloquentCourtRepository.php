<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\CourtRepositoryInterface;
use App\Models\Court;
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
            ->where('is_active', true)
            ->whereHas('venue', fn ($query) => $query->where('is_active', true))
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
