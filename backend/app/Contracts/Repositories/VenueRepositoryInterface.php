<?php

namespace App\Contracts\Repositories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Collection;

interface VenueRepositoryInterface
{
    public function getActiveVenuesWithCourts(?string $sportType = null): Collection;

    public function findBySlug(string $slug): ?Venue;

    public function findById(int $id): ?Venue;
}
