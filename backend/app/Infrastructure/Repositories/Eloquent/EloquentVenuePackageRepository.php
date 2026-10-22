<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\VenuePackageRepositoryInterface;
use App\Domain\Package\Enums\PackageStatus;
use App\Models\Venue;
use App\Models\VenuePackage;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentVenuePackageRepository implements VenuePackageRepositoryInterface
{
    public function findCurrentForVenue(int $venueId): ?VenuePackage
    {
        return VenuePackage::query()
            ->where('venue_id', $venueId)
            ->latest('id')
            ->first();
    }

    public function statusAt(int $venueId, CarbonInterface $at): PackageStatus
    {
        return $this->findCurrentForVenue($venueId)?->statusAt($at) ?? PackageStatus::None;
    }

    public function currentEndingBetween(CarbonInterface $after, CarbonInterface $until): Collection
    {
        // The column holds UTC wall time and the grammar binds a Carbon without converting it.
        return VenuePackage::query()
            ->with('venue')
            ->whereNull('terminated_at')
            // D4: a closed venue can never renew or take bookings again, so it gets no reminder.
            ->whereHas('venue', fn ($query) => $query->where('status', '!=', Venue::STATUS_CLOSED))
            ->where('ends_at', '>', $after->copy()->utc())
            ->where('ends_at', '<=', $until->copy()->utc())
            // D1: a row that a newer purchase of the venue superseded is no longer its package.
            ->whereNotExists(fn ($query) => $query->from('venue_packages as newer')
                ->whereColumn('newer.venue_id', 'venue_packages.venue_id')
                ->whereColumn('newer.id', '>', 'venue_packages.id'))
            ->orderBy('id')
            ->get();
    }

    public function lockVenueForPackage(int $venueId): ?Venue
    {
        return Venue::query()->whereKey($venueId)->lockForUpdate()->first();
    }

    public function create(array $attributes): VenuePackage
    {
        return VenuePackage::query()->create($attributes);
    }

    public function update(VenuePackage $package, array $attributes): VenuePackage
    {
        $package->update($attributes);

        return $package;
    }

    public function venuesWithPackages(int $organizationId): Collection
    {
        return Venue::query()
            ->where('organization_id', $organizationId)
            ->with(['currentPackage', 'packagePaymentRequests' => fn ($query) => $query->latest('id')])
            ->orderBy('id')
            ->get();
    }
}
