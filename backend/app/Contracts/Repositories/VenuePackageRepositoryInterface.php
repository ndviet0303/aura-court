<?php

namespace App\Contracts\Repositories;

use App\Domain\Package\Enums\PackageStatus;
use App\Models\Venue;
use App\Models\VenuePackage;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

interface VenuePackageRepositoryInterface
{
    /** The venue's current package: its row with the largest id (D1). */
    public function findCurrentForVenue(int $venueId): ?VenuePackage;

    public function statusAt(int $venueId, CarbonInterface $at): PackageStatus;

    /** @return Collection<int, VenuePackage> D11: each venue's current package, not terminated, venue not closed, ending in (after, until], with its venue */
    public function currentEndingBetween(CarbonInterface $after, CarbonInterface $until): Collection;

    /** Locks the venues row: the shared lock of every package change (D4, D6, D8), taken before any package row. */
    public function lockVenueForPackage(int $venueId): ?Venue;

    public function create(array $attributes): VenuePackage;

    public function update(VenuePackage $package, array $attributes): VenuePackage;

    /** @return Collection<int, Venue> every venue of the organization with currentPackage and packagePaymentRequests (newest first) loaded */
    public function venuesWithPackages(int $organizationId): Collection;
}
