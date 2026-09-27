<?php

namespace Tests\Feature\Finance;

use App\Models\Organization;
use App\Models\PlatformPaymentAttempt;
use App\Models\PlatformProviderReceipt;
use App\Services\Finance\FinancialPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialEntityPostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_subscription_receipt_posts_to_platform_entity_without_commission_or_tenant_payable(): void
    {
        $organization = Organization::create([
            'name' => 'Platform Ledger Tenant',
            'slug' => 'platform-ledger-'.uniqid(),
            'status' => 'active',
        ]);
        $attempt = PlatformPaymentAttempt::create([
            'organization_id' => $organization->id,
            'reference_code' => 'SUB-PAY-ENTITY-1',
            'amount' => 600000,
            'currency' => 'VND',
            'provider' => 'sepay',
            'status' => 'succeeded',
            'paid_at' => now(),
            'metadata' => ['purpose' => 'platform_subscription'],
        ]);
        $receipt = PlatformProviderReceipt::create([
            'payment_attempt_id' => $attempt->id,
            'provider' => 'sepay',
            'provider_transaction_id' => 'PLATFORM-TXN-ENTITY-1',
            'amount' => 600000,
            'currency' => 'VND',
            'raw_payload' => ['id' => 'platform-event-1'],
            'verified_at' => now(),
        ]);

        $posting = app(FinancialPostingService::class);
        $journal = $posting->postPlatformSubscriptionReceipt($receipt)->load('entries.account');
        $retry = $posting->postPlatformSubscriptionReceipt($receipt);

        $this->assertSame($journal->id, $retry->id);
        $this->assertSame('platform', $journal->ledger_entity);
        $this->assertSame('platform_subscription_v1', $journal->payment_model);
        $this->assertSame(600000, $journal->total_debit);
        $this->assertSame($journal->total_debit, $journal->total_credit);
        $this->assertSame(0, $journal->metadata['commission']);
        $this->assertSame(['platform_bank_cash', 'platform_subscription_revenue'], $journal->entries->pluck('account.code')->all());
        $this->assertDatabaseCount('journals', 1);
    }
}
