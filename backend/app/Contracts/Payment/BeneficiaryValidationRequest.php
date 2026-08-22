<?php

namespace App\Contracts\Payment;

final readonly class BeneficiaryValidationRequest
{
    public function __construct(
        public string $bankCode,
        public string $accountNumber,
        public ?string $accountHolder = null,
        public ?string $taxId = null,
        public array $metadata = [],
    ) {}
}
