<?php

namespace App\Contracts\Payment;

final readonly class PayoutInstruction
{
    public function __construct(
        public string $reference,
        public string $beneficiaryId,
        public int $amount,
        public string $currency = 'VND',
        public string $description = '',
        public string $idempotencyKey = '',
        public array $metadata = [],
    ) {}
}
