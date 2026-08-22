<?php

namespace App\Contracts\Payment;

final readonly class PaymentIntentRequest
{
    public function __construct(
        public string $reference,
        public int $amount,
        public string $currency = 'VND',
        public string $description = '',
        public ?string $returnUrl = null,
        public ?string $cancelUrl = null,
        public array $metadata = [],
        public string $idempotencyKey = '',
    ) {}
}
