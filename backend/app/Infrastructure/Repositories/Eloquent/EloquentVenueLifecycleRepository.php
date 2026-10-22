<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\VenueLifecycleRepositoryInterface;
use App\Models\Venue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentVenueLifecycleRepository implements VenueLifecycleRepositoryInterface
{
    public function findById(int $id): ?Venue
    {
        return Venue::query()
            ->with(['organization', 'courts'])
            ->find($id);
    }

    public function findByIdWithLock(int $id): ?Venue
    {
        return Venue::query()
            ->lockForUpdate()
            ->with(['organization', 'courts'])
            ->find($id);
    }

    public function createVenue(array $attributes): Venue
    {
        return Venue::create($attributes);
    }

    public function updateVenue(Venue $venue, array $attributes): Venue
    {
        $venue->update($attributes);

        return $venue->fresh(['organization', 'courts']);
    }

    public function paginateByOrganization(int $organizationId, array $venueIds, int $perPage = 15): LengthAwarePaginator
    {
        return Venue::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $venueIds)
            ->with('courts')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
