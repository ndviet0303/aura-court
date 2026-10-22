<?php

namespace App\Http\Resources\Finance;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A booking as the owner's finance screens show it; payable is the recorded P_i, never recalculated (D12). */
class OwnerFinanceBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Reservation $reservation */
        $reservation = $this->resource;
        $court = $reservation->court;

        return [
            'id' => $reservation->id,
            'code' => $reservation->code,
            'status' => $reservation->status,
            'venue' => ['id' => $court?->venue_id, 'name' => $court?->venue?->name],
            'court' => ['id' => $reservation->court_id, 'name' => $court?->name],
            'starts_at' => $reservation->starts_at?->toISOString(),
            'ends_at' => $reservation->ends_at?->toISOString(),
            'payable' => $reservation->total_amount,
        ];
    }
}
