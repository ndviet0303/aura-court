<?php

namespace App\Http\Resources\Finance;

use App\Models\FinanceException;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** The owner's read-only view of a direct-owner payment exception: what arrived, where, and which booking it named (D8). */
class OwnerPaymentExceptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var FinanceException $exception */
        $exception = $this->resource;
        $metadata = $exception->metadata ?? [];
        $purpose = $metadata['purpose'] ?? null;
        $eventAt = $metadata['event_at'] ?? null;

        return [
            'id' => $exception->id,
            'code' => Str::after($exception->type, 'owner_payment_'),
            'status' => $exception->status,
            'amount' => $exception->amount,
            'currency' => $exception->currency ?? 'VND',
            'payment_code' => $metadata['payment_code'] ?? null,
            'event_at' => $eventAt ? Carbon::parse($eventAt)->toISOString() : null,
            'connection' => [
                'bank_code' => $metadata['bank_code'] ?? null,
                'account_last_four' => $metadata['account_last_four'] ?? null,
            ],
            'purpose' => $purpose,
            // Only a booking request's id is a booking code; other purposes name series, groups or orders.
            'booking_code' => $purpose === 'booking' ? ($metadata['purpose_id'] ?? null) : null,
            'detected_at' => $exception->detected_at->toISOString(),
        ];
    }
}
