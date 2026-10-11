<?php

namespace Tests\Feature;

use App\Models\CashShift;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PosProduct;
use App\Models\ShiftReconciliation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosShiftOpenCloseReviewReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $tenant;

    private User $cashier;

    private User $manager;

    private PosProduct $productA;

    private PosProduct $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Organization::create([
            'name' => 'Aura Arena East',
            'slug' => 'aura-east-'.uniqid(),
            'status' => 'active',
        ]);

        config([
            'features.flags.tenant_pos_and_wallet' => true,
            'features.pilot_organizations' => [(string) $this->tenant->id],
        ]);

        $this->cashier = User::factory()->create(['name' => 'Cashier Thao']);
        OrganizationMember::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->cashier->id,
            'role' => OrganizationMember::ROLE_CASHIER,
            'status' => 'active',
        ]);

        $this->manager = User::factory()->create(['name' => 'Manager Dung']);
        OrganizationMember::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->manager->id,
            'role' => OrganizationMember::ROLE_VENUE_MANAGER,
            'status' => 'active',
        ]);

        $this->productA = PosProduct::create([
            'organization_id' => $this->tenant->id,
            'sku' => 'WATER-AQUAFINA',
            'name' => 'Nước suối Aquafina 500ml',
            'price' => 10000,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);

        $this->productB = PosProduct::create([
            'organization_id' => $this->tenant->id,
            'sku' => 'TOWEL-COTTON',
            'name' => 'Khăn lau mồ hôi thể thao',
            'price' => 50000,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);
    }

    public function test_shift_open_close_and_reconciliation_lifecycle(): void
    {
        // 1. OPEN SHIFT
        // Negative opening cash fails
        $negOpen = $this->actingAs($this->cashier)->postJson('/api/v2/pos/shifts', [
            'opening_cash' => -5000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'neg-open-'.uniqid(),
        ]);
        $negOpen->assertStatus(422);

        // Valid shift open with 500,000 VND
        $openRes = $this->actingAs($this->cashier)->postJson('/api/v2/pos/shifts', [
            'opening_cash' => 500000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'open-shift-1',
        ]);

        $openRes->assertCreated()
            ->assertJsonPath('shift.opening_cash', 500000)
            ->assertJsonPath('shift.status', 'open');

        $shiftId = $openRes->json('shift.id');

        // Opening second shift while open fails with 409
        $dupOpen = $this->actingAs($this->cashier)->postJson('/api/v2/pos/shifts', [
            'opening_cash' => 200000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'dup-open-'.uniqid(),
        ]);
        $dupOpen->assertStatus(409);

        // 2. REAL SALES UNDER THIS SHIFT
        // Cash Sale 1: 5 bottles of water = 50,000 VND
        $sale1 = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'items' => [['product_id' => $this->productA->id, 'quantity' => 5]],
            'payment_method' => 'cash',
            'cash_tendered' => 50000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'sale-1-'.uniqid(),
        ]);
        $sale1->assertCreated();
        $order1Id = $sale1->json('order.id');

        // Cash Sale 2: 2 towels = 100,000 VND
        $sale2 = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'items' => [['product_id' => $this->productB->id, 'quantity' => 2]],
            'payment_method' => 'cash',
            'cash_tendered' => 100000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'sale-2-'.uniqid(),
        ]);
        $sale2->assertCreated();
        $order2Id = $sale2->json('order.id');

        // Verify active shift expected cash: 500,000 + 150,000 cash sales = 650,000 VND
        $activeRes = $this->actingAs($this->cashier)->getJson('/api/v2/pos/shifts/active', [
            'X-Organization-Id' => $this->tenant->id,
        ]);
        $activeRes->assertOk()
            ->assertJsonPath('shift.opening_cash', 500000)
            ->assertJsonPath('shift.cash_sales', 150000)
            ->assertJsonPath('shift.expected_cash', 650000);

        // 3. CLOSE SHIFT WITH VARIANCE
        // Cashier counts 640,000 VND (Deficit of -10,000 VND)
        $closeRes = $this->actingAs($this->cashier)->postJson("/api/v2/pos/shifts/{$shiftId}/close", [
            'declared_cash' => 640000,
            'note' => 'Thiếu 10k do hoàn nhầm tiền lẻ cho khách mua nước',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'close-shift-1',
        ]);

        $closeRes->assertOk()
            ->assertJsonPath('shift.status', 'closed')
            ->assertJsonPath('shift.expected_cash', 650000)
            ->assertJsonPath('shift.declared_cash', 640000)
            ->assertJsonPath('shift.variance', -10000);

        $shift = CashShift::findOrFail($shiftId);
        $this->assertSame('closed', $shift->status);
        $this->assertSame(650000, (int) $shift->expected_cash);
        $this->assertSame(640000, (int) $shift->declared_cash);
        $this->assertSame(-10000, (int) $shift->variance);

        // ShiftReconciliation automatically created with exception status
        $this->assertDatabaseHas('shift_reconciliations', [
            'cash_shift_id' => $shiftId,
            'variance' => -10000,
            'status' => 'exception',
        ]);

        // 4. MANAGER REVIEWS AND APPROVES RECONCILIATION
        $reviewRes = $this->actingAs($this->manager)->postJson("/api/v2/pos/shifts/{$shiftId}/review", [
            'status' => 'approved',
            'note' => 'Quản lý xác nhận chấp thuận khoản chênh lệch 10k',
        ], [
            'X-Organization-Id' => $this->tenant->id,
        ]);

        $reviewRes->assertOk()
            ->assertJsonPath('reconciliation.status', 'approved');

        $reconciliation = ShiftReconciliation::where('cash_shift_id', $shiftId)->first();
        $this->assertNotNull($reconciliation);
        $this->assertSame('approved', $reconciliation->status);
        $this->assertSame($this->manager->id, $reconciliation->reviewed_by);
        $this->assertNotNull($reconciliation->reviewed_at);

        // 5. PRESERVATION OF ORIGINAL FINANCIAL ENTRIES
        // The original sales journals for order1 (50,000) and order2 (100,000) remain completely intact
        $journal1 = Journal::where('organization_id', $this->tenant->id)
            ->where('event_type', 'pos.sale')
            ->where('event_id', "pos:{$order1Id}")
            ->first();
        $this->assertNotNull($journal1);
        $this->assertSame(50000, (int) $journal1->entries()->where('side', 'debit')->value('amount'));

        $journal2 = Journal::where('organization_id', $this->tenant->id)
            ->where('event_type', 'pos.sale')
            ->where('event_id', "pos:{$order2Id}")
            ->first();
        $this->assertNotNull($journal2);
        $this->assertSame(100000, (int) $journal2->entries()->where('side', 'debit')->value('amount'));

        // Total sales in journals remain exactly 150,000 VND
        $totalDebit = Journal::where('journals.organization_id', $this->tenant->id)
            ->where('journals.event_type', 'pos.sale')
            ->join('journal_entries', 'journals.id', '=', 'journal_entries.journal_id')
            ->where('journal_entries.side', 'debit')
            ->sum('journal_entries.amount');
        $this->assertSame(150000, (int) $totalDebit);
    }
}
