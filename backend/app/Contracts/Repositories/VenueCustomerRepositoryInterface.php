<?php

namespace App\Contracts\Repositories;

use App\Domain\Customer\Rules\CustomerQuarter;
use App\Models\Reservation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * FR-110: the accounts that booked at a venue with their quarter figures, read from bookings and their attendance
 * (module 06 D3) so a module 06 adjustment shows at once. Each row has id, name, phone, email, avatar_url, completed,
 * no_show, used_minutes, completed_amount and last_used_at (any quarter).
 */
interface VenueCustomerRepositoryInterface
{
    /** @return LengthAwarePaginator<int, object> most completed first */
    public function paginate(int $venueId, CustomerQuarter $quarter, ?string $search, int $perPage): LengthAwarePaginator;

    public function find(int $venueId, int $userId, CustomerQuarter $quarter): ?object;

    /** @return Collection<int, Reservation> the customer's confirmed bookings at the venue, latest first */
    public function history(int $venueId, int $userId, int $limit): Collection;
}
