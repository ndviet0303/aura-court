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
