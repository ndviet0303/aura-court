<?php

namespace App\Http\Resources\Booking;

use App\Http\Resources\Promotion\VoucherQuoteResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One occurrence of a recurring booking: as checked (rental and the vouchers that apply, or the code entered) or as
 * submitted (the booking it became), or why the ordinary booking flow refused it.
 *
 * @property array<string, mixed> $resource
 */
class RecurringOccurrenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $row = $this->resource;
        $reservation = $row['reservation'] ?? null;

        return [
            'starts_at' => $row['starts_at'],
            'ends_at' => $row['ends_at'],
            'ok' => ! isset($row['refusal']),
            'refusal' => isset($row['refusal']) ? $row['refusal']->body : null,
            'rental' => $row['rental'] ?? null,
            'vouchers' => isset($row['vouchers']) ? VoucherQuoteResource::collection($row['vouchers'])->toArray($request) : null,
            'code_voucher' => isset($row['code_voucher']) ? VoucherQuoteResource::make($row['code_voucher'])->toArray($request) : null,
            'code_refusal' => isset($row['code_refusal']) ? $row['code_refusal']->body : null,
            'reservation' => $reservation === null ? null : [
                'code' => $reservation->code,
                'status' => $reservation->status,
                'total_amount' => (int) $reservation->total_amount,
                'hold_expires_at' => $reservation->hold_expires_at?->toIso8601String(),
            ],
        ];
    }
}
