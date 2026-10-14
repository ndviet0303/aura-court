<?php

namespace Tests\Feature;

use App\Models\CashShift;
use App\Models\Court;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PosOrder;
use App\Models\PosProduct;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\ServiceTab;
use App\Models\StockMovement;
use App\Models\TenantCustomer;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\WalletPaymentService;
use App\Services\POS\ServiceTabService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTabAndStockReservationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $tenant;

    private User $cashier;

    private User $staff;

    private User $customer;

    private Venue $venue;

    private Court $court;

    private PosProduct $waterProduct;

    private PosProduct $gripProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Organization::create([
            'name' => 'Aura Court Arena',
            'slug' => 'aura-arena-'.uniqid(),
            'status' => 'active',
            'settings' => ['timezone' => 'Asia/Ho_Chi_Minh'],
        ]);

        config([
            'features.flags.tenant_pos_and_wallet' => true,
            'features.pilot_organizations' => [(string) $this->tenant->id],
        ]);

        $this->staff = User::factory()->create(['name' => 'Staff Alice']);
        OrganizationMember::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->staff->id,
            'role' => OrganizationMember::ROLE_STAFF,
            'status' => 'active',
        ]);

        $this->cashier = User::factory()->create(['name' => 'Cashier Bob']);
        OrganizationMember::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->cashier->id,
            'role' => OrganizationMember::ROLE_CASHIER,
            'status' => 'active',
        ]);

        $this->customer = User::factory()->create(['name' => 'Customer Charlie']);
        OrganizationMember::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->customer->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);
        TenantCustomer::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->customer->id,
            'name' => $this->customer->name,
            'status' => 'active',
        ]);

        $this->venue = Venue::create([
            'organization_id' => $this->tenant->id,
            'name' => 'Aura Arena District 7',
            'slug' => 'aura-d7-'.uniqid(),
            'is_active' => true,
        ]);

        $this->court = Court::create([
            'venue_id' => $this->venue->id,
            'name' => 'Court Alpha',
            'is_active' => true,
        ]);

        $this->waterProduct = PosProduct::create([
            'organization_id' => $this->tenant->id,
            'sku' => 'AQUAFINA-500',
            'name' => 'Nước Suối Aquafina 500ml',
            'price' => 15000,
            'cost' => 8000,
            'stock_quantity' => 20,
            'is_active' => true,
            'metadata' => ['category' => 'drinks', 'unit' => 'chai'],
        ]);

        $this->gripProduct = PosProduct::create([
            'organization_id' => $this->tenant->id,
            'sku' => 'YONEX-GRIP',
            'name' => 'Quấn Cán Vợt Yonex AC102EX',
            'price' => 45000,
            'cost' => 25000,
            'stock_quantity' => 10,
            'is_active' => true,
            'metadata' => ['category' => 'accessories', 'unit' => 'cái'],
        ]);
    }

    private function headers(): array
    {
        return [
            'X-Organization-ID' => (string) $this->tenant->id,
            'Idempotency-Key' => uniqid('tab-idem-'),
        ];
    }

    private function createReservation(): Reservation
    {
        $tz = config('app.timezone');
        $slotTime = Carbon::now($tz)->startOfHour();

        $reservation = Reservation::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'code' => 'RES-'.strtoupper(uniqid()),
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => 180000,
            'confirmed_at' => now(),
        ]);

        ReservationSlot::create([
            'organization_id' => $this->tenant->id,
            'reservation_id' => $reservation->id,
            'court_id' => $this->court->id,
            'starts_at' => $slotTime,
            'ends_at' => $slotTime->copy()->addHour(),
            'unit_price' => 180000,
            'status' => 'confirmed',
        ]);

        return $reservation;
    }

    private function openShift(): CashShift
    {
        return CashShift::create([
            'organization_id' => $this->tenant->id,
            'cashier_id' => $this->cashier->id,
            'shift_code' => 'SHIFT-'.strtoupper(uniqid()),
            'opened_at' => now(),
            'opening_cash' => 500000,
            'expected_cash' => 500000,
            'status' => 'open',
        ]);
    }

    public function test_unpaid_service_tab_reserves_stock_without_cash_sale_or_stock_movement(): void
    {
        $reservation = $this->createReservation();
        $tabService = app(ServiceTabService::class);

        // Initially: 20 water bottles, 0 reserved
        $this->assertSame(20, $tabService->getAvailableStock($this->waterProduct));
        $this->assertSame(0, $tabService->getReservedQuantity($this->waterProduct->id));

        // Staff attaches 3 water bottles to the reservation tab
        $res = $this->actingAs($this->staff)
            ->withHeaders($this->headers())
            ->postJson('/api/service-tabs', [
                'reservation_id' => $reservation->id,
                'items' => [
                    ['product_id' => $this->waterProduct->id, 'quantity' => 3],
                ],
            ]);

        $res->assertCreated();
        $res->assertJsonPath('tab.status', 'unpaid');
        $res->assertJsonPath('tab.total_amount', 45000); // 3 * 15,000

        // Stock reservation verified: 3 reserved, available becomes 17
        $this->assertSame(3, $tabService->getReservedQuantity($this->waterProduct->id));
        $this->assertSame(17, $tabService->getAvailableStock($this->waterProduct->fresh()));
        // Actual stock_quantity in DB has NOT been decremented yet (on-hand is 20)
        $this->assertSame(20, $this->waterProduct->fresh()->stock_quantity);

        // Crucial requirement: No cash sale recorded, no StockMovement of type 'sale'
        $this->assertSame(0, StockMovement::where('type', 'sale')->count());
        $this->assertSame(0, PosOrder::count());
        $this->assertSame(0, Journal::where('event_type', 'pos.sale')->count());
    }

    public function test_removing_unpaid_item_releases_stock_reservation(): void
    {
        $reservation = $this->createReservation();
        $tabService = app(ServiceTabService::class);

        // Add 2 grips to tab
        $this->actingAs($this->staff)
            ->withHeaders($this->headers())
            ->postJson('/api/service-tabs', [
                'reservation_id' => $reservation->id,
                'items' => [
                    ['product_id' => $this->gripProduct->id, 'quantity' => 2],
                ],
            ])
            ->assertCreated();

        $this->assertSame(2, $tabService->getReservedQuantity($this->gripProduct->id));
        $this->assertSame(8, $tabService->getAvailableStock($this->gripProduct->fresh()));

        $tab = ServiceTab::where('reservation_id', $reservation->id)->first();
        $item = $tab->items()->where('product_id', $this->gripProduct->id)->first();

        // Staff removes item
        $deleteRes = $this->actingAs($this->staff)
            ->withHeaders($this->headers())
            ->deleteJson("/api/service-tabs/{$reservation->id}/items/{$item->id}");

        $deleteRes->assertOk();
        $deleteRes->assertJsonPath('tab.total_amount', 0);

        // Reservation released: available stock is back to 10
        $this->assertSame(0, $tabService->getReservedQuantity($this->gripProduct->id));
        $this->assertSame(10, $tabService->getAvailableStock($this->gripProduct->fresh()));
        $this->assertSame(0, StockMovement::where('type', 'sale')->count());
    }

    public function test_concurrent_pos_purchase_cannot_oversell_reserved_tab_stock(): void
    {
        $reservation = $this->createReservation();
        $this->openShift();

        // Grip has 10 in stock. Reserve 9 on the tab.
        $this->actingAs($this->staff)
            ->withHeaders($this->headers())
            ->postJson('/api/service-tabs', [
                'reservation_id' => $reservation->id,
                'items' => [
                    ['product_id' => $this->gripProduct->id, 'quantity' => 9],
                ],
            ])
            ->assertCreated();

        // Available stock is 10 - 9 = 1
        // Cashier tries to complete a POS sale for 2 grips (more than available)
        $conflictSale = $this->actingAs($this->cashier)
            ->withHeaders($this->headers())
            ->postJson('/api/pos/orders', [
                'items' => [
                    ['product_id' => $this->gripProduct->id, 'quantity' => 2],
                ],
                'payment_method' => 'cash',
                'cash_tendered' => 100000,
            ]);

        $conflictSale->assertStatus(409);
        $this->assertStringContainsString('Insufficient stock', $conflictSale->json('message'));

        // Selling 1 grip (the remaining available stock) succeeds
        $okSale = $this->actingAs($this->cashier)
            ->withHeaders($this->headers())
            ->postJson('/api/pos/orders', [
                'items' => [
                    ['product_id' => $this->gripProduct->id, 'quantity' => 1],
                ],
                'payment_method' => 'cash',
                'cash_tendered' => 50000,
            ]);

        $okSale->assertCreated();
    }

    public function test_tab_settlement_converts_stock_reservation_to_sale_and_records_payment_once(): void
    {
        $reservation = $this->createReservation();
        $shift = $this->openShift();

        // Add 3 waters and 1 grip to service tab
        // Total = (3 * 15,000) + (1 * 45,000) = 90,000 VND
        $this->actingAs($this->staff)
            ->withHeaders($this->headers())
            ->postJson('/api/service-tabs', [
                'reservation_id' => $reservation->id,
                'items' => [
                    ['product_id' => $this->waterProduct->id, 'quantity' => 3],
                    ['product_id' => $this->gripProduct->id, 'quantity' => 1],
                ],
            ])
            ->assertCreated();

        $tab = ServiceTab::where('reservation_id', $reservation->id)->first();
        $this->assertSame(90000, (int) $tab->total_amount);

        // Cashier settles tab with cash 100,000 VND
        $settleHeaders = $this->headers();
        $res = $this->actingAs($this->cashier)
            ->withHeaders($settleHeaders)
            ->postJson("/api/service-tabs/{$tab->id}/settle", [
                'payment_method' => 'cash',
                'cash_tendered' => 100000,
            ]);

        $res->assertOk();
        $res->assertJsonPath('tab.status', 'settled');
        $res->assertJsonPath('order.status', 'completed');
        $res->assertJsonPath('order.total_amount', 90000);
        $res->assertJsonPath('order.change_due', 10000);
        $res->assertJsonPath('receipt.grand_total', 90000);

        // DB State verification:
        // 1. Tab marked settled with settled_at and settled_by
        $this->assertDatabaseHas('service_tabs', [
            'id' => $tab->id,
            'status' => 'settled',
            'settled_by' => $this->cashier->id,
        ]);

        // 2. Product stock quantities decremented
        $this->assertSame(17, $this->waterProduct->fresh()->stock_quantity); // 20 - 3
        $this->assertSame(9, $this->gripProduct->fresh()->stock_quantity);   // 10 - 1

        // 3. Exactly 2 sale StockMovements created (1 per product line)
        $this->assertSame(2, StockMovement::where('reference_type', 'service_tab')->where('reference_id', $tab->id)->count());

        // 4. Balanced financial posting recorded
        $this->assertDatabaseHas('journals', [
            'organization_id' => $this->tenant->id,
            'event_type' => 'pos.sale',
            'total_debit' => 90000,
            'total_credit' => 90000,
        ]);

        // 5. Retrying settlement with same idempotency key or repeat is idempotent: NO duplicate stock decrement
        $retry = $this->actingAs($this->cashier)
            ->withHeaders($settleHeaders)
            ->postJson("/api/service-tabs/{$tab->id}/settle", [
                'payment_method' => 'cash',
                'cash_tendered' => 100000,
            ]);

        $retry->assertOk();
        $this->assertSame(17, $this->waterProduct->fresh()->stock_quantity);
        $this->assertSame(9, $this->gripProduct->fresh()->stock_quantity);
        $this->assertSame(2, StockMovement::where('reference_type', 'service_tab')->where('reference_id', $tab->id)->count());
    }

    public function test_tab_settlement_via_wallet(): void
    {
        $reservation = $this->createReservation();
        $this->openShift();

        // Fund customer wallet with 200,000 VND
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($this->customer->id, $this->tenant->id);
        $walletService->credit($wallet, 200000, 'test_fund', 1, 'Top up');

        // Add 1 grip (45,000 VND) to tab
        $this->actingAs($this->staff)
            ->withHeaders($this->headers())
            ->postJson('/api/service-tabs', [
                'reservation_id' => $reservation->id,
                'items' => [
                    ['product_id' => $this->gripProduct->id, 'quantity' => 1],
                ],
            ])
            ->assertCreated();

        $tab = ServiceTab::where('reservation_id', $reservation->id)->first();

        // Settle tab via wallet
        $res = $this->actingAs($this->cashier)
            ->withHeaders($this->headers())
            ->postJson("/api/service-tabs/{$tab->id}/settle", [
                'payment_method' => 'wallet',
                'customer_id' => $this->customer->id,
            ]);

        $res->assertOk();
        $res->assertJsonPath('tab.status', 'settled');

        // Customer wallet balance debited: 200,000 - 45,000 = 155,000
        $this->assertSame(155000, (int) $wallet->fresh()->balance);

        // Stock decremented
        $this->assertSame(9, $this->gripProduct->fresh()->stock_quantity);
    }

    public function test_cannot_settle_tab_without_active_cashier_shift(): void
    {
        $reservation = $this->createReservation();
        // NO shift opened

        $this->actingAs($this->staff)
            ->withHeaders($this->headers())
            ->postJson('/api/service-tabs', [
                'reservation_id' => $reservation->id,
                'items' => [
                    ['product_id' => $this->waterProduct->id, 'quantity' => 1],
                ],
            ])
            ->assertCreated();

        $tab = ServiceTab::where('reservation_id', $reservation->id)->first();

        $res = $this->actingAs($this->cashier)
            ->withHeaders($this->headers())
            ->postJson("/api/service-tabs/{$tab->id}/settle", [
                'payment_method' => 'cash',
                'cash_tendered' => 20000,
            ]);

        $res->assertStatus(409);
        $this->assertStringContainsString('active open shift', $res->json('message'));
    }

    public function test_cancelled_tab_releases_reserved_stock(): void
    {
        $reservation = $this->createReservation();
        $tabService = app(ServiceTabService::class);

        // Reserve 5 waters
        $this->actingAs($this->staff)
            ->withHeaders($this->headers())
            ->postJson('/api/service-tabs', [
                'reservation_id' => $reservation->id,
                'items' => [
                    ['product_id' => $this->waterProduct->id, 'quantity' => 5],
                ],
            ])
            ->assertCreated();

        $this->assertSame(5, $tabService->getReservedQuantity($this->waterProduct->id));
        $tab = ServiceTab::where('reservation_id', $reservation->id)->first();

        // Staff cancels tab
        $res = $this->actingAs($this->staff)
            ->withHeaders($this->headers())
            ->postJson("/api/service-tabs/{$tab->id}/cancel", [
                'reason' => 'Khách hủy không sử dụng đồ uống',
            ]);

        $res->assertOk();
        $res->assertJsonPath('tab.status', 'cancelled');

        // Reserved quantity returns to 0, available back to 20
        $this->assertSame(0, $tabService->getReservedQuantity($this->waterProduct->id));
        $this->assertSame(20, $tabService->getAvailableStock($this->waterProduct->fresh()));

        // Settle on cancelled tab is rejected
        $this->openShift();
        $settleRes = $this->actingAs($this->cashier)
            ->withHeaders($this->headers())
            ->postJson("/api/service-tabs/{$tab->id}/settle", [
                'payment_method' => 'cash',
                'cash_tendered' => 100000,
            ]);
        $settleRes->assertStatus(422);
    }

    public function test_direct_cohort_settles_tabs_in_cash_only_without_mutation(): void
    {
        config(['features.flags.direct_owner_payments' => true]);
        $reservation = $this->createReservation();
        $this->openShift();
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($this->customer->id, $this->tenant->id);
        $walletService->credit($wallet, 200000, 'test_fund', 1, 'Top up');
        $this->actingAs($this->staff)
            ->withHeaders($this->headers())
            ->postJson('/api/service-tabs', [
                'reservation_id' => $reservation->id,
                'items' => [['product_id' => $this->gripProduct->id, 'quantity' => 1]],
            ])
            ->assertCreated();
        $tab = ServiceTab::where('reservation_id', $reservation->id)->firstOrFail();

        $this->actingAs($this->cashier)
            ->withHeaders($this->headers())
            ->postJson("/api/service-tabs/{$tab->id}/settle", ['payment_method' => 'wallet', 'customer_id' => $this->customer->id])
            ->assertConflict()
            ->assertJsonPath('code', 'DIRECT_OWNER_PAYMENTS_ENABLED');
        $this->actingAs($this->cashier)
            ->withHeaders($this->headers())
            ->postJson("/api/service-tabs/{$tab->id}/settle", ['payment_method' => 'qr'])
            ->assertConflict()
            ->assertJsonPath('message', 'Tab dịch vụ ở cơ sở thanh toán trực tiếp chỉ nhận tiền mặt. Muốn thu bằng QR, hãy tạo đơn POS.');

        $this->assertSame(ServiceTab::STATUS_UNPAID, $tab->fresh()->status);
        $this->assertSame(200000, (int) $wallet->fresh()->balance);
        $this->assertSame(0, PosOrder::count());

        $this->actingAs($this->cashier)
            ->withHeaders($this->headers())
            ->postJson("/api/service-tabs/{$tab->id}/settle", ['payment_method' => 'cash'])
            ->assertOk();
        $this->assertSame(ServiceTab::STATUS_SETTLED, $tab->fresh()->status);
    }
}
