<?php

namespace App\Contracts\Repositories;

use App\Models\Reservation;
use App\Models\ReservationSlot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reservation reads that cross organizations; implementations ignore the tenant scope.
 */
interface ReservationRepositoryInterface
{
    public function paginateUserReservations(int $userId, int $perPage = 20): LengthAwarePaginator;

    /**
     * @param  array{status?: ?string, date?: ?string, venue_ids?: ?array<int>}  $filters  venue_ids null means every venue
     */
    public function paginateAdminReservations(array $filters, int $perPage = 30): LengthAwarePaginator;

    /**
     * The reservation's slots still holding court time (held or confirmed), by start.
     *
     * @return Collection<int, ReservationSlot>
     */
    public function unreleasedSlots(int $reservationId): Collection;

    /**
     * Bookings pending payment that hold a use of the program (design D19), by id, with only id and code.
     *
     * @return Collection<int, Reservation>
     */
    public function heldWithVoucherOf(int $programId): Collection;

    /** A booking of the organization by its code, with its court, venue and booker account. */
    public function findInOrganizationByCode(int $organizationId, string $code): ?Reservation;
}
