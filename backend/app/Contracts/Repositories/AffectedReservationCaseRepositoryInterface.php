<?php

namespace App\Contracts\Repositories;

use App\Models\AffectedReservationCase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AffectedReservationCaseRepositoryInterface
{
    /**
     * Cases at the given venues, newest first. Without a status, or with an open one, cases of bookings that are
     * cancelled, expired, completed, no longer held before their deadline, or finished on site are left out.
     *
     * @param  array{venue_ids: list<int>, court_id?: ?int, source_type?: ?string, status?: ?string}  $filters
     */
    public function paginate(array $filters, int $perPage = 30): LengthAwarePaginator;

    public function find(int $id, bool $lock = false): ?AffectedReservationCase;

    /** @param  array<string, mixed>  $attributes */
    public function update(AffectedReservationCase $case, array $attributes): AffectedReservationCase;
}
