<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\VenueRepositoryInterface;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class EloquentVenueRepository implements VenueRepositoryInterface
{
    public function getActiveVenuesWithCourts(?string $sportType = null): Collection
    {
        return Venue::withCount('courts')
            ->with(['sportZones' => fn ($q) => $q->where('is_active', true)->withCount('courts')])
            ->when($sportType, function ($q, $sportType) {
                $q->whereHas('sportZones', fn ($z) => $z->where('sport_type', $sportType)->where('is_active', true));
            })
            ->orderBy('id')
            ->get();
    }

    public function findBySlug(string $slug): ?Venue
    {
        return Venue::where('slug', $slug)
            ->where('is_active', true)
            ->with(['sportZones' => fn ($q) => $q->where('is_active', true)->with(['courts' => fn ($c) => $c->where('is_active', true)->with('priceSlots')])])
            ->first();
    }

    public function findById(int $id): ?Venue
    {
        return Venue::find($id);
    }

    public function create(array $attributes): Venue
    {
        $venue = Venue::create($attributes);
        Cache::increment('venues_version');
        return $venue;
    }

    public function update(Venue $venue, array $attributes): bool
    {
        $updated = $venue->update($attributes);
        Cache::increment('venues_version');
        return $updated;
    }

    public function existsBySlug(string $slug): bool
    {
        return Venue::where('slug', $slug)->exists();
    }
}
