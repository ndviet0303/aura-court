<?php

namespace App\Domain\Inventory\Actions;

use App\Contracts\Repositories\StockTransferRepositoryInterface;
use App\Contracts\Repositories\VenueStockRepositoryInterface;
use App\Domain\Inventory\DTOs\TransferStockData;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\POS\PosQrPaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Moves stock from one venue to another of the same organization in one step (pos-venue-inventory D5). */
class TransferStock
{
    public function __construct(
        private readonly VenueStockRepositoryInterface $stock,
        private readonly StockTransferRepositoryInterface $transfers,
        private readonly PosQrPaymentService $venues,
    ) {}

    public function execute(TransferStockData $data, User $actor): StockTransfer
    {
        foreach ([$data->fromVenueId, $data->toVenueId] as $venueId) {
            $this->venues->assertOperableVenue($actor, $data->organizationId, $venueId);
        }
        $product = $this->transfers->product($data->organizationId, $data->productId);
        if (! $product?->isTracked()) {
            throw ValidationException::withMessages([
                'product_id' => $product ? 'Sản phẩm này không theo dõi tồn kho nên không điều chuyển được.' : 'Không tìm thấy sản phẩm.',
            ]);
        }

        return DB::transaction(function () use ($data, $actor, $product): StockTransfer {
            // Both rows are locked in venue order, so two opposite transfers cannot deadlock.
            $rows = [];
            foreach (collect([$data->fromVenueId, $data->toVenueId])->sort() as $venueId) {
                $rows[$venueId] = $this->stock->lock($data->organizationId, $venueId, $product->id);
            }
            $available = $rows[$data->fromVenueId]->quantity - $this->stock->held($data->fromVenueId, $product->id);
            if ($data->quantity > $available) {
                throw ValidationException::withMessages([
                    'quantity' => 'Cơ sở chuyển chỉ còn '.max(0, $available)." {$product->name} bán được.",
                ])->status(409);
            }

            $transfer = $this->transfers->create([
                'organization_id' => $data->organizationId,
                'product_id' => $product->id,
                'from_venue_id' => $data->fromVenueId,
                'to_venue_id' => $data->toVenueId,
                'quantity' => $data->quantity,
                'note' => $data->note,
                'created_by' => $actor->id,
            ]);
            $this->stock->apply($rows[$data->fromVenueId], -$data->quantity, 'transfer_out', $actor->id, 'stock_transfer', $transfer->id, ['to_venue_id' => $data->toVenueId]);
            $this->stock->apply($rows[$data->toVenueId], $data->quantity, 'transfer_in', $actor->id, 'stock_transfer', $transfer->id, ['from_venue_id' => $data->fromVenueId]);

            return $transfer;
        });
    }
}
