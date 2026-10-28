<?php

namespace App\Domain\BookingTransfer\DTOs;

use App\Http\Requests\BookingTransfer\StoreBookingTransferRequest;

/** FR-143: the buyer, the bookings and the price agreed for each (BR-183). */
readonly class BookingTransferData
{
    /** @param  list<array{reservation_id: int, price: int}>  $items */
    public function __construct(public ?int $listingId, public int $buyerId, public array $items) {}

    public static function from(StoreBookingTransferRequest $request): self
    {
        $listingId = $request->validated('listing_id');

        return new self($listingId === null ? null : (int) $listingId, (int) $request->validated('buyer_id'), TransferItems::from($request->validated('items')));
    }
}
