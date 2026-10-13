<?php

namespace Tests\Feature;

use App\Events\CourtStatusUpdated;
use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PosOrder;
use App\Models\PosProduct;
use App\Models\PriceRule;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\ServiceTab;
use App\Models\StockMovement;
use App\Models\TenantCustomer;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OnSiteAttendanceAndTabJourneyTest extends TestCase
{
    use RefreshDatabase;

    private Organization $tenant;

    private User $cashier;

    private User $staff;

    private User $manager;

    private User $customer;

    private Venue $venue;

    private Court $court;

    private PosProduct $drink;

    private PosProduct $accessory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Organization::create([
            'name' => 'Aura Central Stadium',
            'slug' => 'aura-central-'.uniqid(),
            'status' => 'active',
            'settings' => ['timezone' => 'Asia/Ho_Chi_Minh'],
        ]);

        config([
            'features.flags.tenant_pos_and_wallet' => true,
            'features.pilot_organizations' => [(string) $this->tenant->id],
        ]);

        $this->cashier = User::factory()->create(['name' => 'Cashier Trang']);
        OrganizationMember::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->cashier->id,
            'role' => OrganizationMember::ROLE_CASHIER,
            'status' => 'active',
        ]);

        $this->staff = User::factory()->create(['name' => 'Staff Nam']);
        OrganizationMember::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->staff->id,
            'role' => OrganizationMember::ROLE_STAFF,
            'status' => 'active',
        ]);

        $this->manager = User::factory()->create(['name' => 'Manager Phuong']);
        OrganizationMember::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->manager->id,
            'role' => OrganizationMember::ROLE_VENUE_MANAGER,
            'status' => 'active',
        ]);

        $this->customer = User::factory()->create(['name' => 'Player Linh']);
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
            'name' => 'Aura Grand Stadium',
            'slug' => 'aura-grand-'.uniqid(),
            'is_active' => true,
        ]);

        $this->court = Court::create([
            'venue_id' => $this->venue->id,
            'name' => 'Center Championship Court',
            'is_active' => true,
        ]);

        // Base price rule for court bookings
        PriceRule::create([
            'organization_id' => $this->tenant->id,
            'court_id' => $this->court->id,
            'type' => 'base',
            'price' => 200000,
            'is_active' => true,
        ]);

        // POS Products
        $this->drink = PosProduct::create([
            'organization_id' => $this->tenant->id,
            'sku' => 'RED-BULL-CAN',
            'name' => 'Nước Tăng Lực Red Bull',
            'price' => 20000,
            'cost' => 12000,
            'stock_quantity' => 50,
            'is_active' => true,
            'metadata' => ['category' => 'drinks', 'unit' => 'lon'],
        ]);

        $this->accessory = PosProduct::create([
            'organization_id' => $this->tenant->id,
            'sku' => 'SHUTTLE-RSL-TUBE',
            'name' => 'Ống Cầu Lông RSL No.1',
            'price' => 80000,
            'cost' => 55000,
            'stock_quantity' => 20,
            'is_active' => true,
            'metadata' => ['category' => 'accessories', 'unit' => 'ống'],
        ]);
    }

    private function headers(string $keyPrefix = 'journey-'): array
    {
        return [
            'X-Organization-ID' => (string) $this->tenant->id,
            'Idempotency-Key' => uniqid($keyPrefix),
        ];
    }

    public function test_complete_check_in_to_service_tab_settlement_checkout_and_shift_reconciliation_journey(): void
    {
        Event::fake([CourtStatusUpdated::class]);

        // =========================================================================
        // STEP 1: Cashier opens morning shift with 500,000 VND
        // =========================================================================
        $openShiftRes = $this->actingAs($this->cashier)
            ->withHeaders($this->headers('shift-open-'))
            ->postJson('/api/pos/shifts', [
                'opening_cash' => 500000,
            ]);

        $openShiftRes->assertCreated();
        $openShiftRes->assertJsonPath('shift.status', 'open');
        $openShiftRes->assertJsonPath('shift.opening_cash', 500000);
        $shiftId = $openShiftRes->json('shift.id');

        // =========================================================================
        // STEP 2: Customer has confirmed booking for today
        // =========================================================================
        $tz = config('app.timezone');
        $todaySlot = Carbon::now($tz)->subMinutes(5)->second(0);

        $reservation = Reservation::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'code' => 'RES-JOURNEY-'.strtoupper(uniqid()),
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => 200000,
            'confirmed_at' => now(),
        ]);

        ReservationSlot::create([
            'organization_id' => $this->tenant->id,
            'reservation_id' => $reservation->id,
            'court_id' => $this->court->id,
            'starts_at' => $todaySlot,
            'ends_at' => $todaySlot->copy()->addHour(),
            'unit_price' => 200000,
            'status' => 'confirmed',
        ]);

        // =========================================================================
        // STEP 3: Staff checks in customer attendance (QR / Code scan)
        // =========================================================================
        $checkInRes = $this->actingAs($this->staff)
            ->withHeaders($this->headers('check-in-'))
            ->postJson('/api/attendance/check-in', [
                'code' => "AURA_V2_RESERVATION_{$reservation->code}",
                'notes' => 'Khách đến đúng giờ, vào sân số 1',
            ]);

        $checkInRes->assertCreated();
        $checkInRes->assertJsonPath('attendance.status', 'checked_in');
        $checkInRes->assertJsonPath('attendance.checked_in_by', $this->staff->id);

        Event::assertDispatched(CourtStatusUpdated::class, function (CourtStatusUpdated $event) use ($reservation) {
            return $event->courtId === $this->court->id
                && $event->status === 'occupied'
                && ($event->metadata['reservation_id'] ?? null) === $reservation->id;
        });

        // =========================================================================
        // STEP 4: Service Purchase: Customer orders 2 drinks & 1 shuttle tube on court tab
        // =========================================================================
        // 2 drinks @ 20,000 = 40,000 VND
        // 1 shuttle tube @ 80,000 = 80,000 VND
        // Total = 120,000 VND
        $tabRes = $this->actingAs($this->staff)
            ->withHeaders($this->headers('tab-add-'))
            ->postJson('/api/service-tabs', [
                'reservation_id' => $reservation->id,
                'items' => [
                    ['product_id' => $this->drink->id, 'quantity' => 2],
                    ['product_id' => $this->accessory->id, 'quantity' => 1],
                ],
            ]);

        $tabRes->assertCreated();
        $tabRes->assertJsonPath('tab.status', 'unpaid');
        $tabRes->assertJsonPath('tab.total_amount', 120000);
        $tabId = $tabRes->json('tab.id');

        // Verify stock reservations: on-hand unmutated, 0 sale movements, 0 POS orders
        $this->assertSame(50, $this->drink->fresh()->stock_quantity);
        $this->assertSame(20, $this->accessory->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::where('type', 'sale')->count());
        $this->assertSame(0, PosOrder::count());

        // =========================================================================
        // STEP 5: Staff checks out attendance (SUCCEEDS even with unpaid tab - Task 8.4 [D51])
        // =========================================================================
        $checkoutRes = $this->actingAs($this->staff)
            ->withHeaders($this->headers('checkout-unpaid-'))
            ->postJson('/api/attendance/check-out', [
                'code' => $reservation->code,
            ]);

        $checkoutRes->assertOk();
        $this->assertSame('checked_out', $checkoutRes->json('attendance.status'));
        $this->assertSame(120000, $checkoutRes->json('unpaid_receivable'));

        // Tab remains unpaid
        $this->assertSame('unpaid', ServiceTab::find($tabId)->status);

        // Event CourtStatusUpdated was dispatched with awaiting_inspection (Task 8.5 [D52])
        Event::assertDispatched(CourtStatusUpdated::class, function (CourtStatusUpdated $event) {
            return $event->courtId === $this->court->id && $event->status === 'awaiting_inspection';
        });

        // Staff court inspection step: Staff confirms court ready
        $confirmRes = $this->actingAs($this->staff)
            ->withHeaders($this->headers('confirm-ready-'))
            ->postJson("/api/attendance/courts/{$this->court->id}/confirm-ready");

        $confirmRes->assertOk();
        $this->assertSame('available', $confirmRes->json('status'));
        // =========================================================================
        // STEP 6: Cashier settles the tab with 150,000 VND cash (change = 30,000 VND)
        // =========================================================================
        $settleRes = $this->actingAs($this->cashier)
            ->withHeaders($this->headers('tab-settle-'))
            ->postJson("/api/service-tabs/{$tabId}/settle", [
                'payment_method' => 'cash',
                'cash_tendered' => 150000,
            ]);

        $settleRes->assertOk();
        $settleRes->assertJsonPath('tab.status', 'settled');
        $settleRes->assertJsonPath('order.total_amount', 120000);
        $settleRes->assertJsonPath('order.change_due', 30000);
        $settleRes->assertJsonPath('receipt.grand_total', 120000);

        // Stock reservations converted to actual sale movements:
        $this->assertSame(48, $this->drink->fresh()->stock_quantity);     // 50 - 2
        $this->assertSame(19, $this->accessory->fresh()->stock_quantity); // 20 - 1
        $this->assertSame(2, StockMovement::where('type', 'sale')->count());

        // Balanced financial posting for POS sale recorded
        $this->assertDatabaseHas('journals', [
            'organization_id' => $this->tenant->id,
            'event_type' => 'pos.sale',
            'total_debit' => 120000,
            'total_credit' => 120000,
        ]);

        // =========================================================================
        // STEP 7: Repeated check-out is idempotent (already checked out in STEP 5)
        // =========================================================================
        $checkoutRes = $this->actingAs($this->staff)
            ->withHeaders($this->headers('checkout-dup-'))
            ->postJson('/api/attendance/check-out', [
                'code' => $reservation->code,
            ]);

        $checkoutRes->assertOk();
        $this->assertTrue($checkoutRes->json('duplicate'));

        // =========================================================================
        // STEP 8: Active shift reflects cash sales and Cashier closes shift
        // =========================================================================
        // Expected cash = 500,000 opening + 120,000 net cash collected = 620,000 VND
        $activeShiftRes = $this->actingAs($this->cashier)
            ->withHeaders(['X-Organization-ID' => (string) $this->tenant->id])
            ->getJson('/api/pos/shifts/active');
        $activeShiftRes->assertOk();
        $activeShiftRes->assertJsonPath('shift.opening_cash', 500000);
        $activeShiftRes->assertJsonPath('shift.cash_sales', 120000);
        $activeShiftRes->assertJsonPath('shift.expected_cash', 620000);

        // Cashier counts exactly 620,000 VND and closes the shift
        $closeShiftRes = $this->actingAs($this->cashier)
            ->withHeaders($this->headers('close-shift-'))
            ->postJson("/api/pos/shifts/{$shiftId}/close", [
                'declared_cash' => 620000,
                'note' => 'Đầy đủ tiền mặt, không lệch đồng nào',
            ]);

        $closeShiftRes->assertOk();
        $closeShiftRes->assertJsonPath('shift.status', 'closed');
        $closeShiftRes->assertJsonPath('shift.declared_cash', 620000);
        // =========================================================================
        // STEP 9: Manager reviews and approves shift reconciliation
        // =========================================================================
        $reviewRes = $this->actingAs($this->manager)
            ->withHeaders($this->headers('review-shift-'))
            ->postJson("/api/pos/shifts/{$shiftId}/review", [
                'status' => 'approved',
                'note' => 'Khớp tiền 100% đối soát ca thành công',
            ]);

        $reviewRes->assertOk();
        $reviewRes->assertJsonPath('reconciliation.status', 'approved');
        $reviewRes->assertJsonPath('reconciliation.variance', 0);
    }
}
