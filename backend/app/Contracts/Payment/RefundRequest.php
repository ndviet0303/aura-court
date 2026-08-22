<?php

namespace App\Contracts\Payment;

final readonly class RefundRequest
{
    public function __construct(
        public string $providerPaymentId,
        public int $amount,
        public string $currency = 'VND',
        public string $reference = '',
        public string $reason = '',
        public string $idempotencyKey = '',
        public array $metadata = [],
    ) {}
}
