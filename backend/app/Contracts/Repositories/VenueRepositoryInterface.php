<?php

namespace App\Contracts\Repositories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Collection;

interface VenueRepositoryInterface
{
    public function getActiveVenuesWithCourts(?string $sportType = null): Collection;

    public function findBySlug(string $slug): ?Venue;

    public function findById(int $id): ?Venue;

    public function create(array $attributes): Venue;

    public function update(Venue $venue, array $attributes): bool;

    public function existsBySlug(string $slug): bool;
}
