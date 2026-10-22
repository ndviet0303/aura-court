<?php

namespace Tests\Concerns;

use App\Domain\Package\Enums\PackageTerm;
use App\Models\PackagePrice;
use Carbon\CarbonInterface;
use Illuminate\Testing\TestResponse;

/**
 * Module 05 venue packages: package prices, AuraCourt's receiving account and the SePay webhook that pays a PKG code.
 * No real provider is called: the webhook is posted straight to the app with a fake API key.
 *
 *     $this->usesAuraCourtAccount();
 *     $this->packagePrice(PackageTerm::Days30, 300000);
 *     $this->payPackage('PKGABCDEFGH', 300000, now());   // a SePay transfer quoting the code
 */
trait PaysPackages
{
    protected function usesAuraCourtAccount(): void
    {
        config([
            'services.sepay.bank' => 'VCB',
            'services.sepay.account' => '0011223344',
            'services.sepay.account_name' => 'AURA COURT',
            'services.sepay.api_key' => 'test-sepay-key',
            'services.sepay.hmac_secret' => null,
        ]);
    }

    protected function packagePrice(PackageTerm $term, int $amount, ?CarbonInterface $effectiveAt = null): PackagePrice
    {
        return PackagePrice::factory()->create([
            'term_days' => $term,
            'amount' => $amount,
            'effective_at' => ($effectiveAt ?? now())->copy()->utc(),
        ]);
    }

    /** A SePay transfer quoting the code, recorded by the bank at $at (Vietnam wall time, as SePay sends it). */
    protected function payPackage(string $paymentCode, int $amount, CarbonInterface $at, ?string $transactionId = null, string $type = 'in'): TestResponse
    {
        return $this->postJson('/api/webhooks/sepay', [
            'id' => $transactionId ?? (string) random_int(1, PHP_INT_MAX),
            'gateway' => 'Vietcombank',
            'transactionDate' => $at->copy()->setTimezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s'),
            'accountNumber' => '0011223344',
            'content' => "CK {$paymentCode} phi goi",
            'transferType' => $type,
            'transferAmount' => $amount,
        ], ['Authorization' => 'Apikey test-sepay-key']);
    }
}
