<?php

namespace App\Http\Resources\Package;

use App\Models\PackagePaymentRequest;
use App\Services\Payment\VietQrImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A package payment request with AuraCourt's receiving account and a QR whose content is the payment code (D4). */
class PackagePaymentRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PackagePaymentRequest $paymentRequest */
        $paymentRequest = $this->resource;
        $receiver = [
            'bank_code' => (string) config('services.sepay.bank'),
            'account_number' => config('services.sepay.account'),
            'account_name' => (string) config('services.sepay.account_name'),
        ];

        return [
            'request_code' => $paymentRequest->request_code,
            'payment_code' => $paymentRequest->payment_code,
            'venue_id' => $paymentRequest->venue_id,
            'kind' => $paymentRequest->kind,
            'term_days' => $paymentRequest->term_days->value,
            'days' => $paymentRequest->term_days->value,
            'amount' => $paymentRequest->amount,
            'status' => $paymentRequest->statusAt(now()),
            'expires_at' => $paymentRequest->expires_at->toISOString(),
            'paid_at' => $paymentRequest->paid_at?->toISOString(),
            'period_starts_at' => $paymentRequest->period_starts_at?->toISOString(),
            'period_ends_at' => $paymentRequest->period_ends_at?->toISOString(),
            'receiver' => $receiver,
            'qr_url' => blank($receiver['account_number']) ? null : VietQrImage::url(
                $receiver['bank_code'],
                (string) $receiver['account_number'],
                $receiver['account_name'],
                $paymentRequest->amount,
                $paymentRequest->payment_code,
            ),
        ];
    }
}
