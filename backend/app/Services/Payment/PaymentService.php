<?php

namespace App\Services\Payment;

use App\Domain\Payment\Actions\ProcessQrPayment;

class PaymentService
{
    public function __construct(
        private readonly ProcessQrPayment $processQrPaymentAction,
    ) {}

    public function handleWebhook(array $data, string $raw): void
    {
        $this->processQrPaymentAction->execute($data, $raw);
    }
}
