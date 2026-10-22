<?php

namespace App\Http\Resources\Finance;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The five separate metrics of the owner's financial report, with no total (D12). */
class OwnerFinanceReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'metrics' => array_map(fn (array $metric) => [
                'amount' => $metric['amount'],
                'count' => $metric['count'],
                'bookings' => $metric['rows']->map(fn (Reservation $row) => [
                    ...OwnerFinanceBookingResource::make($row)->toArray($request),
                    'at' => $row->getAttribute('finance_at')->toISOString(),
                    'amount' => $row->getAttribute('finance_amount'),
                ])->values()->all(),
            ], $this->resource),
        ];
    }
}
