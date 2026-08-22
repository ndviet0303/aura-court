<?php

namespace App\Contracts\Payment;

final readonly class PayoutSubmissionResponse
{
    public function __construct(
        public string $provider,
        public string $providerPayoutId,
        public string $status,
        public int $amount,
        public string $currency = 'VND',
        public array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'providerPayoutId' => $this->providerPayoutId,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'raw' => $this->raw,
        ];
    }
}
