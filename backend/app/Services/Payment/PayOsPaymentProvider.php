<?php

namespace App\Services\Payment;

use App\Contracts\Payment\BeneficiaryValidationRequest;
use App\Contracts\Payment\BeneficiaryValidationResponse;
use App\Contracts\Payment\PaymentIntentRequest;
use App\Contracts\Payment\PaymentIntentResponse;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Contracts\Payment\PayoutInstruction;
use App\Contracts\Payment\PayoutStatusResponse;
use App\Contracts\Payment\PayoutSubmissionResponse;
use App\Contracts\Payment\ProviderException;
use App\Contracts\Payment\RefundRequest;
use App\Contracts\Payment\RefundResponse;
use App\Contracts\Payment\WebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class PayOsPaymentProvider implements PaymentProviderInterface
{
    public function name(): string
    {
        return 'payos';
    }

    public function createPaymentIntent(PaymentIntentRequest $request): PaymentIntentResponse
    {
        $payload = [
            'orderCode' => $this->numericReference($request->reference),
            'amount' => $request->amount,
            'description' => $request->description ?: $request->reference,
            'returnUrl' => $request->returnUrl,
            'cancelUrl' => $request->cancelUrl,
            'metadata' => $request->metadata,
        ];
        $data = $this->request()->withHeaders(['x-idempotency-key' => $request->idempotencyKey])->post('/v2/payment-requests', $payload)->json();

        return new PaymentIntentResponse(
            provider: $this->name(),
            providerIntentId: (string) ($data['data']['paymentLinkId'] ?? $data['data']['id'] ?? $data['id'] ?? $request->reference),
            status: strtoupper((string) ($data['data']['status'] ?? $data['status'] ?? 'PENDING')),
            amount: $request->amount,
            currency: $request->currency,
            checkoutUrl: $data['data']['checkoutUrl'] ?? $data['checkoutUrl'] ?? null,
            qrCode: $data['data']['qrCode'] ?? $data['qrCode'] ?? null,
            raw: $data,
        );
    }

    public function verifyWebhookSignature(string $payload, string $signature, array $headers = []): bool
    {
        $checksumKey = (string) (config('services.payos.checksum_key') ?: config('services.payos.webhook_secret'));
        if ($checksumKey === '') {
            return false;
        }

        try {
            $webhook = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        $data = $webhook['data'] ?? null;
        $bodySignature = strtolower((string) ($webhook['signature'] ?? ''));
        if (! is_array($data) || preg_match('/^[a-f0-9]{64}$/', $bodySignature) !== 1) {
            return false;
        }

        try {
            $canonicalData = $this->webhookDataToQueryString($data);
        } catch (\JsonException) {
            return false;
        }

        $expected = hash_hmac('sha256', $canonicalData, $checksumKey);

        return hash_equals($expected, $bodySignature);
    }

    public function parseWebhookEvent(string $payload, array $headers = []): WebhookEvent
    {
        $data = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        $body = is_array($data['data'] ?? null) ? $data['data'] : $data;
        $eventId = (string) ($data['id'] ?? $data['eventId'] ?? $body['paymentLinkId'] ?? $body['reference'] ?? hash('sha256', $payload));
        $status = strtoupper((string) ($body['status'] ?? $data['status'] ?? (
            ($data['success'] ?? false) && (string) ($body['code'] ?? $data['code'] ?? '') === '00'
                ? 'PAID'
                : 'UNKNOWN'
        )));

        return new WebhookEvent(
            provider: $this->name(),
            eventId: $eventId,
            type: (string) ($data['code'] ?? $data['event'] ?? 'payment.status'),
            status: $status,
            reference: isset($body['orderCode']) ? (string) $body['orderCode'] : ($body['reference'] ?? null),
            providerPaymentId: $body['paymentLinkId'] ?? $body['id'] ?? null,
            amount: isset($body['amount']) ? (int) $body['amount'] : null,
            currency: (string) ($body['currency'] ?? 'VND'),
            data: $data,
            occurredAt: isset($data['createdAt']) ? CarbonImmutable::parse($data['createdAt'])->toDateTimeImmutable() : null,
        );
    }

    public function executeRefund(RefundRequest $request): RefundResponse
    {
        $data = $this->request()->withHeaders(['x-idempotency-key' => $request->idempotencyKey])->post('/v2/payment-requests/'.$request->providerPaymentId.'/refund', [
            'amount' => $request->amount,
            'reason' => $request->reason,
            'reference' => $request->reference,
        ])->json();

        return new RefundResponse(
            provider: $this->name(),
            providerRefundId: (string) ($data['data']['refundId'] ?? $data['data']['id'] ?? $data['id'] ?? $request->idempotencyKey),
            status: strtoupper((string) ($data['data']['status'] ?? $data['status'] ?? 'PROCESSING')),
            amount: $request->amount,
            currency: $request->currency,
            raw: $data,
        );
    }

    public function verifyBeneficiary(BeneficiaryValidationRequest $request): BeneficiaryValidationResponse
    {
        $data = $this->request()->post('/v1/payouts/beneficiary/verify', [
            'bankCode' => $request->bankCode,
            'accountNumber' => $request->accountNumber,
            'accountHolder' => $request->accountHolder,
            'taxId' => $request->taxId,
        ])->json();
        $body = is_array($data['data'] ?? null) ? $data['data'] : $data;

        return new BeneficiaryValidationResponse(
            provider: $this->name(),
            verified: (bool) ($body['verified'] ?? $body['success'] ?? false),
            bankCode: $request->bankCode,
            accountNumber: $request->accountNumber,
            accountHolder: $body['accountHolder'] ?? $body['accountName'] ?? null,
            providerBeneficiaryId: $body['beneficiaryId'] ?? null,
            failureCode: $body['code'] ?? null,
            raw: $data,
        );
    }

    public function submitPayout(PayoutInstruction $instruction): PayoutSubmissionResponse
    {
        $data = $this->request()->withHeaders(['x-idempotency-key' => $instruction->idempotencyKey])->post('/v1/payouts', [
            'reference' => $instruction->reference,
            'beneficiaryId' => $instruction->beneficiaryId,
            'amount' => $instruction->amount,
            'currency' => $instruction->currency,
            'description' => $instruction->description,
            'metadata' => $instruction->metadata,
        ])->json();
        $body = is_array($data['data'] ?? null) ? $data['data'] : $data;

        return new PayoutSubmissionResponse(
            provider: $this->name(),
            providerPayoutId: (string) ($body['payoutId'] ?? $body['id'] ?? $instruction->reference),
            status: strtoupper((string) ($body['status'] ?? 'PROCESSING')),
            amount: $instruction->amount,
            currency: $instruction->currency,
            raw: $data,
        );
    }

    public function queryPayoutStatus(string $providerPayoutId): PayoutStatusResponse
    {
        $data = $this->request()->get('/v1/payouts/'.$providerPayoutId)->json();
        $body = is_array($data['data'] ?? null) ? $data['data'] : $data;

        return new PayoutStatusResponse(
            provider: $this->name(),
            providerPayoutId: $providerPayoutId,
            status: strtoupper((string) ($body['status'] ?? 'UNKNOWN')),
            failureCode: $body['failureCode'] ?? $body['code'] ?? null,
            failureMessage: $body['failureMessage'] ?? $body['message'] ?? null,
            raw: $data,
        );
    }

    private function request(): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('services.payos.base_url', 'https://api-merchant.payos.vn'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.payos.timeout', 10));
        $headers = array_filter([
            'x-client-id' => config('services.payos.client_id'),
            'x-api-key' => config('services.payos.api_key'),
        ]);

        return $request->withHeaders($headers)->throw(function ($response, $exception) {
            throw new ProviderException(
                'Payment provider request failed: '.$response->status(),
                (string) ($response->json('code') ?? $response->status()),
                $response->serverError() || $response->status() === 429,
                $exception,
            );
        });
    }

    private function webhookDataToQueryString(array $data): string
    {
        ksort($data, SORT_STRING);
        $parts = [];

        foreach ($data as $key => $value) {
            if ($value === null || in_array($value, ['null', 'undefined'], true)) {
                $value = '';
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                foreach ($value as $index => $item) {
                    if (is_array($item) && ! array_is_list($item)) {
                        ksort($item, SORT_STRING);
                        $value[$index] = $item;
                    }
                }
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            }

            $parts[] = $key.'='.$value;
        }

        return implode('&', $parts);
    }

    private function numericReference(string $reference): int
    {
        $number = (int) preg_replace('/\D+/', '', $reference);

        return $number > 0 ? $number : abs(crc32($reference));
    }
}
