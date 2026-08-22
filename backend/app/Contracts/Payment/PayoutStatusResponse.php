<?php

namespace App\Contracts\Payment;

final readonly class PayoutStatusResponse
{
    public function __construct(
        public string $provider,
        public string $providerPayoutId,
        public string $status,
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
        public array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'providerPayoutId' => $this->providerPayoutId,
            'status' => $this->status,
            'failureCode' => $this->failureCode,
            'failureMessage' => $this->failureMessage,
            'raw' => $this->raw,
        ];
    }
}
