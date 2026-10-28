<?php

namespace App\Http\Resources\BookingTransfer;

use App\Models\BookingTransferListing;
use App\Models\BookingTransferListingItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * FR-141: a listing with its poster and each booking's price and state. contacts, given only to the seller, lists
 * who asked about it in chat (UC-9.7).
 *
 * @mixin BookingTransferListing
 */
class TransferListingResource extends JsonResource
{
    /** @param  list<array<string, mixed>>|null  $contacts */
    public function __construct($resource, private readonly ?array $contacts = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
            'seller' => TransferBooking::person($this->seller),
            'is_mine' => (int) $this->seller_id === (int) $request->user()?->id,
            'items' => $this->items->map(fn (BookingTransferListingItem $item): array => [
                'reservation_id' => (int) $item->reservation_id,
                ...TransferBooking::of($item->reservation),
                'paid_amount' => (int) $item->reservation?->total_amount,
                'price' => (int) $item->price,
                'state' => $item->state(),
            ])->values()->all(),
            'contacts' => $this->when($this->contacts !== null, $this->contacts),
        ];
    }
}
