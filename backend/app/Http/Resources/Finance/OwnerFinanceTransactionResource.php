<?php

namespace App\Http\Resources\Finance;

use App\Models\OwnerPaymentReceipt;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One received transfer and its reconciliation against the booking it names (D12). */
class OwnerFinanceTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var OwnerPaymentReceipt $receipt */
        $receipt = $this->resource;
        /** @var Reservation|null $reservation */
        $reservation = $receipt->getRelation('reservation');

        return [
            'id' => $receipt->id,
            'transaction_at' => ($receipt->event_at ?? $receipt->received_at)->toISOString(),
            'amount' => $receipt->amount,
            'currency' => $receipt->currency,
            'payment_code' => $receipt->payment_code,
            'status' => $receipt->status,
            'exception_code' => $receipt->exception_code,
            'purpose' => $receipt->purpose,
            'venue' => ['id' => $receipt->venue_id, 'name' => $receipt->venue?->name],
            'booking' => $reservation ? OwnerFinanceBookingResource::make($reservation)->toArray($request) : null,
            // Δ = A − P only for an identified booking: 0 matches, below 0 is short, above 0 is excess.
            'delta' => $reservation ? $receipt->amount - $reservation->total_amount : null,
        ];
    }
}
