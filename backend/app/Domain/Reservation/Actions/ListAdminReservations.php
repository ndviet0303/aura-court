<?php

namespace App\Domain\Reservation\Actions;

use App\Contracts\Repositories\ReservationRepositoryInterface;
use App\Domain\Reservation\DTOs\AdminReservationFilterData;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAdminReservations
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservations,
    ) {}

    public function execute(AdminReservationFilterData $filter, User $user): LengthAwarePaginator
    {
        // null = platform admin (every venue); otherwise only the venues this user may operate, possibly none.
        $venueIds = $user->getAuthorizedVenueIds();
        if ($venueIds !== null) {
            $venueIds = array_map('intval', $venueIds);
            abort_if($filter->venueId !== null && ! in_array($filter->venueId, $venueIds, true), 403, 'Unauthorized venue.');
        }

        return $this->reservations->paginateAdminReservations([
            'status' => $filter->status,
            'date' => $filter->date,
            'venue_ids' => $filter->venueId !== null ? [$filter->venueId] : $venueIds,
        ], 30);
    }
}
