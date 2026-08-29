<?php

namespace App\Domain\Payment\DTOs;

readonly class PaymentIntentData
{
    public function __construct(
        public int $bookingId,
        public int $amount,
        public string $provider,
        public ?string $transferCode = null,
        public array $cardDetails = [],
    ) {}
}
