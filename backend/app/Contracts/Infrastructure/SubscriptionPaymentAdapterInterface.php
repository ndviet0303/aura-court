<?php

namespace App\Contracts\Infrastructure;

use App\Models\PlatformPaymentAttempt;

interface SubscriptionPaymentAdapterInterface
{
    /**
     * @return array{
     *     checkout_url: string,
     *     provider: string,
     *     provider_ref: string,
     *     qr_code: ?string
     * }
     */
    public function initiatePayment(PlatformPaymentAttempt $attempt, ?string $returnUrl = null, ?string $cancelUrl = null): array;
}
