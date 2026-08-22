<?php

namespace App\Contracts\Payment;

final readonly class RefundResponse
{
    public function __construct(
        public string $provider,
        public string $providerRefundId,
        public string $status,
        public int $amount,
        public string $currency = 'VND',
        public array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'providerRefundId' => $this->providerRefundId,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'raw' => $this->raw,
        ];
    }
}
