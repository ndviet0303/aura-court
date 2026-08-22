<?php

namespace App\Contracts\Payment;

final readonly class PaymentIntentResponse
{
    public function __construct(
        public string $provider,
        public string $providerIntentId,
        public string $status,
        public int $amount,
        public string $currency = 'VND',
        public ?string $checkoutUrl = null,
        public ?string $qrCode = null,
        public array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'providerIntentId' => $this->providerIntentId,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'checkoutUrl' => $this->checkoutUrl,
            'qrCode' => $this->qrCode,
            'raw' => $this->raw,
        ];
    }
}
