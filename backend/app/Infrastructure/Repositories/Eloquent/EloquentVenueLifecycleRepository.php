<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\VenueLifecycleRepositoryInterface;
use App\Models\Venue;
use App\Models\VenuePublicationRevision;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentVenueLifecycleRepository implements VenueLifecycleRepositoryInterface
{
    public function findById(int $id): ?Venue
    {
        return Venue::query()
            ->with(['organization', 'courts', 'sportZones', 'currentPublicationRevision'])
            ->find($id);
    }

    public function findByIdWithLock(int $id): ?Venue
    {
        return Venue::query()
            ->lockForUpdate()
            ->with(['organization', 'courts', 'sportZones', 'currentPublicationRevision'])
            ->find($id);
    }

    public function updateStatus(Venue $venue, string $status, ?string $reason = null): Venue
    {
        $payload = ['status' => $status];
        if ($reason !== null) {
            $payload['status_reason'] = $reason;
        }
        if ($status === 'active') {
            $payload['published_at'] = now();
        }

        $venue->update($payload);

        return $venue->fresh(['organization', 'courts', 'sportZones', 'currentPublicationRevision']);
    }

    public function createVenue(array $attributes): Venue
    {
        return Venue::create($attributes);
    }

    public function updateVenue(Venue $venue, array $attributes): Venue
    {
        $venue->update($attributes);

        return $venue->fresh(['organization', 'courts', 'sportZones', 'currentPublicationRevision']);
    }

    public function createPublicationRevision(Venue $venue, array $attributes): VenuePublicationRevision
    {
        return $venue->publicationRevisions()->create($attributes);
    }

    public function findPublicationRevision(int $id): ?VenuePublicationRevision
    {
        return VenuePublicationRevision::query()
            ->with(['venue.organization', 'submitter', 'reviewer'])
            ->find($id);
    }

    public function findPublicationRevisionWithLock(int $id): ?VenuePublicationRevision
    {
        return VenuePublicationRevision::query()
            ->lockForUpdate()
            ->with(['venue.organization', 'submitter', 'reviewer'])
            ->find($id);
    }

    public function updatePublicationRevision(VenuePublicationRevision $revision, array $attributes): VenuePublicationRevision
    {
        $revision->update($attributes);

        return $revision->fresh(['venue.organization', 'submitter', 'reviewer']);
    }

    public function getLatestRevision(Venue $venue): ?VenuePublicationRevision
    {
        return $venue->publicationRevisions()->latest('revision_number')->first();
    }

    public function paginateByOrganization(int $organizationId, int $perPage = 15): LengthAwarePaginator
    {
        return Venue::query()
            ->where('organization_id', $organizationId)
            ->with(['courts', 'currentPublicationRevision'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function paginatePendingPublication(int $perPage = 15): LengthAwarePaginator
    {
        return VenuePublicationRevision::query()
            ->where('status', 'pending')
            ->with(['venue.organization', 'submitter'])
            ->orderBy('submitted_at', 'asc')
            ->paginate($perPage);
    }
}
