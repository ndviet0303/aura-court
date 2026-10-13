<?php

namespace Tests\Feature\Subscription;

use App\Domain\Subscription\Actions\ConfirmSubscriptionPayment;
use App\Domain\Subscription\Actions\FailSubscriptionPayment;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPaymentAttempt;
use App\Models\PlatformPlan;
use App\Models\PlatformProviderReceipt;
use App\Models\PlatformSubscription;
use App\Models\ProviderEvent;
use App\Models\User;
use App\Services\Payment\ProviderEventHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        $this->verifiedReceipt($attempt, 'TXN-PAYOS-998877', 600000, 'VND');

        Sanctum::actingAs($owner);

        $result = app(ConfirmSubscriptionPayment::class)->execute(
            referenceCode: 'SUB-PAY-TEST-001',
            providerTransactionId: 'TXN-PAYOS-998877',
            amount: 600000,
            currency: 'VND',
            rawPayload: ['ignored_untrusted_fixture_payload' => true],
            actorId: $owner->id,
        );
        $this->assertFalse($result['is_duplicate']);
        $this->assertSame('active', $result['subscription']->status);
        $this->assertTrue($result['subscription']->isValid());

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
        $this->verifiedReceipt($attempt, 'TXN-DUP-01', 500000, 'VND');

        Sanctum::actingAs($owner);

        // First confirmation
        $res1 = app(ConfirmSubscriptionPayment::class)->execute(
            referenceCode: 'SUB-PAY-DUP-001',
            providerTransactionId: 'TXN-DUP-01',
            amount: 500000,
            currency: 'VND',
        );
        $endsAt1 = $res1['subscription']->ends_at->toIso8601String();

        $res2 = app(ConfirmSubscriptionPayment::class)->execute(
            referenceCode: 'SUB-PAY-DUP-001',
            providerTransactionId: 'TXN-DUP-01',
            amount: 500000,
            currency: 'VND',
        );
        $this->assertTrue($res2['is_duplicate']);

        // Assert exactly 1 receipt and identical ends_at (NOT extended!)
        $this->assertEquals(1, PlatformProviderReceipt::where('payment_attempt_id', $attempt->id)->count());
        $this->assertEquals($endsAt1, $res2['subscription']->ends_at->toIso8601String());
    }

    public function test_paid_terms_use_one_six_and_twelve_calendar_months_with_month_end_clamping(): void
    {
        $this->travelTo(Carbon::create(2024, 1, 31, 10, 0, 0));
        $owner = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($owner);

        foreach ([
            ['cycle' => 'monthly', 'months' => 1, 'expected_end' => '2024-02-29'],
            ['cycle' => 'six_months', 'months' => 6, 'expected_end' => '2024-07-31'],
            ['cycle' => 'yearly', 'months' => 12, 'expected_end' => '2025-01-31'],
        ] as $index => $case) {
            $org = Organization::create([
                'name' => "Calendar Term Club {$index}",
                'slug' => "calendar-term-club-{$index}",
                'status' => 'active',
                'partner_status' => 'approved',
            ]);
            $plan = PlatformPlan::create([
                'code' => "CALENDAR-{$index}",
                'name' => "Calendar {$case['months']} month plan",
                'price' => 600000,
                'currency' => 'VND',
                'billing_cycle' => $case['cycle'],
                'terms_version' => '2026.1',
                'terms_text' => 'Calendar month terms.',
                'is_active' => true,
            ]);
            $reference = "SUB-CALENDAR-{$index}";
            PlatformPaymentAttempt::create([
                'organization_id' => $org->id,
                'reference_code' => $reference,
                'amount' => 600000,
                'currency' => 'VND',
                'provider' => 'payos',
                'status' => 'pending',
                'metadata' => [
                    'plan_id' => $plan->id,
                    'plan_version' => 1,
                    'billing_cycle' => $case['cycle'],
                    'billing_months' => $case['months'],
                    'accepted_terms_version' => '2026.1',
                ],
            ]);
            $attempt = PlatformPaymentAttempt::where('reference_code', $reference)->firstOrFail();
            $this->verifiedReceipt($attempt, "TXN-CALENDAR-{$index}", 600000, 'VND');

            app(ConfirmSubscriptionPayment::class)->execute(
                referenceCode: $reference,
                providerTransactionId: "TXN-CALENDAR-{$index}",
                amount: 600000,
                currency: 'VND',
            );

            $subscription = PlatformSubscription::where('organization_id', $org->id)->firstOrFail();
            $this->assertSame($case['expected_end'], $subscription->ends_at->toDateString());
        }
    }

    public function test_monthly_renewal_preserves_the_original_month_end_anchor(): void
    {
        $this->travelTo(Carbon::create(2024, 1, 31, 10, 0, 0));
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create([
            'name' => 'Month End Renewal Club',
            'slug' => 'month-end-renewal-club',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $plan = PlatformPlan::create([
            'code' => 'MONTH-END',
            'name' => 'Month End Plan',
            'price' => 600000,
            'currency' => 'VND',
            'billing_cycle' => 'monthly',
            'terms_version' => '2026.1',
            'terms_text' => 'Calendar month terms.',
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner);
        foreach ([1, 2] as $attemptNumber) {
            $reference = "SUB-MONTH-END-{$attemptNumber}";
            PlatformPaymentAttempt::create([
                'organization_id' => $org->id,
                'reference_code' => $reference,
                'amount' => 600000,
                'currency' => 'VND',
                'provider' => 'payos',
                'status' => 'pending',
                'metadata' => [
                    'plan_id' => $plan->id,
                    'plan_version' => 1,
                    'billing_cycle' => 'monthly',
                    'billing_months' => 1,
                    'accepted_terms_version' => '2026.1',
                ],
            ]);
            $attempt = PlatformPaymentAttempt::where('reference_code', $reference)->firstOrFail();
            $this->verifiedReceipt($attempt, "TXN-MONTH-END-{$attemptNumber}", 600000, 'VND');

            app(ConfirmSubscriptionPayment::class)->execute(
                referenceCode: $reference,
                providerTransactionId: "TXN-MONTH-END-{$attemptNumber}",
                amount: 600000,
                currency: 'VND',
            );
        }

        $subscription = PlatformSubscription::where('organization_id', $org->id)->firstOrFail();
        $this->assertSame('2024-03-31', $subscription->ends_at->toDateString());
    }

    public function test_client_claim_with_matching_amount_but_no_verified_evidence_stays_pending(): void
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
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        Sanctum::actingAs($owner);

        $res = $this->postJson('/api/partner/subscription/confirm-payment', [
            'reference_code' => 'SUB-PAY-MISMATCH-001',
            'provider_transaction_id' => 'TXN-MISMATCH-01',
            'amount' => 500000,
            'currency' => 'VND',
        ]);

        $res->assertOk()->assertJsonPath('data.status', 'pending');

        $this->assertEquals('pending', $attempt->fresh()->status);
        $this->assertDatabaseCount('platform_provider_receipts', 0);

        // NO subscription created!
        $this->assertEquals(0, PlatformSubscription::where('organization_id', $org->id)->count());
    }

    public function test_signature_verified_platform_provider_event_records_receipt_and_activates_attempt(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create(['name' => 'Webhook Club', 'slug' => 'webhook-club', 'status' => 'active', 'partner_status' => 'approved']);
        $plan = PlatformPlan::create([
            'code' => 'WEBHOOK_PLAN', 'name' => 'Webhook plan', 'price' => 500000, 'currency' => 'VND',
            'billing_cycle' => 'monthly', 'terms_version' => '1.0', 'terms_text' => 'Terms', 'is_active' => true,
        ]);
        $attempt = PlatformPaymentAttempt::create([
            'organization_id' => $org->id,
            'reference_code' => 'SUB-PAY-WEBHOOK-001',
            'amount' => 500000,
            'currency' => 'VND',
            'provider' => 'payos',
            'provider_ref' => 'PAYOS-INTENT-WEBHOOK-1',
            'status' => 'pending',
            'metadata' => ['plan_id' => $plan->id, 'plan_version' => 1, 'billing_cycle' => 'monthly', 'accepted_terms_version' => '1.0', 'actor_id' => $owner->id],
        ]);
        config(['services.payos.webhook_secret' => 'platform-test-webhook-secret']);

        $data = [
            'orderCode' => 14001,
            'amount' => 500000,
            'description' => $attempt->reference_code,
            'reference' => $attempt->reference_code,
            'transactionDateTime' => now()->toDateTimeString(),
            'currency' => 'VND',
            'paymentLinkId' => $attempt->provider_ref,
            'code' => '00',
            'desc' => 'Thành công',
        ];
        $payload = [
            'id' => 'payos-event-platform-1',
            'code' => '00',
            'desc' => 'success',
            'success' => true,
            'data' => $data,
            'signature' => $this->payosSignature($data, 'platform-test-webhook-secret'),
        ];
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $tamperedPayload = $payload;
        $tamperedPayload['data']['amount'] = 500001;
        $tampered = json_encode($tamperedPayload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/webhooks/payos', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $tampered)->assertUnauthorized();
        $this->assertSame('pending', $attempt->fresh()->status);
        $this->assertDatabaseCount('platform_provider_receipts', 0);
        $this->assertDatabaseCount('journals', 0);

        $this->call('POST', '/api/webhooks/payos', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $raw)->assertOk();

        $event = ProviderEvent::where('event_id', 'payos-event-platform-1')->firstOrFail();
        app(ProviderEventHandler::class)->handle($event);

        $this->assertSame('succeeded', $attempt->fresh()->status);
        $this->assertDatabaseHas('platform_provider_receipts', [
            'payment_attempt_id' => $attempt->id,
            'provider' => 'payos',
            'provider_transaction_id' => 'PAYOS-INTENT-WEBHOOK-1',
            'amount' => 500000,
            'currency' => 'VND',
        ]);
        $this->assertDatabaseHas('platform_subscriptions', ['organization_id' => $org->id, 'status' => 'active']);
        $this->assertDatabaseHas('journals', [
            'event_type' => 'platform.subscription_receipt',
            'ledger_entity' => 'platform',
            'payment_model' => 'platform_subscription_v1',
        ]);
    }

    public function test_verified_platform_event_with_wrong_amount_does_not_create_receipt_or_activate(): void
    {
        $org = Organization::create(['name' => 'Mismatch Club', 'slug' => 'mismatch-club', 'status' => 'active', 'partner_status' => 'approved']);
        $plan = PlatformPlan::create([
            'code' => 'MISMATCH_PLAN', 'name' => 'Mismatch plan', 'price' => 500000, 'currency' => 'VND',
            'billing_cycle' => 'monthly', 'terms_version' => '1.0', 'terms_text' => 'Terms', 'is_active' => true,
        ]);
        $attempt = PlatformPaymentAttempt::create([
            'organization_id' => $org->id,
            'reference_code' => 'SUB-PAY-WEBHOOK-MISMATCH',
            'amount' => 500000,
            'currency' => 'VND',
            'provider' => 'payos',
            'provider_ref' => 'PAYOS-INTENT-WEBHOOK-MISMATCH',
            'status' => 'pending',
            'metadata' => ['plan_id' => $plan->id, 'plan_version' => 1, 'billing_cycle' => 'monthly', 'accepted_terms_version' => '1.0'],
        ]);
        config(['services.payos.webhook_secret' => 'platform-test-webhook-secret']);

        $data = [
            'orderCode' => 14002,
            'amount' => 400000,
            'description' => $attempt->reference_code,
            'reference' => $attempt->reference_code,
            'transactionDateTime' => now()->toDateTimeString(),
            'currency' => 'VND',
            'paymentLinkId' => $attempt->provider_ref,
            'code' => '00',
            'desc' => 'Thành công',
        ];
        $payload = [
            'id' => 'payos-event-platform-mismatch',
            'code' => '00',
            'desc' => 'success',
            'success' => true,
            'data' => $data,
            'signature' => $this->payosSignature($data, 'platform-test-webhook-secret'),
        ];
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/webhooks/payos', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $raw)->assertOk();
        $event = ProviderEvent::where('event_id', 'payos-event-platform-mismatch')->firstOrFail();
        app(ProviderEventHandler::class)->handle($event);

        $this->assertSame('pending', $attempt->fresh()->status);
        $this->assertDatabaseMissing('platform_provider_receipts', ['payment_attempt_id' => $attempt->id]);
        $this->assertDatabaseMissing('platform_subscriptions', ['organization_id' => $org->id]);
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

    private function verifiedReceipt(PlatformPaymentAttempt $attempt, string $transactionId, int $amount, string $currency): PlatformProviderReceipt
    {
        return PlatformProviderReceipt::create([
            'payment_attempt_id' => $attempt->id,
            'provider' => $attempt->provider,
            'provider_transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => $currency,
            'raw_payload' => ['verified_fixture' => true],
            'verified_at' => now(),
        ]);
    }

    private function payosSignature(array $data, string $checksumKey): string
    {
        ksort($data, SORT_STRING);
        $canonical = collect($data)->map(function ($value, $key): string {
            if ($value === null || in_array($value, ['null', 'undefined'], true)) {
                $value = '';
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            }

            return $key.'='.$value;
        })->implode('&');

        return hash_hmac('sha256', $canonical, $checksumKey);
    }
}
