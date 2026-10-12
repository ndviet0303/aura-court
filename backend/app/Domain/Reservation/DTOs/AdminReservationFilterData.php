<?php

namespace App\Domain\Reservation\DTOs;

use App\Http\Requests\Reservation\ListAdminReservationsRequest;

readonly class AdminReservationFilterData
{
    public function __construct(
        public ?string $status = null,
        public ?string $date = null,
        public ?int $venueId = null,
    ) {}

    public static function from(ListAdminReservationsRequest $request): self
    {
        return new self(
            status: $request->validated('status'),
            date: $request->validated('date'),
            venueId: $request->filled('venue_id') ? (int) $request->validated('venue_id') : null,
        );
    }
}
