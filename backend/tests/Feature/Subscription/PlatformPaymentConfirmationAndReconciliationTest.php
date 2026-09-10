<?php

namespace Tests\Feature\Subscription;

use App\Domain\Subscription\Actions\FailSubscriptionPayment;
use App\Models\Organization;
use App\Models\PlatformPaymentAttempt;
use App\Models\PlatformPlan;
use App\Models\PlatformProviderReceipt;
use App\Models\PlatformSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformPaymentConfirmationAndReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_confirmation_activates_subscription_and_records_receipt(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create([
            'name' => 'Verified Club',
            'slug' => 'verified-club',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        $plan = PlatformPlan::create([
            'code' => 'PRO_M',
            'name' => 'Gói Pro Tháng',
            'version' => 1,
            'price' => 600000,
            'currency' => 'VND',
            'billing_cycle' => 'monthly',
            'terms_version' => '2026.PRO',
            'terms_text' => 'Terms',
            'is_active' => true,
        ]);

        $attempt = PlatformPaymentAttempt::create([
            'organization_id' => $org->id,
            'reference_code' => 'SUB-PAY-TEST-001',
            'amount' => 600000,
            'currency' => 'VND',
            'provider' => 'payos',
            'provider_ref' => 'PAYOS-INTENT-001',
            'status' => 'pending',
            'metadata' => [
                'plan_id' => $plan->id,
                'plan_version' => 1,
                'billing_cycle' => 'monthly',
                'accepted_terms_version' => '2026.PRO',
                'actor_id' => $owner->id,
            ],
        ]);

        Sanctum::actingAs($owner);

        // Confirm payment with matching amount and currency
        $res = $this->postJson('/api/v1/partner/subscription/confirm-payment', [
            'reference_code' => 'SUB-PAY-TEST-001',
            'provider_transaction_id' => 'TXN-PAYOS-998877',
            'amount' => 600000,
            'currency' => 'VND',
            'payload' => ['bank' => 'VCB', 'code' => '00'],
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('data.subscription.status', 'active')
            ->assertJsonPath('data.subscription.is_valid', true)
            ->assertJsonPath('data.subscription.is_trial', false)
            ->assertJsonPath('data.is_duplicate', false);

        // 1. Payment attempt updated to succeeded
        $this->assertEquals('succeeded', $attempt->fresh()->status);
        $this->assertNotNull($attempt->fresh()->paid_at);
        $this->assertNotNull($attempt->fresh()->subscription_id);

        // 2. Receipt recorded
        $this->assertDatabaseHas('platform_provider_receipts', [
            'payment_attempt_id' => $attempt->id,
            'provider_transaction_id' => 'TXN-PAYOS-998877',
            'amount' => 600000,
        ]);

        // 3. Subscription active in database
        $this->assertDatabaseHas('platform_subscriptions', [
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
    }

    public function test_duplicate_confirmation_is_idempotent_without_extending_duration(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create(['name' => 'Org', 'slug' => 'org', 'status' => 'active', 'partner_status' => 'approved']);

        $plan = PlatformPlan::create([
            'code' => 'PLAN_D',
            'name' => 'Plan',
            'price' => 500000,
            'currency' => 'VND',
            'billing_cycle' => 'monthly',
            'terms_version' => '1.0',
            'terms_text' => 'Terms',
            'is_active' => true,
        ]);

        $attempt = PlatformPaymentAttempt::create([
            'organization_id' => $org->id,
            'reference_code' => 'SUB-PAY-DUP-001',
            'amount' => 500000,
            'currency' => 'VND',
            'provider' => 'payos',
            'status' => 'pending',
            'metadata' => [
                'plan_id' => $plan->id,
                'plan_version' => 1,
                'billing_cycle' => 'monthly',
                'accepted_terms_version' => '1.0',
            ],
        ]);

        Sanctum::actingAs($owner);

        // First confirmation
        $res1 = $this->postJson('/api/v1/partner/subscription/confirm-payment', [
            'reference_code' => 'SUB-PAY-DUP-001',
            'provider_transaction_id' => 'TXN-DUP-01',
            'amount' => 500000,
            'currency' => 'VND',
        ]);
        $res1->assertStatus(200);
        $endsAt1 = $res1->json('data.subscription.ends_at');

        // Second confirmation (duplicate webhook/callback)
        $res2 = $this->postJson('/api/v1/partner/subscription/confirm-payment', [
            'reference_code' => 'SUB-PAY-DUP-001',
            'provider_transaction_id' => 'TXN-DUP-01',
            'amount' => 500000,
            'currency' => 'VND',
        ]);
        $res2->assertStatus(200)
            ->assertJsonPath('data.is_duplicate', true);

        // Assert exactly 1 receipt and identical ends_at (NOT extended!)
        $this->assertEquals(1, PlatformProviderReceipt::where('payment_attempt_id', $attempt->id)->count());
        $this->assertEquals($endsAt1, $res2->json('data.subscription.ends_at'));
    }

    public function test_amount_or_currency_mismatch_fails_and_denies_entitlement(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create(['name' => 'Org', 'slug' => 'org', 'status' => 'active', 'partner_status' => 'approved']);

        $plan = PlatformPlan::create([
            'code' => 'PLAN_M',
            'name' => 'Plan',
            'price' => 500000,
            'currency' => 'VND',
            'billing_cycle' => 'monthly',
            'terms_version' => '1.0',
            'terms_text' => 'Terms',
            'is_active' => true,
        ]);

        $attempt = PlatformPaymentAttempt::create([
            'organization_id' => $org->id,
            'reference_code' => 'SUB-PAY-MISMATCH-001',
            'amount' => 500000,
            'currency' => 'VND',
            'provider' => 'payos',
            'status' => 'pending',
            'metadata' => ['plan_id' => $plan->id],
        ]);

        Sanctum::actingAs($owner);

        // Underpaid mismatch (400,000 VND instead of 500,000)
        $res = $this->postJson('/api/v1/partner/subscription/confirm-payment', [
            'reference_code' => 'SUB-PAY-MISMATCH-001',
            'provider_transaction_id' => 'TXN-MISMATCH-01',
            'amount' => 400000, // Mismatched!
            'currency' => 'VND',
        ]);

        $res->assertStatus(422);

        // Attempt marked failed
        $this->assertEquals('failed', $attempt->fresh()->status);
        $this->assertEquals('AMOUNT_OR_CURRENCY_MISMATCH', $attempt->fresh()->failure_reason);

        // NO subscription created!
        $this->assertEquals(0, PlatformSubscription::where('organization_id', $org->id)->count());
    }

    public function test_fail_subscription_payment_action_marks_failed_without_entitlement(): void
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'org', 'status' => 'active', 'partner_status' => 'approved']);

        $attempt = PlatformPaymentAttempt::create([
            'organization_id' => $org->id,
            'reference_code' => 'SUB-PAY-FAIL-001',
            'amount' => 500000,
            'currency' => 'VND',
            'provider' => 'payos',
            'status' => 'pending',
        ]);

        $failAction = app(FailSubscriptionPayment::class);
        $failed = $failAction->execute('SUB-PAY-FAIL-001', 'Người dùng hủy thanh toán trên cổng PayOS');

        $this->assertEquals('failed', $failed->status);
        $this->assertEquals('Người dùng hủy thanh toán trên cổng PayOS', $failed->failure_reason);
        $this->assertEquals(0, PlatformSubscription::where('organization_id', $org->id)->count());
    }
}
