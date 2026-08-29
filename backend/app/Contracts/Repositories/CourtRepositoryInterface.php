<?php

namespace App\Contracts\Repositories;

use App\Models\Court;
use Illuminate\Database\Eloquent\Collection;

interface CourtRepositoryInterface
{
    public function findById(int $id): ?Court;

    public function findActiveWithActiveVenue(int $id): ?Court;

    public function getByVenue(int $venueId): Collection;

    public function create(array $attributes): Court;

    public function update(Court $court, array $attributes): bool;
}
