<?php

namespace App\Contracts\Repositories;

use App\Models\Court;
use App\Models\CourtBlock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CourtBlockRepositoryInterface
{
    public function paginateForCourt(Court $court, int $perPage = 50): LengthAwarePaginator;

    public function createForCourt(Court $court, array $attributes): CourtBlock;

    public function delete(CourtBlock $block): bool;

    public function hasOverlappingBlock(Court $court, $startsAtUtc, $endsAtUtc): bool;
}
