<?php

namespace Tests\Feature;

use App\Models\CashShift;
use App\Models\FinanceException;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\PosOrder;
use App\Models\PosPayment;
use App\Models\PosProduct;
use App\Models\ReceivingConnection;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Venue;
use App\Services\POS\PosQrPaymentService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\PaysOwnerByQr;
use Tests\TestCase;

/** POS QR pays the venue owner's verified connection; only a verified owner receipt completes the order. */
class PosVerifiedPendingQrCollectionTest extends TestCase
{
    use PaysOwnerByQr;
    use RefreshDatabase;

    private Organization $tenant;

    private Venue $venue;

    private User $cashier;

    private PosProduct $product;

    private ReceivingConnection $connection;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Organization::create(['name' => 'Aura Arena POS Tenant', 'slug' => 'aura-pos-'.uniqid(), 'status' => 'active']);
        $this->venue = Venue::create([
            'organization_id' => $this->tenant->id,
            'name' => 'Arena Courts',
            'slug' => 'arena-courts-'.uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);
        config([
            'features.flags.tenant_pos_and_wallet' => true,
            'features.flags.direct_owner_payments' => true,
            'features.pilot_organizations' => [(string) $this->tenant->id],
        ]);

        $this->cashier = User::factory()->create(['name' => 'Cashier Nam']);
        OrganizationMember::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->cashier->id,
            'role' => OrganizationMember::ROLE_CASHIER,
            'status' => 'active',
        ]);
        // Opened from the POS screen, so the shift names no venue; the organization's only venue is used.
        CashShift::create([
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
        [$this->connection, $this->secret] = $this->connectOwnerReceiver($this->venue);
    }

    public function test_qr_tender_is_refused_with_setup_guidance_without_a_verified_connection(): void
    {
        $this->getJson('/api/pos/shifts/active', $this->headers())->assertJsonPath('shift.qr_available', true);

        $this->venue->forceFill(['current_receiving_connection_id' => null])->save();

        $this->getJson('/api/pos/shifts/active', $this->headers())->assertJsonPath('shift.qr_available', false);
        $this->order(2)->assertStatus(422)->assertJsonPath('message', PosQrPaymentService::UNAVAILABLE);
        $this->assertSame(0, PosOrder::count());
        $this->assertSame(0, OwnerPaymentAttempt::count());
    }

    public function test_qr_order_names_the_venue_owner_with_a_server_reference(): void
    {
        $response = $this->order(3, ['payment_reference' => 'CLIENT-CHOSEN-REF'])->assertCreated();
        $order = PosOrder::findOrFail($response->json('order.id'));

        $this->assertSame('pending_payment', $order->status);
        $this->assertSame($this->venue->id, (int) $order->venue_id);
        $this->assertMatchesRegularExpression('/^AC[A-Z0-9]{18}$/', $order->payment_reference);
        $response->assertJsonPath('payment_attempt.reference', $order->payment_reference)
            ->assertJsonPath('payment_attempt.amount', 60000)
            ->assertJsonPath('payment_attempt.status', OwnerPaymentAttempt::STATUS_PENDING)
            ->assertJsonPath('payment_attempt.receiver.account_number', '0987654321')
            ->assertJsonPath('receipt.is_paid', false);
        $this->assertStringContainsString('MB-0987654321', $response->json('payment_attempt.qr_url'));
        $this->assertNull($response->json('receipt.qr_data'));

        $attempt = OwnerPaymentAttempt::sole();
        $this->assertSame(['pos_order', (string) $order->id, $this->connection->id], [$attempt->purpose, $attempt->purpose_id, (int) $attempt->receiving_connection_id]);
        $this->assertSame($order->metadata['expires_at'], $attempt->expires_at->toIso8601String());
        $this->assertSame('pending', PosPayment::where('order_id', $order->id)->value('status'));
        $this->assertSame(50, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, Journal::where('organization_id', $this->tenant->id)->count());
    }

    public function test_a_receipt_delivered_twice_completes_the_order_once_and_records_revenue_once(): void
    {
        $order = PosOrder::findOrFail($this->order(4)->assertCreated()->json('order.id'));
        $this->postJson("/api/pos/orders/{$order->id}/confirm-qr", ['external_transaction_id' => 'CASHIER-SAYS-PAID'], $this->headers())->assertNotFound();
        $this->assertSame('pending_payment', $order->fresh()->status);

        $receipt = $this->payOwner($this->connection, $this->secret, $order->payment_reference, 80000, 'SEPAY-POS-1');
        $this->payOwner($this->connection, $this->secret, $order->payment_reference, 80000, 'SEPAY-POS-1');

        $this->assertSame(OwnerPaymentReceipt::STATUS_ALLOCATED, $receipt->status);
        $this->assertSame(1, OwnerPaymentReceipt::count());
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertTrue($order->fresh()->receipt['is_paid']);
        $this->assertSame('paid', PosPayment::where('order_id', $order->id)->value('status'));
        $this->assertSame(46, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, StockMovement::where('reference_type', PosOrder::class)->where('reference_id', $order->id)->count());
        $this->assertSame(1, Journal::where('event_type', 'owner.direct_receipt')->where('event_id', "owner-receipt:{$receipt->id}")->count());
        $this->assertSame(0, Journal::where('event_type', 'pos.sale')->count(), 'The QR portion is revenue of the owner receipt only.');
        $this->getJson("/api/pos/orders/{$order->id}", $this->headers())
            ->assertJsonPath('order.status', 'completed')
            ->assertJsonPath('payment_attempt.status', OwnerPaymentAttempt::STATUS_PAID);
    }

    public function test_an_expired_order_releases_stock_once_and_a_late_receipt_becomes_an_exception(): void
    {
        $order = PosOrder::findOrFail($this->order(50)->assertCreated()->json('order.id'));
        $this->order(1, ['payment_method' => 'cash', 'cash_tendered' => 20000])->assertStatus(409);
        $order->update(['metadata' => array_merge($order->metadata, ['expires_at' => now()->subMinute()->toIso8601String()])]);

        $this->runScheduledEvent('pos:release-expired-orders');
        $this->runScheduledEvent('pos:release-expired-orders');

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(OwnerPaymentAttempt::STATUS_EXPIRED, OwnerPaymentAttempt::sole()->status);
        $this->assertSame('cancelled', PosPayment::where('order_id', $order->id)->value('status'));
        $this->order(50, ['payment_method' => 'cash', 'cash_tendered' => 1000000])->assertCreated();
        $this->assertSame(0, $this->product->fresh()->stock_quantity);

        $late = $this->payOwner($this->connection, $this->secret, $order->payment_reference, 1000000, 'SEPAY-POS-LATE');

        $this->assertSame([OwnerPaymentReceipt::STATUS_EXCEPTION, 'attempt_not_pending'], [$late->status, $late->exception_code]);
        $this->assertTrue(FinanceException::where('reference', "owner-receipt:{$late->id}")->exists());
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, Journal::where('event_type', 'owner.direct_receipt')->count());
        $this->assertSame(0, StockMovement::where('reference_type', PosOrder::class)->where('reference_id', $order->id)->count());
    }

    public function test_cancel_qr_expires_the_attempt_and_a_receipt_for_an_unavailable_order_is_an_exception(): void
    {
        $cancelled = PosOrder::findOrFail($this->order(1)->assertCreated()->json('order.id'));
        $this->postJson("/api/pos/orders/{$cancelled->id}/cancel-qr", ['reason' => 'customer left'], $this->headers() + ['Idempotency-Key' => 'cancel-'.uniqid()])
            ->assertOk()->assertJsonPath('order.status', 'cancelled');
        $this->assertSame(OwnerPaymentAttempt::STATUS_EXPIRED, OwnerPaymentAttempt::where('purpose_id', (string) $cancelled->id)->value('status'));

        // An order cancelled while its attempt is still live (no cancel path does this today) cannot be completed by money.
        $orphan = PosOrder::findOrFail($this->order(1)->assertCreated()->json('order.id'));
        $orphan->forceFill(['status' => 'cancelled'])->save();
        $receipt = $this->payOwner($this->connection, $this->secret, $orphan->payment_reference, 20000, 'SEPAY-POS-ORPHAN');

        $this->assertSame([OwnerPaymentReceipt::STATUS_EXCEPTION, 'pos_order_unavailable'], [$receipt->status, $receipt->exception_code]);
        $this->assertTrue(FinanceException::where('type', 'owner_payment_pos_order_unavailable')->exists());
        $this->assertSame('cancelled', $orphan->fresh()->status);
        $this->assertSame(50, $this->product->fresh()->stock_quantity);
    }

    private function order(int $quantity, array $overrides = []): TestResponse
    {
        return $this->actingAs($this->cashier)->postJson('/api/pos/orders', array_merge([
            'items' => [['product_id' => $this->product->id, 'quantity' => $quantity]],
            'payment_method' => 'qr',
        ], $overrides), $this->headers() + ['Idempotency-Key' => 'pos-qr-'.uniqid()]);
    }

    private function headers(): array
    {
        $this->actingAs($this->cashier);

        return ['X-Organization-Id' => (string) $this->tenant->id];
    }

    private function runScheduledEvent(string $name): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => ($e->description ?? null) === $name);
        $this->assertNotNull($event, "Scheduled event {$name} must be registered.");
        $event->run($this->app);
    }
}
