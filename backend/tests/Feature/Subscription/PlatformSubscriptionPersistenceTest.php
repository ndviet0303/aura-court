<?php

namespace Tests\Feature\Subscription;

use App\Models\Organization;
use App\Models\PlatformPaymentAttempt;
use App\Models\PlatformPlan;
use App\Models\PlatformProviderReceipt;
use App\Models\PlatformSubscription;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSubscriptionPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_persist_plans_subscriptions_attempts_and_receipts(): void
    {
        $org = Organization::create([
            'name' => 'Badminton Champions Club',
            'slug' => 'badminton-champions',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        $plan = PlatformPlan::create([
            'code' => 'pro_monthly',
            'name' => 'Gói Chuyên Nghiệp',
            'version' => 1,
            'price' => 500000, // 500,000 VND
            'currency' => 'VND',
            'billing_cycle' => 'monthly',
            'trial_days' => 14,
            'trial_enabled' => true,
            'max_venues' => 2,
            'max_courts' => 10,
            'max_staff' => 8,
            'features' => ['advanced_analytics', 'pos_integration', 'priority_support'],
            'terms_version' => '2026.1',
            'terms_text' => 'Điều khoản dịch vụ gói Pro...',
            'is_active' => true,
        ]);

        $subscription = PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'plan_version' => 1,
            'status' => 'trial',
            'trial_started_at' => now(),
            'trial_ends_at' => now()->addDays(14),
            'trial_used' => true,
            'price_snapshot' => 500000,
            'currency_snapshot' => 'VND',
            'terms_version_accepted' => '2026.1',
            'terms_accepted_at' => now(),
        ]);

        $attempt = PlatformPaymentAttempt::create([
            'subscription_id' => $subscription->id,
            'organization_id' => $org->id,
            'reference_code' => 'SUB-PAY-001',
            'amount' => 500000,
            'currency' => 'VND',
            'provider' => 'vnpay',
            'provider_ref' => 'VNPAY_TXN_999',
            'status' => 'succeeded',
            'paid_at' => now(),
            'metadata' => ['bank_code' => 'NCB'],
        ]);

        $receipt = PlatformProviderReceipt::create([
            'payment_attempt_id' => $attempt->id,
            'provider' => 'vnpay',
            'provider_transaction_id' => 'VNPAY_TRANS_88888',
            'amount' => 500000,
            'currency' => 'VND',
            'raw_payload' => ['vnp_ResponseCode' => '00', 'vnp_TransactionNo' => '88888'],
            'verified_at' => now(),
        ]);

        $this->assertTrue($subscription->isTrial());
        $this->assertTrue($subscription->isValid());
        $this->assertFalse($subscription->isExpired());
        $this->assertTrue($org->hasActiveSubscription());

        $this->assertEquals('Gói Chuyên Nghiệp', $subscription->plan->name);
        $this->assertEquals('VNPAY_TRANS_88888', $attempt->receipt->provider_transaction_id);
    }

    public function test_unique_provider_transaction_id_prevents_duplicate_receipts(): void
    {
        $org = Organization::create([
            'name' => 'Elite Sports',
            'slug' => 'elite-sports',
            'status' => 'active',
        ]);

        $plan = PlatformPlan::create([
            'code' => 'starter',
            'name' => 'Starter Plan',
            'price' => 200000,
            'currency' => 'VND',
        ]);

        $subscription = PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'pending_payment',
        ]);

        $attempt1 = PlatformPaymentAttempt::create([
            'subscription_id' => $subscription->id,
            'organization_id' => $org->id,
            'reference_code' => 'PAY-REF-1',
            'amount' => 200000,
            'currency' => 'VND',
        ]);

        $attempt2 = PlatformPaymentAttempt::create([
            'subscription_id' => $subscription->id,
            'organization_id' => $org->id,
            'reference_code' => 'PAY-REF-2',
            'amount' => 200000,
            'currency' => 'VND',
        ]);

        PlatformProviderReceipt::create([
            'payment_attempt_id' => $attempt1->id,
            'provider' => 'vnpay',
            'provider_transaction_id' => 'TXN_IDENTICAL_123',
            'amount' => 200000,
            'currency' => 'VND',
            'raw_payload' => ['sample' => 1],
            'verified_at' => now(),
        ]);

        // Attempting to record identical provider_transaction_id must fail unique constraint
        $this->expectException(QueryException::class);

        PlatformProviderReceipt::create([
            'payment_attempt_id' => $attempt2->id,
            'provider' => 'vnpay',
            'provider_transaction_id' => 'TXN_IDENTICAL_123',
            'amount' => 200000,
            'currency' => 'VND',
            'raw_payload' => ['sample' => 2],
            'verified_at' => now(),
        ]);
    }
}
