<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\StockTransferRepositoryInterface;
use App\Models\PosProduct;
use App\Models\StockTransfer;
use Illuminate\Support\Collection;

class EloquentStockTransferRepository implements StockTransferRepositoryInterface
{
    private const DETAILS = ['product:id,name,sku', 'fromVenue:id,name', 'toVenue:id,name', 'creator:id,name'];

    public function product(int $organizationId, int $productId): ?PosProduct
    {
        return PosProduct::query()->where('organization_id', $organizationId)->find($productId);
    }

    public function create(array $attributes): StockTransfer
    {
        return StockTransfer::query()->create($attributes)->load(self::DETAILS);
    }

    public function forVenue(int $organizationId, int $venueId, int $limit = 150): Collection
    {
        return StockTransfer::query()
            ->where('organization_id', $organizationId)
            ->where(fn ($query) => $query->where('from_venue_id', $venueId)->orWhere('to_venue_id', $venueId))
            ->with(self::DETAILS)
            ->latest('created_at')->latest('id')
            ->take($limit)
            ->get()
            ->toBase();
    }
}
