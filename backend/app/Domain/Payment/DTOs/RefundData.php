<?php

namespace App\Domain\Payment\DTOs;

readonly class RefundData
{
    public function __construct(
        public int $bookingId,
        public int $amount,
        public ?string $reason = null,
    ) {}
}
