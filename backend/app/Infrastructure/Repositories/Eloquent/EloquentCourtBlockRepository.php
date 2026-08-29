<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\CourtBlockRepositoryInterface;
use App\Models\Court;
use App\Models\CourtBlock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentCourtBlockRepository implements CourtBlockRepositoryInterface
{
    public function paginateForCourt(Court $court, int $perPage = 50): LengthAwarePaginator
    {
        return $court->blocks()->latest('starts_at')->paginate($perPage);
    }

    public function createForCourt(Court $court, array $attributes): CourtBlock
    {
        return $court->blocks()->create($attributes);
    }

    public function delete(CourtBlock $block): bool
    {
        return $block->delete();
    }

    public function hasOverlappingBlock(Court $court, $startsAtUtc, $endsAtUtc): bool
    {
        return $court->blocks()->where(function ($query) use ($startsAtUtc, $endsAtUtc) {
            $query->where('starts_at', '<', $endsAtUtc)
                ->where('ends_at', '>', $startsAtUtc);
        })->exists();
    }
}
