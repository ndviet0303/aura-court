<?php

namespace Tests\Feature\POS;

use App\Models\CashShift;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Payment;
use App\Models\PosOrder;
use App\Models\PosProduct;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Services\POS\PosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosShiftControlsAndOrderProvenanceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $tenant;

    private Venue $venue;

    private User $cashier;

    private User $manager;

    private User $customer;

    private PosProduct $drinkProduct;

    private PosProduct $serviceProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Organization::create([
            'name' => 'Aura Central Arena',
            'slug' => 'aura-central-'.uniqid(),
            'status' => 'active',
        ]);

        $this->venue = Venue::create([
            'organization_id' => $this->tenant->id,
            'name' => 'Central Courts',
            'slug' => 'central-courts-'.uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);

        $this->cashier = User::factory()->create(['name' => 'Cashier Mai']);
        OrganizationMember::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->cashier->id,
            'role' => OrganizationMember::ROLE_CASHIER,
            'status' => 'active',
        ]);

        $this->manager = User::factory()->create(['name' => 'Manager An']);
        OrganizationMember::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->manager->id,
            'role' => OrganizationMember::ROLE_VENUE_MANAGER,
            'status' => 'active',
        ]);

        $this->customer = User::factory()->create(['name' => 'Customer Hoang']);

        // Tracked retail product
        $this->drinkProduct = PosProduct::create([
            'organization_id' => $this->tenant->id,
            'sku' => 'ENERGY-DRINK',
            'name' => 'Nước tăng lực Revive',
            'price' => 15000,
            'cost' => 8000,
            'stock_quantity' => 20,
            'is_active' => true,
            'metadata' => [
                'category' => 'general',
                'tax_rate' => 0,
                'track_stock' => true,
            ],
        ]);

        // Untracked service product (e.g. racket restringing)
        $this->serviceProduct = PosProduct::create([
            'organization_id' => $this->tenant->id,
            'sku' => 'STRINGING-SVC',
            'name' => 'Dịch vụ căng cước vợt',
            'price' => 100000,
            'cost' => 30000,
            'stock_quantity' => 0,
            'is_active' => true,
            'metadata' => [
                'category' => 'services',
                'tax_rate' => 0,
                'is_service' => true,
            ],
        ]);

        // Venue policy with mixed tender enabled
        VenueBookingPolicy::create([
            'venue_id' => $this->venue->id,
            'version' => 1,
            'created_by' => $this->manager->id,
            'effective_at' => now(),
            'settings' => [
                'pos' => [
                    'allow_mixed_tender' => true,
                ],
            ],
        ]);

        config([
            'features.flags.tenant_pos_and_wallet' => true,
            'features.pilot_organizations' => [(string) $this->tenant->id],
            'services.payments.simulate_qr_provider' => true,
        ]);
    }

    public function test_qr_payment_enforces_amount_and_provenance_verification(): void
    {
        // 1. Open shift
        $this->actingAs($this->cashier)->postJson('/api/v2/pos/shifts', [
            'opening_cash' => 500000,
            'venue_id' => $this->venue->id,
            'register_id' => 'REG-01',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'open-'.uniqid(),
        ])->assertCreated();

        // 2. Create pending QR order for 2 drinks (30,000 VND)
        $orderRes = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'items' => [
                ['product_id' => $this->drinkProduct->id, 'quantity' => 2],
            ],
            'payment_method' => 'qr',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'order-'.uniqid(),
        ]);
        $orderRes->assertCreated();
        $orderId = $orderRes->json('order.id');
        $order = PosOrder::findOrFail($orderId);
        $this->assertSame('pending_payment', $order->status);

        // 3. Amount mismatch rejection (passed amount less than order total)
        $mismatchRes = $this->actingAs($this->cashier)->postJson("/api/v2/pos/orders/{$orderId}/confirm-qr", [
            'external_transaction_id' => 'TXN-FAKE-123',
            'amount' => 10000, // Expected 30,000
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'confirm-bad-'.uniqid(),
        ]);
        $mismatchRes->assertStatus(422);

        // 4. When simulate_qr_provider is false, unverified self-declared reference rejected
        config(['services.payments.simulate_qr_provider' => false]);
        $unverifiedRes = $this->actingAs($this->cashier)->postJson("/api/v2/pos/orders/{$orderId}/confirm-qr", [
            'external_transaction_id' => 'TXN-UNVERIFIED-999',
            'amount' => 30000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'confirm-unverified-'.uniqid(),
        ]);
        $unverifiedRes->assertStatus(422);
        $this->assertStringContainsString('not been verified by payment provider', $unverifiedRes->json('message'));

        // 5. With genuine verified transaction, verification succeeds
        Payment::create([
            'booking_id' => null,
            'provider' => 'vietqr',
            'external_transaction_id' => 'TXN-VERIFIED-GENUINE',
            'amount' => 30000,
            'status' => 'paid',
        ]);

        $verifiedRes = $this->actingAs($this->cashier)->postJson("/api/v2/pos/orders/{$orderId}/confirm-qr", [
            'external_transaction_id' => 'TXN-VERIFIED-GENUINE',
            'amount' => 30000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'confirm-ok-'.uniqid(),
        ]);
        $verifiedRes->assertOk();
        $this->assertSame('completed', $verifiedRes->json('order.status'));
        $this->assertSame(18, $this->drinkProduct->fresh()->stock_quantity); // 20 - 2 = 18
    }

    public function test_shift_controls_orders_association_and_expected_cash_reconciliation(): void
    {
        // 1. Open Shift 1
        $shiftRes = $this->actingAs($this->cashier)->postJson('/api/v2/pos/shifts', [
            'opening_cash' => 200000,
            'venue_id' => $this->venue->id,
            'register_id' => 'POS-REG-A',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'shift-1-'.uniqid(),
        ]);
        $shiftRes->assertCreated();
        $shiftId = $shiftRes->json('shift.id');
        $shift = CashShift::findOrFail($shiftId);

        // 2. Complete a cash sale (2 drinks = 30,000 VND)
        $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'items' => [
                ['product_id' => $this->drinkProduct->id, 'quantity' => 2],
            ],
            'payment_method' => 'cash',
            'cash_tendered' => 50000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'sale-1-'.uniqid(),
        ])->assertCreated();

        // 3. Record cash movement (cash_in 50,000 for change float)
        $this->actingAs($this->cashier)->postJson("/api/v2/pos/shifts/{$shiftId}/cash-movement", [
            'type' => 'cash_in',
            'amount' => 50000,
            'reason' => 'Adding change float',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'cm-1-'.uniqid(),
        ])->assertOk();

        // 4. Record cash movement (cash_out 20,000 for emergency cleaning supplies)
        $this->actingAs($this->cashier)->postJson("/api/v2/pos/shifts/{$shiftId}/cash-movement", [
            'type' => 'cash_out',
            'amount' => 20000,
            'reason' => 'Broom and mop soap',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'cm-2-'.uniqid(),
        ])->assertOk();

        // Expected cash: 200,000 (opening) + 30,000 (cash sale) + 50,000 (cash in) - 20,000 (cash out) = 260,000 VND
        // Close shift with declared cash = 260,000 (variance = 0)
        $closeRes = $this->actingAs($this->cashier)->postJson("/api/v2/pos/shifts/{$shiftId}/close", [
            'declared_cash' => 260000,
            'note' => 'Shift balanced cleanly',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'close-1-'.uniqid(),
        ]);
        $closeRes->assertOk();
        $this->assertSame(260000, (int) $closeRes->json('shift.expected_cash'));
        $this->assertSame(0, (int) $closeRes->json('shift.variance'));
    }

    public function test_shift_close_with_pending_orders_requires_disposition_and_handles_late_payment(): void
    {
        // 1. Open shift
        $shift = app(PosService::class)->openShift($this->cashier, $this->tenant->id, 100000, $this->venue->id, 'REG-02');

        // 2. Create pending QR order (1 drink = 15,000 VND)
        $orderRes = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'items' => [
                ['product_id' => $this->drinkProduct->id, 'quantity' => 1],
            ],
            'payment_method' => 'qr',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'order-pending-'.uniqid(),
        ]);
        $orderRes->assertCreated();
        $orderId = $orderRes->json('order.id');

        // 3. Trying to close shift without disposition fails with 409
        $failClose = $this->actingAs($this->cashier)->postJson("/api/v2/pos/shifts/{$shift->id}/close", [
            'declared_cash' => 100000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'close-fail-'.uniqid(),
        ]);
        $failClose->assertStatus(409);
        $this->assertStringContainsString('without disposition', $failClose->json('message'));

        // 4. Closing with disposition = 'handover' succeeds
        $handoverClose = $this->actingAs($this->cashier)->postJson("/api/v2/pos/shifts/{$shift->id}/close", [
            'declared_cash' => 100000,
            'pending_disposition' => 'handover',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'close-handover-'.uniqid(),
        ]);
        $handoverClose->assertOk();

        $order = PosOrder::findOrFail($orderId);
        $this->assertSame('handed_over', $order->handover_status);

        // 5. Late confirmation of order after shift is closed:
        // Confirms successfully, tags late payment metadata, does NOT mutate closed shift expected cash
        Payment::create([
            'booking_id' => null,
            'provider' => 'vietqr',
            'external_transaction_id' => 'LATE-TXN-112233',
            'amount' => 15000,
            'transfer_code' => "POS {$order->payment_reference}",
            'status' => 'paid',
            'received_at' => now(),
        ]);

        $confirmRes = $this->actingAs($this->cashier)->postJson("/api/v2/pos/orders/{$orderId}/confirm-qr", [
            'external_transaction_id' => 'LATE-TXN-112233',
            'amount' => 15000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'confirm-late-'.uniqid(),
        ]);
        $confirmRes->assertOk();
        $this->assertSame('completed', $confirmRes->json('order.status'));
        $this->assertTrue((bool) $order->fresh()->metadata['late_payment_after_shift_close']);

        // Closed shift numbers remain unmodified
        $freshShift = $shift->fresh();
        $this->assertSame(100000, (int) $freshShift->expected_cash);
        $this->assertSame(0, (int) $freshShift->variance);
    }

    public function test_post_sale_order_refund_restocks_inventory_and_adjusts_cash(): void
    {
        // 1. Open shift
        $shift = app(PosService::class)->openShift($this->cashier, $this->tenant->id, 300000, $this->venue->id, 'REG-03');

        // 2. Customer buys 2 drinks for 30,000 VND cash
        $orderRes = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'items' => [
                ['product_id' => $this->drinkProduct->id, 'quantity' => 2],
            ],
            'payment_method' => 'cash',
            'cash_tendered' => 30000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'order-refund-src-'.uniqid(),
        ]);
        $orderId = $orderRes->json('order.id');
        $this->assertSame(18, $this->drinkProduct->fresh()->stock_quantity);

        // 3. Customer returns 1 drink for cash refund (15,000 VND)
        $refundRes = $this->actingAs($this->cashier)->postJson("/api/v2/pos/orders/{$orderId}/refund", [
            'items' => [
                ['product_id' => $this->drinkProduct->id, 'quantity' => 1, 'restock' => true],
            ],
            'reason' => 'Customer changed mind',
            'refund_method' => 'cash',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'refund-1-'.uniqid(),
        ]);

        $refundRes->assertOk();
        // Product inventory restocked: 18 + 1 = 19
        $this->assertSame(19, $this->drinkProduct->fresh()->stock_quantity);

        // Stock movement recorded for return
        $returnMovement = StockMovement::where('product_id', $this->drinkProduct->id)->where('type', 'return')->first();
        $this->assertNotNull($returnMovement);
        $this->assertSame(1, $returnMovement->quantity_delta);

        // Active shift recorded cash refund movement
        $movements = $shift->fresh()->cash_movements;
        $this->assertCount(1, $movements);
        $this->assertSame('refund', $movements[0]['type']);
        $this->assertSame(15000, $movements[0]['amount']);

        // Expected cash on close: 300,000 (opening) + 30,000 (sale) - 15,000 (refund) = 315,000 VND
        $closeRes = $this->actingAs($this->cashier)->postJson("/api/v2/pos/shifts/{$shift->id}/close", [
            'declared_cash' => 315000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'close-refund-'.uniqid(),
        ]);
        $closeRes->assertOk();
        $this->assertSame(315000, (int) $closeRes->json('shift.expected_cash'));
        $this->assertSame(0, (int) $closeRes->json('shift.variance'));
    }

    public function test_service_products_bypass_stock_and_expired_orders_are_released(): void
    {
        // 1. Service product has stock_quantity = 0. Selling it succeeds without stock constraint error!
        $this->actingAs($this->cashier)->postJson('/api/v2/pos/shifts', [
            'opening_cash' => 100000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'svc-shift-'.uniqid(),
        ])->assertCreated();

        $orderRes = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'items' => [
                ['product_id' => $this->serviceProduct->id, 'quantity' => 1],
            ],
            'payment_method' => 'cash',
            'cash_tendered' => 100000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'svc-order-'.uniqid(),
        ]);
        $orderRes->assertCreated();
        $this->assertSame('completed', $orderRes->json('order.status'));
        $this->assertSame(0, $this->serviceProduct->fresh()->stock_quantity);

        // 2. Pending QR order for drink product (reserves 3 items)
        $qrOrderRes = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'items' => [
                ['product_id' => $this->drinkProduct->id, 'quantity' => 3],
            ],
            'payment_method' => 'qr',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'expire-qr-'.uniqid(),
        ]);
        $qrOrderId = $qrOrderRes->json('order.id');
        $qrOrder = PosOrder::findOrFail($qrOrderId);

        // Fast forward 20 minutes (exceeding 15 min TTL)
        $meta = (array) ($qrOrder->metadata ?? []);
        $meta['expires_at'] = now()->subMinutes(5)->toIso8601String();
        $qrOrder->update([
            'created_at' => now()->subMinutes(20),
            'metadata' => $meta,
        ]);
        $releasedCount = app(PosService::class)->releaseExpiredPendingOrders($this->tenant->id);
        $this->assertSame(1, $releasedCount);
        $this->assertSame('cancelled', $qrOrder->fresh()->status);
    }

    public function test_receipt_draft_warning_and_mixed_tender_payment(): void
    {
        // 1. Open shift
        $this->actingAs($this->cashier)->postJson('/api/v2/pos/shifts', [
            'opening_cash' => 100000,
            'venue_id' => $this->venue->id,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'mixed-shift-'.uniqid(),
        ])->assertCreated();

        // 2. Mixed tender payment: 1 drink (15,000 VND) paid via 10,000 Cash + 5,000 QR
        $mixedRes = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'venue_id' => $this->venue->id,
            'items' => [
                ['product_id' => $this->drinkProduct->id, 'quantity' => 1],
            ],
            'payment_method' => 'mixed',
            'tenders' => [
                ['method' => 'cash', 'amount' => 10000, 'cash_tendered' => 10000],
                ['method' => 'qr', 'amount' => 5000],
            ],
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'mixed-order-'.uniqid(),
        ]);
        $mixedRes->assertCreated();
        $orderId = $mixedRes->json('order.id');

        // Order has 2 payments, status is pending_payment because of QR portion
        $order = PosOrder::findOrFail($orderId);
        $this->assertSame('pending_payment', $order->status);
        $this->assertCount(2, $order->payments);
        $this->assertSame(20, $this->drinkProduct->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::where('reference_type', PosOrder::class)->where('reference_id', $orderId)->count());
        $this->assertDatabaseMissing('journals', ['organization_id' => $this->tenant->id, 'event_type' => 'pos.sale', 'event_id' => "pos:{$orderId}"]);

        // 3. Receipt print on unpaid order indicates draft warning
        $printRes = $this->actingAs($this->cashier)->postJson("/api/v2/pos/orders/{$orderId}/receipt/print", [], [
            'X-Organization-Id' => $this->tenant->id,
        ]);
        $printRes->assertOk();
        $this->assertFalse($printRes->json('printable'));
        $this->assertStringContainsString('UNPAID DRAFT RECEIPT', $printRes->json('receipt.warning'));

        // 4. Receipt send endpoint
        $sendRes = $this->actingAs($this->cashier)->postJson("/api/v2/pos/orders/{$orderId}/receipt/send", [
            'email' => 'guest@example.com',
        ], [
            'X-Organization-Id' => $this->tenant->id,
        ]);
        $sendRes->assertOk();
        $this->assertTrue($sendRes->json('sent'));
        $this->assertSame('guest@example.com', $sendRes->json('recipient'));

        // 5. Cashier attempts to confirm the QR portion with an unverified self-declared reference ('MIXED-FAKE-REF') -> assertStatus(422)
        $unverifiedRes = $this->actingAs($this->cashier)->postJson("/api/v2/pos/orders/{$orderId}/confirm-qr", [
            'external_transaction_id' => 'MIXED-FAKE-REF',
            'amount' => 5000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'confirm-unverified-mixed-'.uniqid(),
        ]);
        $unverifiedRes->assertStatus(422);
        $this->assertStringContainsString('Payment has not been verified by payment provider.', $unverifiedRes->json('message'));

        // 6. Seed genuine payment
        Payment::create([
            'booking_id' => null,
            'provider' => 'vietqr',
            'external_transaction_id' => 'MIXED-TXN-5000',
            'amount' => 5000,
            'transfer_code' => "POS {$order->payment_reference}",
            'status' => 'paid',
            'received_at' => now(),
        ]);

        // Cashier confirms QR portion with verified reference -> assertOk(), order completed
        $confirmRes = $this->actingAs($this->cashier)->postJson("/api/v2/pos/orders/{$orderId}/confirm-qr", [
            'external_transaction_id' => 'MIXED-TXN-5000',
            'amount' => 5000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'confirm-genuine-mixed-'.uniqid(),
        ]);
        $confirmRes->assertOk();
        $this->assertSame('completed', $confirmRes->json('order.status'));
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(19, $this->drinkProduct->fresh()->stock_quantity);
        $this->assertSame(1, StockMovement::where('reference_type', PosOrder::class)->where('reference_id', $orderId)->count());
        $this->assertDatabaseHas('journals', ['organization_id' => $this->tenant->id, 'event_type' => 'pos.sale', 'event_id' => "pos:{$orderId}", 'total_debit' => 15000, 'total_credit' => 15000]);
        $this->assertSame(2, $order->fresh()->payments()->where('status', 'paid')->count());
        $this->assertSame(10000, (int) $order->fresh()->payments()->where('method', 'cash')->first()->amount);
        $this->assertSame(5000, (int) $order->fresh()->payments()->where('method', 'qr')->first()->amount);

        // Now print receipt again -> assertOk(), printable === true, receipt.is_paid === true, and warning is null
        $printPaidRes = $this->actingAs($this->cashier)->postJson("/api/v2/pos/orders/{$orderId}/receipt/print", [], [
            'X-Organization-Id' => $this->tenant->id,
        ]);
        $printPaidRes->assertOk();
        $this->assertTrue($printPaidRes->json('printable'));
        $this->assertTrue($printPaidRes->json('receipt.is_paid'));
        $this->assertNull($printPaidRes->json('receipt.warning'));
    }

    public function test_pending_qr_order_reserves_final_tracked_unit_and_blocks_second_order_until_expiry(): void
    {
        // Open shift for cashier
        app(PosService::class)->openShift($this->cashier, $this->tenant->id, 500000, $this->venue->id, 'REG-01');

        // 1. Create a tracked product with stock_quantity = 1
        $product = PosProduct::create([
            'organization_id' => $this->tenant->id,
            'sku' => 'LAST-RACKET-01',
            'name' => 'Vợt cầu lông Yonex cuối cùng',
            'price' => 500000,
            'cost' => 300000,
            'stock_quantity' => 1,
            'is_active' => true,
            'metadata' => [
                'track_stock' => true,
            ],
        ]);

        // 2. Cashier creates first order for 1 unit with payment_method = 'qr' -> SUCCEEDS (order status pending_payment)
        $orderRes1 = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'venue_id' => $this->venue->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'payment_method' => 'qr',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'order-1-'.uniqid(),
        ]);
        $orderRes1->assertCreated();
        $order1Id = $orderRes1->json('order.id');
        $order1 = PosOrder::findOrFail($order1Id);
        $this->assertSame('pending_payment', $order1->status);

        // 3. Cashier tries to create a second order for 1 unit (either cash or qr) -> REJECTED with 409 ('Insufficient stock ... 1 reserved by pending orders/tabs')
        $orderRes2 = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'venue_id' => $this->venue->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'payment_method' => 'cash',
            'cash_tendered' => 500000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'order-2-'.uniqid(),
        ]);
        $orderRes2->assertStatus(409);
        $this->assertStringContainsString('Insufficient stock', $orderRes2->json('message'));
        $this->assertStringContainsString('1 reserved by pending orders/tabs', $orderRes2->json('message'));

        // 4. Call app(PosService::class)->releaseExpiredPendingOrders($this->tenant->id) after setting expires_at in the past
        $order1->update([
            'created_at' => now()->subMinutes(30),
            'metadata' => array_merge($order1->metadata ?? [], [
                'expires_at' => now()->subMinutes(15)->toIso8601String(),
                'receipt' => array_merge($order1->metadata['receipt'] ?? [], [
                    'expires_at' => now()->subMinutes(15)->toIso8601String(),
                ]),
            ]),
        ]);

        $releasedCount = app(PosService::class)->releaseExpiredPendingOrders($this->tenant->id);
        $this->assertGreaterThanOrEqual(1, $releasedCount);

        // 5. Verify first order status becomes cancelled
        $this->assertSame('cancelled', $order1->fresh()->status);

        // 6. Now cashier creates the second order for 1 unit -> SUCCEEDS (stock decremented or reserved)
        $orderRes3 = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'venue_id' => $this->venue->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'payment_method' => 'cash',
            'cash_tendered' => 500000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'order-3-'.uniqid(),
        ]);
        $orderRes3->assertCreated();
        $this->assertSame('completed', $orderRes3->json('order.status'));
        $this->assertSame(0, $product->fresh()->stock_quantity);
    }
}
