<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentProviderInterface;
use InvalidArgumentException;

class PaymentProviderManager
{
    /** @var array<string, PaymentProviderInterface> */
    private array $providers = [];

    public function __construct(PaymentProviderInterface ...$providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->name()] = $provider;
        }
    }

    public function provider(?string $name = null): PaymentProviderInterface
    {
        $name ??= (string) config('services.payments.default_provider', 'payos');
        $provider = $this->providers[$name] ?? null;
        if (! $provider) {
            throw new InvalidArgumentException("Unsupported payment provider [{$name}].");
        }

        return $provider;
    }

    /** @return array<string, PaymentProviderInterface> */
    public function all(): array
    {
        return $this->providers;
    }
}
