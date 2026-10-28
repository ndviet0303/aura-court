<?php

namespace App\Domain\BookingTransfer\DTOs;

use App\Http\Requests\BookingTransfer\StoreTransferListingRequest;

/** FR-140: the seller's note and a price for each booking offered (BR-180). */
readonly class TransferListingData
{
    /** @param  list<array{reservation_id: int, price: int}>  $items */
    public function __construct(public ?string $note, public array $items) {}

    public static function from(StoreTransferListingRequest $request): self
    {
        $note = trim((string) $request->validated('note'));

        return new self($note === '' ? null : $note, TransferItems::from($request->validated('items')));
    }
}
