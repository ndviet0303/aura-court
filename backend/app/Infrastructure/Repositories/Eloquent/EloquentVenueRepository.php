<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\VenueRepositoryInterface;
use App\Models\Court;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Collection;

class EloquentVenueRepository implements VenueRepositoryInterface
{
    public function getActiveVenuesWithCourts(?string $sportType = null): Collection
    {
        return Venue::withCount(['courts' => fn ($q) => $q->where('status', Court::STATUS_ACTIVE)])
            ->where('status', Venue::STATUS_ACTIVE)
            ->whereDoesntHave('activeSuspension')
            ->whereHas('organization', fn ($q) => $q->where('partner_status', 'approved')->where('status', 'active'))
            ->when($sportType, function ($q, $sportType) {
                $q->whereHas('courts', fn ($c) => $c->where('sport_type', $sportType)->where('status', Court::STATUS_ACTIVE));
            })
            ->orderBy('id')
            ->get();
    }

    public function findBySlug(string $slug): ?Venue
    {
        return Venue::where('slug', $slug)
            ->where('status', Venue::STATUS_ACTIVE)
            ->whereDoesntHave('activeSuspension')
            ->whereHas('organization', fn ($q) => $q->where('partner_status', 'approved')->where('status', 'active'))
            ->with(['courts' => fn ($c) => $c->where('status', Court::STATUS_ACTIVE)])
            ->first();
    }

    public function findById(int $id): ?Venue
    {
        return Venue::find($id);
    }
}
