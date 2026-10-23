<?php

namespace App\Contracts\Repositories;

use App\Models\PosProduct;
use App\Models\StockTransfer;
use Illuminate\Support\Collection;

/** Stock transfers between venues of one organization (pos-venue-inventory D5). */
interface StockTransferRepositoryInterface
{
    /** The organization's product, or null when it has none with this id. */
    public function product(int $organizationId, int $productId): ?PosProduct;

    /**
     * Records a transfer and returns it with its product, venues and actor.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): StockTransfer;

    /**
     * The latest transfers out of or into the venue, newest first.
     *
     * @return Collection<int, StockTransfer>
     */
    public function forVenue(int $organizationId, int $venueId, int $limit = 150): Collection;
}
