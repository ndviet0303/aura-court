<?php

namespace App\Http\Resources\Package;

use App\Models\PackagePaymentReceipt;
use App\Models\PackagePaymentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A package payment request for the platform admin (D15); the detail adds its stored receipts and their outcomes. */
class PlatformFeeRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PackagePaymentRequest $paymentRequest */
        $paymentRequest = $this->resource;
        $owner = $paymentRequest->organization?->owner;

        return [
            'id' => $paymentRequest->id,
            'request_code' => $paymentRequest->request_code,
            'payment_code' => $paymentRequest->payment_code,
            'venue' => ['id' => $paymentRequest->venue_id, 'name' => $paymentRequest->venue?->name, 'status' => $paymentRequest->venue?->status],
            'organization' => ['id' => $paymentRequest->organization_id, 'name' => $paymentRequest->organization?->name],
            'owner' => $owner ? ['id' => $owner->id, 'name' => $owner->name, 'email' => $owner->email] : null,
            'kind' => $paymentRequest->kind,
            'term_days' => $paymentRequest->term_days->value,
            'amount' => $paymentRequest->amount,
            'status' => $paymentRequest->statusAt(now()),
            'created_at' => $paymentRequest->created_at?->toISOString(),
            'expires_at' => $paymentRequest->expires_at->toISOString(),
            'paid_at' => $paymentRequest->paid_at?->toISOString(),
            'period_starts_at' => $paymentRequest->period_starts_at?->toISOString(),
            'period_ends_at' => $paymentRequest->period_ends_at?->toISOString(),
            'venue_package_id' => $paymentRequest->venue_package_id,
            // The raw provider payload stays private; the reconciliation fields are enough to trace a transfer.
            'receipts' => $this->whenLoaded('receipts', fn () => $paymentRequest->receipts->map(fn (PackagePaymentReceipt $receipt) => [
                'id' => $receipt->id,
                'provider_transaction_id' => $receipt->provider_transaction_id,
                'amount' => $receipt->amount,
                'transferred_at' => $receipt->transferred_at->toISOString(),
                'received_at' => $receipt->received_at->toISOString(),
                'outcome' => $receipt->outcome,
            ])->all()),
        ];
    }
}
