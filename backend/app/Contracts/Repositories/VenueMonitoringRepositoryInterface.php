<?php

namespace App\Contracts\Repositories;

use App\Models\Venue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Read-only, cross-organization venue reads for platform monitoring (D13). */
interface VenueMonitoringRepositoryInterface
{
    /** @param  array{organization_id?: int, status?: string, suspended?: bool, keyword?: string}  $filters */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;

    /** A venue with its organization, active owners, schedules, courts and suspension history. */
    public function detail(int $id): Venue;
}
