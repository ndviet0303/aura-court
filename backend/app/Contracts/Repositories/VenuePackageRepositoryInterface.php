<?php

namespace App\Contracts\Repositories;

use App\Domain\Package\Enums\PackageStatus;
use App\Models\VenuePackage;
use Carbon\CarbonInterface;

interface VenuePackageRepositoryInterface
{
    /** The venue's current package: its row with the largest id (D1). */
    public function findCurrentForVenue(int $venueId): ?VenuePackage;

    public function statusAt(int $venueId, CarbonInterface $at): PackageStatus;
}
