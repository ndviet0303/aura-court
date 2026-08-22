<?php

namespace App\Contracts\Payment;

final readonly class BeneficiaryValidationResponse
{
    public function __construct(
        public string $provider,
        public bool $verified,
        public string $bankCode,
        public string $accountNumber,
        public ?string $accountHolder = null,
        public ?string $providerBeneficiaryId = null,
        public ?string $failureCode = null,
        public array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'verified' => $this->verified,
            'bankCode' => $this->bankCode,
            'accountNumber' => $this->accountNumber,
            'accountHolder' => $this->accountHolder,
            'providerBeneficiaryId' => $this->providerBeneficiaryId,
            'failureCode' => $this->failureCode,
            'raw' => $this->raw,
        ];
    }
}
