<?php

namespace App\Http\Resources\BookingTransfer;

use App\Models\BookingTransfer;
use App\Models\BookingTransferItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * FR-144: both parties see the seller, the buyer, each booking with its agreed price, the total and the status.
 * refusal is a pending booking's check read now, so the seller sees a reason before confirming.
 *
 * @mixin BookingTransfer
 */
class BookingTransferResource extends JsonResource
{
    /** @param  array<int, array{code: string, message: string}|null>  $refusals  keyed by reservation id */
    public function __construct($resource, private readonly array $refusals = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'status' => $this->status,
            'listing_id' => $this->listing_id === null ? null : (int) $this->listing_id,
            'seller' => TransferBooking::person($this->seller),
            'buyer' => TransferBooking::person($this->buyer),
            'is_seller' => (int) $this->seller_id === (int) $request->user()?->id,
            'items' => $this->items->map(fn (BookingTransferItem $item): array => [
                'reservation_id' => (int) $item->reservation_id,
                ...TransferBooking::of($item->reservation),
                'price' => (int) $item->price,
                'refusal' => $this->refusals[$item->reservation_id] ?? null,
            ])->values()->all(),
            'total' => (int) $this->total,
            'created_at' => $this->created_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
        ];
    }
}
