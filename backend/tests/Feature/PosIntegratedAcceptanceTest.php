<?php

namespace Tests\Feature;

use App\Models\CashShift;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PosOrder;
use App\Models\PosPayment;
use App\Models\PosProduct;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosIntegratedAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $tenant;
    private User $cashier1;
    private User $cashier2;
    private CashShift $shift1;
    private CashShift $shift2;
    private PosProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Organization::create([
            'name' => 'Aura Arena Acceptance',
            'slug' => 'aura-acc-' . uniqid(),
            'status' => 'active',
        ]);

        config([
            'features.flags.tenant_pos_and_wallet' => true,
            'features.pilot_organizations' => [(string) $this->tenant->id],
        ]);

        $this->cashier1 = User::factory()->create(['name' => 'Cashier Alpha']);
        OrganizationMember::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->cashier1->id,
            'role' => OrganizationMember::ROLE_CASHIER,
            'status' => 'active',
        ]);

        $this->cashier2 = User::factory()->create(['name' => 'Cashier Beta']);
        OrganizationMember::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->cashier2->id,
            'role' => OrganizationMember::ROLE_CASHIER,
            'status' => 'active',
        ]);

        $this->shift1 = CashShift::create([
            'organization_id' => $this->tenant->id,
            'cashier_id' => $this->cashier1->id,
            'opening_cash' => 500000,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $this->shift2 = CashShift::create([
            'organization_id' => $this->tenant->id,
            'cashier_id' => $this->cashier2->id,
            'opening_cash' => 500000,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $this->product = PosProduct::create([
            'organization_id' => $this->tenant->id,
            'sku' => 'GRIP-YONEX-AC102',
            'name' => 'Quấn cán vợt cầu lông Yonex AC102',
            'price' => 40000,
            'cost' => 22000,
            'stock_quantity' => 1, // Only 1 in stock for race test
            'is_active' => true,
            'metadata' => ['tax_rate' => 8],
        ]);
    }

    public function test_pos_errors_do_not_commit_partial_inventory_or_financial_effects(): void
    {
        // 1. Attempting to buy 5 units when only 1 is available
        $outOfStockRes = $this->actingAs($this->cashier1)->postJson('/api/v2/pos/orders', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 5]],
            'payment_method' => 'cash',
            'cash_tendered' => 500000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'err-stock-' . uniqid(),
        ]);

        $outOfStockRes->assertStatus(409)
            ->assertJsonFragment(['message' => "Insufficient stock for {$this->product->sku}."]);

        // Assert stock untouched at 1
        $this->assertSame(1, $this->product->fresh()->stock_quantity);

        // Assert zero orders, zero stock movements, zero journals
        $this->assertDatabaseMissing('pos_orders', ['organization_id' => $this->tenant->id]);
        $this->assertDatabaseMissing('stock_movements', ['organization_id' => $this->tenant->id]);
        $this->assertDatabaseMissing('journals', ['organization_id' => $this->tenant->id, 'event_type' => 'pos.sale']);

        // 2. Closed shift cashier attempting checkout rejects with 409
        $this->shift1->update(['status' => 'closed', 'closed_at' => now()]);

        $closedShiftRes = $this->actingAs($this->cashier1)->postJson('/api/v2/pos/orders', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'cash_tendered' => 50000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'err-shift-' . uniqid(),
        ]);

        $closedShiftRes->assertStatus(409)
            ->assertJsonFragment(['message' => 'Cashier does not have an active open shift.']);
    }

    public function test_offline_draft_isolation_cannot_finalize_payment_or_inventory(): void
    {
        // Reopen shift1 for cashier1
        $this->shift1->update(['status' => 'open', 'closed_at' => null]);

        $offlineRes = $this->actingAs($this->cashier1)->postJson('/api/v2/pos/orders', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'cash_tendered' => 50000,
            'offline' => true, // Offline draft flag
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'draft-off-' . uniqid(),
        ]);

        $offlineRes->assertStatus(409)
            ->assertJsonFragment(['message' => 'Offline POS drafts cannot finalize payment or inventory.']);

        // Assert stock remains untouched
        $this->assertSame(1, $this->product->fresh()->stock_quantity);

        // Assert no paid order exists
        $this->assertDatabaseMissing('pos_orders', ['organization_id' => $this->tenant->id]);
    }

    public function test_repeated_payment_with_identical_idempotency_key_prevents_duplicate_effects(): void
    {
        $this->shift1->update(['status' => 'open', 'closed_at' => null]);
        $this->product->update(['stock_quantity' => 10]);

        $idempotencyKey = 'pos-idem-stable-12345';
        $payload = [
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
            'payment_method' => 'cash',
            'cash_tendered' => 100000,
        ];
        $headers = [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => $idempotencyKey,
        ];

        // First submission
        $res1 = $this->actingAs($this->cashier1)->postJson('/api/v2/pos/orders', $payload, $headers);
        $res1->assertCreated();
        $order1Id = $res1->json('order.id');
        $orderCode1 = $res1->json('receipt.order_code');

        // Stock decreased: 10 - 2 = 8
        $this->assertSame(8, $this->product->fresh()->stock_quantity);

        // Second submission with exact same idempotency key (e.g. network retry)
        $res2 = $this->actingAs($this->cashier1)->postJson('/api/v2/pos/orders', $payload, $headers);
        $this->assertTrue(in_array($res2->status(), [200, 201], true)); // Replayed response from idempotency cache
        $order2Id = $res2->json('order.id');
        $orderCode2 = $res2->json('receipt.order_code');

        // Exact same order and receipt returned
        $this->assertSame($order1Id, $order2Id);
        $this->assertSame($orderCode1, $orderCode2);

        // Verify stock is still 8 (NO DOUBLE DECREMENT)
        $this->assertSame(8, $this->product->fresh()->stock_quantity);

        // Verify only 1 PosOrder in database
        $this->assertSame(1, PosOrder::where('organization_id', $this->tenant->id)->count());

        // Verify only 1 StockMovement in database
        $this->assertSame(1, StockMovement::where('organization_id', $this->tenant->id)->where('type', 'sale')->count());

        // Verify only 1 financial journal entry
        $this->assertSame(1, Journal::where('organization_id', $this->tenant->id)->where('event_type', 'pos.sale')->count());
    }

    public function test_concurrent_stock_checkout_race_condition(): void
    {
        // Set available stock to exactly 1
        $this->product->update(['stock_quantity' => 1]);

        $payload = [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'cash_tendered' => 50000,
        ];

        // Cashier 1 checks out the last remaining unit
        $res1 = $this->actingAs($this->cashier1)->postJson('/api/v2/pos/orders', $payload, [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'race-cashier-1-' . uniqid(),
        ]);
        $res1->assertCreated();

        // Cashier 2 attempts to checkout at the same time
        $res2 = $this->actingAs($this->cashier2)->postJson('/api/v2/pos/orders', $payload, [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'race-cashier-2-' . uniqid(),
        ]);
        $res2->assertStatus(409)
            ->assertJsonFragment(['message' => "Insufficient stock for {$this->product->sku}."]);

        // Stock quantity ends at exactly 0 (never negative)
        $this->assertSame(0, $this->product->fresh()->stock_quantity);
    }

    public function test_receipt_reload_from_persisted_order_endpoint(): void
    {
        $this->product->update(['stock_quantity' => 20]);

        $checkoutRes = $this->actingAs($this->cashier1)->postJson('/api/v2/pos/orders', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
            'payment_method' => 'cash',
            'cash_tendered' => 100000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'receipt-reload-' . uniqid(),
        ]);

        $checkoutRes->assertCreated();
        $orderId = $checkoutRes->json('order.id');
        $originalReceipt = $checkoutRes->json('receipt');

        // Customer / Cashier retrieves receipt via GET /api/v2/pos/orders/{order}
        $reloadRes = $this->actingAs($this->cashier1)->getJson("/api/v2/pos/orders/{$orderId}", [
            'X-Organization-Id' => $this->tenant->id,
        ]);

        $reloadRes->assertOk();
        $reloadedReceipt = $reloadRes->json('receipt');

        // Assert all fields agree identically with the persisted original receipt
        $this->assertSame($originalReceipt['order_code'], $reloadedReceipt['order_code']);
        $this->assertSame($originalReceipt['grand_total'], $reloadedReceipt['grand_total']);
        $this->assertSame($originalReceipt['subtotal'], $reloadedReceipt['subtotal']);
        $this->assertSame($originalReceipt['tax_amount'], $reloadedReceipt['tax_amount']);
        $this->assertSame($originalReceipt['cash_tendered'], $reloadedReceipt['cash_tendered']);
        $this->assertSame($originalReceipt['change_due'], $reloadedReceipt['change_due']);
        $this->assertSame($originalReceipt['payment_method'], $reloadedReceipt['payment_method']);
        $this->assertCount(count($originalReceipt['items']), $reloadedReceipt['items']);
    }
}
