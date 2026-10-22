<?php

namespace App\Domain\Reservation\Actions;

use App\Contracts\Repositories\AffectedReservationCaseRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** The owner sees the cases of every venue they own, staff those of their assigned venues; another venue is 403. */
class ListAffectedReservations
{
    public function __construct(private readonly AffectedReservationCaseRepositoryInterface $cases) {}

    /** @param  array<string, mixed>  $filters */
    public function execute(User $user, array $filters): LengthAwarePaginator
    {
        $venueIds = $user->getAuthorizedVenueIds();
        $venueId = isset($filters['venue_id']) ? (int) $filters['venue_id'] : null;
        abort_if($venueId !== null && ! in_array($venueId, $venueIds, true), 403, 'Bạn không có quyền xem đơn bị ảnh hưởng của cơ sở này.');

        return $this->cases->paginate([
            'venue_ids' => $venueId !== null ? [$venueId] : $venueIds,
            'court_id' => isset($filters['court_id']) ? (int) $filters['court_id'] : null,
            'source_type' => $filters['source_type'] ?? null,
            'status' => $filters['status'] ?? null,
        ], 30);
    }
}
