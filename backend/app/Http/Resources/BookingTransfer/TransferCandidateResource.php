<?php

namespace App\Http\Resources\BookingTransfer;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * FR-139, UC-9.1: one of the holder's upcoming bookings, whether it can be listed now and why not, the most it may
 * be offered for (BR-181) and its recurring group (BR-179, D7).
 *
 * @mixin Reservation
 */
class TransferCandidateResource extends JsonResource
{
    /** @param  array{code: string, message: string}|null  $refusal */
    public function __construct($resource, private readonly ?array $refusal, private readonly int $maxPrice)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'reservation_id' => (int) $this->id,
            'code' => $this->code,
            ...TransferBooking::of($this->resource),
            'paid_amount' => (int) $this->total_amount,
            'max_price' => $this->maxPrice,
            'recurring_group' => $this->metadata['recurring_group'] ?? null,
            'eligible' => $this->refusal === null,
            'refusal' => $this->refusal,
        ];
    }
}
