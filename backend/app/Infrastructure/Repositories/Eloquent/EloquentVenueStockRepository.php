<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\VenueStockRepositoryInterface;
use App\Models\PosOrderItem;
use App\Models\ServiceTabItem;
use App\Models\StockMovement;
use App\Models\VenueStock;

class EloquentVenueStockRepository implements VenueStockRepositoryInterface
{
    public function lock(int $organizationId, int $venueId, int $productId): VenueStock
    {
        // ON CONFLICT DO NOTHING: two first writers create one row and leave the caller's transaction intact.
        VenueStock::query()->insertOrIgnore([
            'organization_id' => $organizationId,
            'venue_id' => $venueId,
            'product_id' => $productId,
            'quantity' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return VenueStock::query()->where('venue_id', $venueId)->where('product_id', $productId)->lockForUpdate()->firstOrFail();
    }

    public function held(int $venueId, int $productId): int
    {
        $tabs = ServiceTabItem::query()
            ->where('product_id', $productId)
            ->where('status', ServiceTabItem::STATUS_RESERVED)
            ->whereHas('serviceTab.reservation.court', fn ($court) => $court->where('venue_id', $venueId))
            ->sum('quantity');
        $awaitingQr = PosOrderItem::query()
            ->where('product_id', $productId)
            ->whereHas('order', fn ($order) => $order->where('status', 'pending_payment')->where('venue_id', $venueId))
            ->sum('quantity');

        return (int) $tabs + (int) $awaitingQr;
    }

    public function apply(VenueStock $stock, int $delta, string $type, ?int $actorId, ?string $referenceType, ?int $referenceId, array $metadata = []): StockMovement
    {
        $product = $stock->product;
        abort_if($stock->quantity + $delta < 0, 409, "Insufficient stock for {$product->sku}.");
        $stock->update(['quantity' => $stock->quantity + $delta]);

        return StockMovement::create([
            'organization_id' => $stock->organization_id,
            'venue_id' => $stock->venue_id,
            'product_id' => $stock->product_id,
            'type' => $type,
            'quantity_delta' => $delta,
            'unit_cost' => (int) $product->cost,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by' => $actorId,
            'metadata' => ['balance_after' => $stock->quantity] + $metadata,
        ]);
    }

    public function quantities(int $venueId, array $productIds): array
    {
        return VenueStock::query()->where('venue_id', $venueId)->whereIn('product_id', $productIds)
            ->pluck('quantity', 'product_id')->map(fn ($quantity): int => (int) $quantity)->all();
    }
}
