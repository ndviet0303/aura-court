<?php

namespace App\Contracts\Repositories;

use App\Models\Venue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface VenueLifecycleRepositoryInterface
{
    public function findById(int $id): ?Venue;

    public function findByIdWithLock(int $id): ?Venue;

    public function createVenue(array $attributes): Venue;

    public function updateVenue(Venue $venue, array $attributes): Venue;

    /** @param  list<int>  $venueIds  the venues the reader may see */
    public function paginateByOrganization(int $organizationId, array $venueIds, int $perPage = 15): LengthAwarePaginator;
}
