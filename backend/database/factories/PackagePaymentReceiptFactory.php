<?php

namespace Database\Factories;

use App\Models\PackagePaymentReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PackagePaymentReceipt>
 */
class PackagePaymentReceiptFactory extends Factory
{
    /**
     * A SePay transaction whose PKG code matches no request.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => PackagePaymentReceipt::PROVIDER_SEPAY,
            'provider_transaction_id' => (string) Str::uuid(),
            'payment_code' => 'PKG'.Str::upper(Str::random(8)),
            'package_payment_request_id' => null,
            'amount' => 300000,
            'content' => fn (array $attributes) => $attributes['payment_code'],
            'transferred_at' => now()->utc(),
            'received_at' => now()->utc(),
            'outcome' => PackagePaymentReceipt::OUTCOME_UNKNOWN_CODE,
            'raw' => [],
        ];
    }
}
