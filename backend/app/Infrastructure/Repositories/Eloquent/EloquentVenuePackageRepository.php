<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\VenuePackageRepositoryInterface;
use App\Domain\Package\Enums\PackageStatus;
use App\Models\VenuePackage;
use Carbon\CarbonInterface;

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
}
