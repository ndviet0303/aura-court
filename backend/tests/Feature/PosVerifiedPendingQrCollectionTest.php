<?php

namespace Tests\Feature;

use App\Domain\Payment\Actions\ProcessQrPayment;
use App\Models\CashShift;
use App\Models\FinancialJournalEntry;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PosOrder;
use App\Models\PosPayment;
use App\Models\PosProduct;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosVerifiedPendingQrCollectionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $tenant;
    private User $cashier;
    private CashShift $shift;
    private PosProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Organization::create([
            'name' => 'Aura Arena POS Tenant',
            'slug' => 'aura-pos-' . uniqid(),
            'status' => 'active',
        ]);

        config([
            'features.flags.tenant_pos_and_wallet' => true,
            'features.pilot_organizations' => [(string) $this->tenant->id],
            'services.payments.qr_enabled' => true,
            'services.payments.simulate_qr_provider' => true,
            'services.sepay.bank' => 'MBBank',
            'services.sepay.account' => '0901234567',
            'services.sepay.account_name' => 'AURA COURT SYSTEM',
            'services.sepay.hmac_secret' => 'test-hmac-secret',
        ]);

        $this->cashier = User::factory()->create(['name' => 'Cashier Nam']);
        OrganizationMember::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->cashier->id,
            'role' => OrganizationMember::ROLE_CASHIER,
            'status' => 'active',
        ]);

        $this->shift = CashShift::create([
            'organization_id' => $this->tenant->id,
            'cashier_id' => $this->cashier->id,
            'opening_cash' => 500000,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $this->product = PosProduct::create([
            'organization_id' => $this->tenant->id,
            'sku' => 'DRINK-POCARI',
            'name' => 'Nước bù khoáng Pocari 500ml',
            'price' => 20000,
            'cost' => 12000,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);
    }

    public function test_qr_payment_rejected_if_provider_verification_is_unavailable(): void
    {
        // Disable QR provider verification
        config([
            'services.payments.qr_enabled' => false,
            'services.payments.simulate_qr_provider' => false,
        ]);

        $response = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
            'payment_method' => 'qr',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'qr-test-disabled-' . uniqid(),
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'QR payment provider verification is unavailable.']);
    }

    public function test_pending_qr_order_creation_does_not_finalize_inventory_or_financial_effects(): void
    {
        $idempotencyKey = 'qr-init-' . uniqid();
        $response = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 3],
            ],
            'payment_method' => 'qr',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $response->assertCreated();

        $orderId = $response->json('order.id');
        $order = PosOrder::findOrFail($orderId);

        // 1. Order and payment are PENDING
        $this->assertSame('pending_payment', $order->status);
        $this->assertNull($order->completed_at);
        $this->assertSame('qr', $order->payment_method);
        $this->assertSame(60000, $order->total_amount);

        $payment = PosPayment::where('order_id', $orderId)->first();
        $this->assertNotNull($payment);
        $this->assertSame('pending', $payment->status);
        $this->assertSame(60000, $payment->amount);

        // 2. Receipt metadata shows pending status and is_paid: false
        $receipt = $response->json('receipt');
        $this->assertSame('pending_payment', $receipt['status']);
        $this->assertFalse($receipt['is_paid']);
        $this->assertNotNull($receipt['qr_data']);
        $this->assertSame('MBBank', $receipt['qr_data']['bank']);
        $this->assertSame('0901234567', $receipt['qr_data']['account']);
        $this->assertSame(60000, $receipt['qr_data']['amount']);

        // 3. INVENTORY IS NOT FINALIZED YET: Stock remains 50!
        $this->assertSame(50, $this->product->fresh()->stock_quantity);

        // 5. NO FINANCIAL JOURNAL POSTED YET
        $this->assertDatabaseMissing('journals', [
            'organization_id' => $this->tenant->id,
            'event_type' => 'pos.sale',
            'event_id' => "pos:{$orderId}",
        ]);
    }

    public function test_verified_qr_confirmation_atomically_decrements_stock_and_posts_financial_effects(): void
    {
        $idempotencyKey = 'qr-confirm-' . uniqid();
        $createRes = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 4],
            ],
            'payment_method' => 'qr',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $orderId = $createRes->json('order.id');
        $order = PosOrder::findOrFail($orderId);
        $this->assertSame('pending_payment', $order->status);
        // 1) Before seeding, assert that posting an unverified self-declared reference is rejected with 422
        $unverifiedRes = $this->actingAs($this->cashier)->postJson("/api/v2/pos/orders/{$orderId}/confirm-qr", [
            'external_transaction_id' => 'UNVERIFIED-FAKE-REF',
            'amount' => 80000,
            'provider' => 'vietqr',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'unverified-key-' . uniqid(),
        ]);
        $unverifiedRes->assertStatus(422)
            ->assertJsonFragment(['message' => 'Payment has not been verified by payment provider.']);

        // 2) Seed the verified payment record
        \App\Models\Payment::create([
            'booking_id' => null,
            'provider' => 'vietqr',
            'external_transaction_id' => 'MB-TXN-998877',
            'amount' => 80000,
            'transfer_code' => "POS {$order->payment_reference}",
            'status' => 'paid',
            'received_at' => now(),
        ]);

        // 3) Then the cashier confirms with verified transaction ID and it succeeds with 200
        $confirmRes = $this->actingAs($this->cashier)->postJson("/api/v2/pos/orders/{$orderId}/confirm-qr", [
            'external_transaction_id' => 'MB-TXN-998877',
            'amount' => 80000,
            'provider' => 'vietqr',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'confirm-key-' . uniqid(),
        ]);

        $confirmRes->assertOk()
            ->assertJsonPath('order.status', 'completed')
            ->assertJsonPath('receipt.is_paid', true)
            ->assertJsonPath('receipt.status', 'completed');

        $freshOrder = $order->fresh();
        $this->assertSame('completed', $freshOrder->status);
        $this->assertNotNull($freshOrder->completed_at);

        // Payment status is updated to paid with provider reference
        $payment = PosPayment::where('order_id', $orderId)->first();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('MB-TXN-998877', $payment->provider_reference);

        // Inventory is NOW atomically decremented: 50 - 4 = 46
        $this->assertSame(46, $this->product->fresh()->stock_quantity);

        // Stock movement recorded
        $this->assertDatabaseHas('stock_movements', [
            'organization_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'reference_type' => PosOrder::class,
            'reference_id' => $orderId,
            'type' => 'sale',
            'quantity_delta' => -4,
        ]);

        // Financial journal is posted
        $this->assertDatabaseHas('journals', [
            'organization_id' => $this->tenant->id,
            'event_type' => 'pos.sale',
            'event_id' => "pos:{$orderId}",
        ]);

        // Re-confirming completed order rejects with 409
        $dupConfirm = $this->actingAs($this->cashier)->postJson("/api/v2/pos/orders/{$orderId}/confirm-qr", [
            'external_transaction_id' => 'MB-TXN-998877',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'dup-confirm-' . uniqid(),
        ]);
        $dupConfirm->assertStatus(409);
    }

    public function test_cancellation_and_expiry_preserves_stock_and_prevents_financial_effects(): void
    {
        $createRes = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 5],
            ],
            'payment_method' => 'qr',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'qr-cancel-test-' . uniqid(),
        ]);

        $orderId = $createRes->json('order.id');

        // Cashier or timeout cancels the QR order
        $cancelRes = $this->actingAs($this->cashier)->postJson("/api/v2/pos/orders/{$orderId}/cancel-qr", [
            'reason' => 'Customer timed out or changed payment method',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'cancel-key-' . uniqid(),
        ]);

        $cancelRes->assertOk()
            ->assertJsonPath('order.status', 'cancelled')
            ->assertJsonPath('receipt.is_paid', false)
            ->assertJsonPath('receipt.status', 'cancelled');

        $order = PosOrder::findOrFail($orderId);
        $this->assertSame('cancelled', $order->status);

        $payment = PosPayment::where('order_id', $orderId)->first();
        $this->assertSame('cancelled', $payment->status);

        // Stock quantity remains unchanged at 50
        $this->assertSame(50, $this->product->fresh()->stock_quantity);

        // No financial entries created
        $this->assertDatabaseMissing('journals', [
            'organization_id' => $this->tenant->id,
            'event_type' => 'pos.sale',
            'event_id' => "pos:{$orderId}",
        ]);
        // Cannot confirm a cancelled order
        $confirmCancelled = $this->actingAs($this->cashier)->postJson("/api/v2/pos/orders/{$orderId}/confirm-qr", [
            'external_transaction_id' => 'TX-LATE-123',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'confirm-cancelled-' . uniqid(),
        ]);
        $confirmCancelled->assertStatus(409);
    }

    public function test_webhook_automatically_confirms_pending_pos_qr_order(): void
    {
        $createRes = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
            'payment_method' => 'qr',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'qr-webhook-test-' . uniqid(),
        ]);

        $orderId = $createRes->json('order.id');
        $order = PosOrder::findOrFail($orderId);
        $orderCode = $order->payment_reference;

        // Simulate incoming VietQR / SePay webhook with POS order code in content
        $action = app(ProcessQrPayment::class);
        $webhookData = [
            'id' => 'SEPAY-TXN-POS-42',
            'transferAmount' => 40000,
            'transferType' => 'in',
            'content' => "ORD POS {$orderCode}",
        ];
        $raw = json_encode($webhookData);

        $action->execute($webhookData, $raw);

        // The POS order is automatically confirmed by webhook!
        $freshOrder = $order->fresh();
        $this->assertSame('completed', $freshOrder->status);
        $this->assertSame(48, $this->product->fresh()->stock_quantity);

        // Financial posting exists
        $this->assertDatabaseHas('journals', [
            'organization_id' => $this->tenant->id,
            'event_type' => 'pos.sale',
            'event_id' => "pos:{$orderId}",
        ]);
    }
}
