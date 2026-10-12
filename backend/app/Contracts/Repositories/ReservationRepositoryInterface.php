<?php

namespace App\Contracts\Repositories;

use App\Models\Reservation;
use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Reservation reads that cross organizations; implementations ignore the tenant scope.
 */
interface ReservationRepositoryInterface
{
    /** The caller's reservation by code, with slots, legacy mapping and review loaded. */
    public function findOwnedByCode(int $userId, string $code): ?Reservation;

    public function createReview(array $attributes): Review;

    public function paginateUserReservations(int $userId, int $perPage = 20): LengthAwarePaginator;

    /**
     * @param  array{status?: ?string, date?: ?string, venue_ids?: ?array<int>}  $filters  venue_ids null means every venue
     */
    public function paginateAdminReservations(array $filters, int $perPage = 30): LengthAwarePaginator;
}
