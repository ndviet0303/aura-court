<?php

namespace App\Contracts\Repositories;

use App\Models\StockMovement;
use App\Models\VenueStock;

/** POS stock per venue (pos-venue-inventory D4): every stock change goes through apply, on a row taken with lock. */
interface VenueStockRepositoryInterface
{
    /** The product's stock row at the venue, created at zero when missing, locked until the caller's transaction ends. */
    public function lock(int $organizationId, int $venueId, int $productId): VenueStock;

    /** Units held at the venue: reserved tab items of its bookings and items of its orders awaiting QR payment. */
    public function held(int $venueId, int $productId): int;

    /**
     * Changes a locked row by $delta and records the movement with the venue's balance after it.
     * Refuses with 409 naming the SKU when the stock would go below zero.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function apply(VenueStock $stock, int $delta, string $type, ?int $actorId, ?string $referenceType, ?int $referenceId, array $metadata = []): StockMovement;

    /**
     * Stock at the venue by product id; a product without a row there has none.
     *
     * @param  list<int>  $productIds
     * @return array<int, int>
     */
    public function quantities(int $venueId, array $productIds): array;
}
