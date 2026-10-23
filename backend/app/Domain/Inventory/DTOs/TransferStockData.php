<?php

namespace App\Domain\Inventory\DTOs;

use App\Http\Requests\POS\StoreStockTransferRequest;

readonly class TransferStockData
{
    public function __construct(
        public int $organizationId,
        public int $productId,
        public int $fromVenueId,
        public int $toVenueId,
        public int $quantity,
        public ?string $note = null,
    ) {}

    public static function fromRequest(StoreStockTransferRequest $request, int $organizationId): self
    {
        return new self(
            organizationId: $organizationId,
            productId: (int) $request->validated('product_id'),
            fromVenueId: (int) $request->validated('from_venue_id'),
            toVenueId: (int) $request->validated('to_venue_id'),
            quantity: (int) $request->validated('quantity'),
            note: $request->validated('note'),
        );
    }
}
