<?php

namespace App\Contracts\Repositories;

use App\Domain\Package\DTOs\PlatformFeeVenueFilterData;
use App\Models\Venue;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PlatformFeeVenueRepositoryInterface
{
    /**
     * D15: every venue with `organization.owner` and `currentPackage` loaded, soonest package end first. The status and
     * expiring filters mirror VenuePackage::statusAt() on the venue's current package (D1) at $now.
     * D14: an export reads this same query.
     *
     * @return LengthAwarePaginator<int, Venue>
     */
    public function paginate(PlatformFeeVenueFilterData $filter, CarbonInterface $now): LengthAwarePaginator;

    /**
     * The same query as paginate() with every matching venue, for the export (D14).
     *
     * @return Collection<int, Venue>
     */
    public function all(PlatformFeeVenueFilterData $filter, CarbonInterface $now): Collection;

    /**
     * BR-116: distinct venues whose non-terminated current package ends within each group, 0 < ends_at − now ≤ n days.
     *
     * @return array{7: int, 3: int, 1: int}
     */
    public function expiringCounts(CarbonInterface $now): array;

    /** A venue with `organization.owner`, `currentPackage`, its packages and its requests, newest first. */
    public function findWithHistory(int $venueId): ?Venue;
}
