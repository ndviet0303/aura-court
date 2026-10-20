<?php

namespace App\Contracts\Repositories;

use App\Models\Venue;
use App\Models\VenuePublicationRevision;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface VenueLifecycleRepositoryInterface
{
    public function findById(int $id): ?Venue;

    public function findByIdWithLock(int $id): ?Venue;

    public function updateStatus(Venue $venue, string $status, ?string $reason = null): Venue;

    public function createVenue(array $attributes): Venue;

    public function updateVenue(Venue $venue, array $attributes): Venue;

    public function createPublicationRevision(Venue $venue, array $attributes): VenuePublicationRevision;

    public function findPublicationRevision(int $id): ?VenuePublicationRevision;

    public function findPublicationRevisionWithLock(int $id): ?VenuePublicationRevision;

    public function updatePublicationRevision(VenuePublicationRevision $revision, array $attributes): VenuePublicationRevision;

    public function getLatestRevision(Venue $venue): ?VenuePublicationRevision;

    /** @param  list<int>  $venueIds  the venues the reader may see */
    public function paginateByOrganization(int $organizationId, array $venueIds, int $perPage = 15): LengthAwarePaginator;

    public function paginatePendingPublication(int $perPage = 15): LengthAwarePaginator;
}
