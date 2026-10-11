<?php

namespace App\Infrastructure\Payment;

use App\Contracts\Infrastructure\SubscriptionPaymentAdapterInterface;
use App\Contracts\Payment\PaymentIntentRequest;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Models\PlatformPaymentAttempt;

class ProviderNeutralSubscriptionPaymentAdapter implements SubscriptionPaymentAdapterInterface
{
    public function __construct(
        private readonly PaymentProviderInterface $paymentProvider,
    ) {}

    public function initiatePayment(PlatformPaymentAttempt $attempt, ?string $returnUrl = null, ?string $cancelUrl = null): array
    {
        // Deterministic sandbox behavior for automated tests and sandbox configurations
        $isTesting = app()->environment('testing');
        $hasLiveCredentials = ! empty(config('services.payos.client_id'))
            && ! str_contains((string) config('services.payos.client_id'), 'dummy')
            && ! str_contains((string) config('services.payos.client_id'), 'test');

        if ($isTesting || ! $hasLiveCredentials) {
            $sandboxRef = 'SANDBOX-'.strtoupper(substr(md5($attempt->reference_code), 0, 10));
            $sandboxUrl = url("/payment/sandbox-checkout?ref={$attempt->reference_code}");

            return [
                'checkout_url' => $sandboxUrl,
                'provider' => $this->paymentProvider->name(),
                'provider_ref' => $sandboxRef,
                'qr_code' => 'data:image/svg+xml;utf8,<svg>SANDBOX-QR</svg>',
            ];
        }

        $intentReq = new PaymentIntentRequest(
            reference: $attempt->reference_code,
            amount: $attempt->amount,
            currency: $attempt->currency,
            description: "Thanh toan goi dich vu - {$attempt->reference_code}",
            returnUrl: $returnUrl ?: url('/partner/subscription/callback?status=success'),
            cancelUrl: $cancelUrl ?: url('/partner/subscription/callback?status=cancelled'),
            metadata: $attempt->metadata ?? [],
            idempotencyKey: $attempt->reference_code
        );

        $res = $this->paymentProvider->createPaymentIntent($intentReq);

        return [
            'checkout_url' => (string) $res->checkoutUrl,
            'provider' => $res->provider,
            'provider_ref' => $res->providerIntentId,
            'qr_code' => $res->qrCode,
        ];
    }
}
