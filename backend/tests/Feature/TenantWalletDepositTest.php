<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PaymentIntent;
use App\Models\ProviderEvent;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Payment\ProviderEventHandler;
use App\Services\Payment\WalletPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantWalletDepositTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_initiate_wallet_deposit(): void
    {
        $org = Organization::create(['name' => 'Deposit Org', 'slug' => 'dep-org-'.uniqid()]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/wallet/deposits', [
            'amount' => 200000,
            'organization_id' => $org->id,
            'provider' => 'sepay',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('amount', 200000)
            ->assertJsonPath('currency', 'VND')
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('organization_id', $org->id)
            ->assertJsonStructure([
                'id',
                'reference',
                'organization_id',
                'amount',
                'currency',
                'status',
                'provider',
                'transfer_info' => ['bank', 'account_number', 'account_name', 'code', 'amount', 'qr_url'],
                'created_at',
            ]);

        $this->assertDatabaseHas('payment_intents', [
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'amount' => 200000,
            'status' => 'pending',
        ]);
    }

    public function test_deposit_initiation_is_idempotent_with_same_key(): void
    {
        $org = Organization::create(['name' => 'Idempotent Org', 'slug' => 'idem-org-'.uniqid()]);
        $user = User::factory()->create();
        $idemKey = 'deposit-key-'.uniqid();

        $first = $this->actingAs($user)->postJson('/api/wallet/deposits', [
            'amount' => 300000,
            'organization_id' => $org->id,
            'idempotency_key' => $idemKey,
        ])->assertStatus(201)->json();

        $second = $this->actingAs($user)->postJson('/api/wallet/deposits', [
            'amount' => 300000,
            'organization_id' => $org->id,
            'idempotency_key' => $idemKey,
        ])->assertStatus(200)->json();

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame($first['reference'], $second['reference']);
        $this->assertDatabaseCount('payment_intents', 1);
    }

    public function test_deposit_status_returns_pending_without_provider_event(): void
    {
        $org = Organization::create(['name' => 'Unverified Org', 'slug' => 'unver-org-'.uniqid()]);
        $user = User::factory()->create();

        $init = $this->actingAs($user)->postJson('/api/wallet/deposits', [
            'amount' => 500000,
            'organization_id' => $org->id,
        ])->assertCreated()->json();

        // Customer returns from provider without a verified webhook event
        $statusResponse = $this->actingAs($user)->getJson('/api/wallet/deposits/'.$init['reference']);

        $statusResponse->assertOk()
            ->assertJsonPath('reference', $init['reference'])
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('wallet_balance', 0);

        // Wallet balance must still be 0
        $wallet = app(WalletPaymentService::class)->getOrCreateWallet($user->id, $org->id);
        $this->assertSame(0, $wallet->fresh()->balance);
    }

    public function test_deposit_history_lists_user_deposits(): void
    {
        $org = Organization::create(['name' => 'History Org', 'slug' => 'hist-org-'.uniqid()]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/wallet/deposits', [
            'amount' => 100000,
            'organization_id' => $org->id,
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/wallet/deposits', [
            'amount' => 200000,
            'organization_id' => $org->id,
        ])->assertCreated();

        $response = $this->actingAs($user)->getJson('/api/wallet/deposits?organization_id='.$org->id);
        $response->assertOk();
        $this->assertCount(2, $response->json('deposits'));
    }

    public function test_verified_provider_event_credits_wallet_and_updates_status(): void
    {
        $org = Organization::create(['name' => 'Provider Org', 'slug' => 'prov-org-'.uniqid()]);
        $user = User::factory()->create();

        $init = $this->actingAs($user)->postJson('/api/wallet/deposits', [
            'amount' => 500000,
            'organization_id' => $org->id,
            'provider' => 'payos',
        ])->assertCreated()->json();

        $intent = PaymentIntent::where('reference', $init['reference'])->firstOrFail();

        // Deliver verified ProviderEvent
        $event = ProviderEvent::create([
            'organization_id' => $org->id,
            'provider' => 'payos',
            'event_id' => 'evt-deposit-'.uniqid(),
            'type' => 'payment.succeeded',
            'status' => 'PAID',
            'reference' => $intent->reference,
            'amount' => 500000,
            'payload' => ['orderCode' => $intent->reference, 'status' => 'PAID'],
            'verified_at' => now(),
        ]);

        app(ProviderEventHandler::class)->handle($event);

        $this->assertSame('succeeded', $intent->fresh()->status);
        $this->assertNotNull($intent->fresh()->succeeded_at);

        $wallet = app(WalletPaymentService::class)->getOrCreateWallet($user->id, $org->id);
        $this->assertSame(500000, $wallet->fresh()->balance);

        // Status API reflects updated wallet balance and succeeded status
        $status = $this->actingAs($user)->getJson('/api/wallet/deposits/'.$intent->reference);
        $status->assertOk()
            ->assertJsonPath('status', 'succeeded')
            ->assertJsonPath('wallet_balance', 500000);
    }

    public function test_duplicate_provider_event_delivers_exactly_one_credit(): void
    {
        $org = Organization::create(['name' => 'Dup Org', 'slug' => 'dup-org-'.uniqid()]);
        $user = User::factory()->create();

        $init = $this->actingAs($user)->postJson('/api/wallet/deposits', [
            'amount' => 400000,
            'organization_id' => $org->id,
            'provider' => 'payos',
        ])->assertCreated()->json();

        $intent = PaymentIntent::where('reference', $init['reference'])->firstOrFail();

        $event = ProviderEvent::create([
            'organization_id' => $org->id,
            'provider' => 'payos',
            'event_id' => 'evt-dup-1',
            'type' => 'payment.succeeded',
            'status' => 'PAID',
            'reference' => $intent->reference,
            'amount' => 400000,
            'payload' => ['orderCode' => $intent->reference, 'status' => 'PAID'],
            'verified_at' => now(),
        ]);

        // First delivery
        app(ProviderEventHandler::class)->handle($event);
        $wallet = app(WalletPaymentService::class)->getOrCreateWallet($user->id, $org->id);
        $this->assertSame(400000, $wallet->fresh()->balance);

        // Second delivery (duplicate event)
        app(ProviderEventHandler::class)->handle($event);
        $this->assertSame(400000, $wallet->fresh()->balance);

        // Database transaction count for this intent must be exactly 1
        $this->assertSame(1, WalletTransaction::where('source_type', PaymentIntent::class)->where('source_id', $intent->id)->count());
    }

    public function test_invalid_signature_is_rejected_and_does_not_credit(): void
    {
        config(['services.sepay.hmac_secret' => 'valid-secret']);
        $user = User::factory()->create();

        $payload = ['id' => 'tx-invalid-sig', 'code' => "ORD NAP {$user->id}", 'transferAmount' => 500000, 'transferType' => 'in'];
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);

        // Send with wrong signature
        $response = $this->call('POST', '/api/v1/webhooks/sepay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SEPAY_SIGNATURE' => 'wrong-signature',
        ], $raw);

        $response->assertStatus(401);
        $this->assertDatabaseMissing('payments', ['external_transaction_id' => 'tx-invalid-sig']);
    }

    public function test_out_of_order_failed_event_does_not_reverse_succeeded_deposit(): void
    {
        $org = Organization::create(['name' => 'Order Org', 'slug' => 'order-org-'.uniqid()]);
        $user = User::factory()->create();

        $init = $this->actingAs($user)->postJson('/api/wallet/deposits', [
            'amount' => 600000,
            'organization_id' => $org->id,
            'provider' => 'payos',
        ])->assertCreated()->json();

        $intent = PaymentIntent::where('reference', $init['reference'])->firstOrFail();

        // Deposit succeeds first
        $paidEvent = ProviderEvent::create([
            'organization_id' => $org->id,
            'provider' => 'payos',
            'event_id' => 'evt-order-paid',
            'type' => 'payment.succeeded',
            'status' => 'PAID',
            'reference' => $intent->reference,
            'amount' => 600000,
            'payload' => ['status' => 'PAID'],
            'verified_at' => now(),
        ]);
        app(ProviderEventHandler::class)->handle($paidEvent);

        $wallet = app(WalletPaymentService::class)->getOrCreateWallet($user->id, $org->id);
        $this->assertSame(600000, $wallet->fresh()->balance);
        $this->assertSame('succeeded', $intent->fresh()->status);

        // Out of order FAILED event arrives later for same reference
        $failedEvent = ProviderEvent::create([
            'organization_id' => $org->id,
            'provider' => 'payos',
            'event_id' => 'evt-order-failed',
            'type' => 'payment.failed',
            'status' => 'FAILED',
            'reference' => $intent->reference,
            'amount' => 600000,
            'payload' => ['status' => 'FAILED'],
            'verified_at' => now(),
        ]);
        app(ProviderEventHandler::class)->handle($failedEvent);

        // Balance and status must remain succeeded
        $this->assertSame(600000, $wallet->fresh()->balance);
        $this->assertSame('succeeded', $intent->fresh()->status);
    }

    public function test_sepay_webhook_matches_payment_intent_and_credits_tenant_wallet(): void
    {
        config(['services.sepay.hmac_secret' => 'test-secret']);
        $org = Organization::create(['name' => 'SePay Tenant Org', 'slug' => 'sepay-tenant-'.uniqid()]);
        $user = User::factory()->create();

        $init = $this->actingAs($user)->postJson('/api/wallet/deposits', [
            'amount' => 250000,
            'organization_id' => $org->id,
            'provider' => 'sepay',
        ])->assertCreated()->json();

        $intent = PaymentIntent::where('reference', $init['reference'])->firstOrFail();
        $this->assertSame("ORD{$intent->id}", $init['transfer_info']['code']);

        $payload = [
            'id' => 'tx-sepay-dep-1',
            'code' => "ORD{$intent->id}",
            'transferAmount' => 250000,
            'transferType' => 'in',
        ];
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $raw, 'test-secret');

        $this->call('POST', '/api/v1/webhooks/sepay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SEPAY_SIGNATURE' => $signature,
        ], $raw)->assertOk();

        $this->assertSame('succeeded', $intent->fresh()->status);
        $wallet = app(WalletPaymentService::class)->getOrCreateWallet($user->id, $org->id);
        $this->assertSame(250000, $wallet->fresh()->balance);

        // Re-delivering same external transaction id does NOT double credit
        $this->call('POST', '/api/v1/webhooks/sepay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SEPAY_SIGNATURE' => $signature,
        ], $raw)->assertOk();

        $this->assertSame(250000, $wallet->fresh()->balance);
    }

    public function test_tenant_isolation_credits_correct_tenant_wallet(): void
    {
        $orgA = Organization::create(['name' => 'Org Alpha', 'slug' => 'org-alpha-'.uniqid()]);
        $orgB = Organization::create(['name' => 'Org Beta', 'slug' => 'org-beta-'.uniqid()]);
        $user = User::factory()->create();

        $initA = $this->actingAs($user)->postJson('/api/wallet/deposits', [
            'amount' => 300000,
            'organization_id' => $orgA->id,
            'provider' => 'payos',
        ])->assertCreated()->json();

        $intentA = PaymentIntent::where('reference', $initA['reference'])->firstOrFail();

        $event = ProviderEvent::create([
            'organization_id' => $orgA->id,
            'provider' => 'payos',
            'event_id' => 'evt-alpha-1',
            'type' => 'payment.succeeded',
            'status' => 'PAID',
            'reference' => $intentA->reference,
            'amount' => 300000,
            'payload' => ['status' => 'PAID'],
            'verified_at' => now(),
        ]);
        app(ProviderEventHandler::class)->handle($event);

        $walletA = app(WalletPaymentService::class)->getOrCreateWallet($user->id, $orgA->id);
        $walletB = app(WalletPaymentService::class)->getOrCreateWallet($user->id, $orgB->id);

        $this->assertSame(300000, $walletA->fresh()->balance);
        $this->assertSame(0, $walletB->fresh()->balance);
    }
}
