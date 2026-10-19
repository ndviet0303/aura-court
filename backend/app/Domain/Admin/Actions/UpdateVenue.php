<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\VenueRepositoryInterface;
use App\Models\Venue;

class UpdateVenue
{
    public function __construct(
        private readonly VenueRepositoryInterface $venues,
    ) {}

    public function execute(Venue $venue, array $data): Venue
    {
        $this->venues->update($venue, $data);

        return $venue->fresh();
    }
}
