<?php

namespace App\Contracts\Payment;

interface PaymentProviderInterface
{
    public function name(): string;

    public function createPaymentIntent(PaymentIntentRequest $request): PaymentIntentResponse;

    public function verifyWebhookSignature(string $payload, string $signature, array $headers = []): bool;

    public function parseWebhookEvent(string $payload, array $headers = []): WebhookEvent;

    public function executeRefund(RefundRequest $request): RefundResponse;

    public function verifyBeneficiary(BeneficiaryValidationRequest $request): BeneficiaryValidationResponse;

    public function submitPayout(PayoutInstruction $instruction): PayoutSubmissionResponse;

    public function queryPayoutStatus(string $providerPayoutId): PayoutStatusResponse;
}
