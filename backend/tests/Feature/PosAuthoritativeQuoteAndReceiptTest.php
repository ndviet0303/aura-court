<?php

namespace Tests\Feature;

use App\Models\CashShift;
use App\Models\Coupon;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PosOrder;
use App\Models\PosProduct;
use App\Models\TenantMembership;
use App\Models\TenantMembershipPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosAuthoritativeQuoteAndReceiptTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $cashier;
    private User $customer;
    private PosProduct $rentalProduct;
    private PosProduct $drinkProduct;
    private CashShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Aura POS Arena',
            'slug' => 'aura-pos-' . uniqid(),
            'status' => 'active',
        ]);

        config([
            'features.flags.tenant_pos_and_wallet' => true,
            'features.pilot_organizations' => [(string) $this->organization->id],
        ]);

        $this->cashier = User::factory()->create(['name' => 'Thu Ngan Chi']);
        OrganizationMember::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->cashier->id,
            'role' => OrganizationMember::ROLE_CASHIER,
        ]);

        $this->customer = User::factory()->create(['name' => 'Nguyen Van Khach']);

        // 1. Rental product: 50,000 VND / hour, 8% VAT
        $this->rentalProduct = PosProduct::create([
            'organization_id' => $this->organization->id,
            'sku' => 'RENT-RACKET-01',
            'name' => 'Thuê Vợt Pickleball Pro',
            'price' => 50000,
            'cost' => 20000,
            'stock_quantity' => 10,
            'is_active' => true,
            'metadata' => [
                'category' => 'rental',
                'is_rental' => true,
                'tax_rate' => 8,
            ],
        ]);

        // 2. Drink product: 20,000 VND, 8% VAT
        $this->drinkProduct = PosProduct::create([
            'organization_id' => $this->organization->id,
            'sku' => 'DRK-REVIVE-01',
            'name' => 'Nước uống Revive Chanh Muối',
            'price' => 20000,
            'cost' => 10000,
            'stock_quantity' => 50,
            'is_active' => true,
            'metadata' => [
                'category' => 'drinks',
                'tax_rate' => 8,
            ],
        ]);

        // Open shift for cashier
        $this->shift = CashShift::create([
            'organization_id' => $this->organization->id,
            'cashier_id' => $this->cashier->id,
            'opening_cash' => 500000,
            'opened_at' => now(),
            'status' => 'open',
        ]);
    }

    public function test_server_quote_calculates_rental_duration_configured_vat_and_discounts(): void
    {
        // Give customer a 10% membership discount
        $plan = TenantMembershipPlan::create([
            'organization_id' => $this->organization->id,
            'name' => 'Hội viên Vàng',
            'version' => 1,
            'price' => 500000,
            'benefits' => ['pos_discount_percent' => 10],
            'status' => 'published',
            'published_at' => now(),
        ]);

        TenantMembership::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->customer->id,
            'plan_id' => $plan->id,
            'plan_version' => $plan->version,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $headers = [
            'X-Organization-Id' => $this->organization->id,
        ];

        // 2 hours rental of 1 racket (50k * 1 * 2 = 100k) + 2 bottles of drink (20k * 2 = 40k)
        // Subtotal = 140,000 VND
        // 10% Membership discount = 14,000 VND
        // Taxable amount = 126,000 VND
        // Configured VAT (8%) = round(126,000 * 0.08) = 10,080 VND
        // Grand Total = 136,080 VND
        // Cash tendered = 150,000 VND => Change due = 13,920 VND

        $response = $this->actingAs($this->cashier)->postJson('/api/v2/pos/quote', [
            'items' => [
                [
                    'product_id' => $this->rentalProduct->id,
                    'quantity' => 1,
                    'rental_duration_hours' => 2,
                ],
                [
                    'product_id' => $this->drinkProduct->id,
                    'quantity' => 2,
                ],
            ],
            'customer_id' => $this->customer->id,
            'payment_method' => 'cash',
            'cash_tendered' => 150000,
        ], $headers);

        $response->assertOk()
            ->assertJsonPath('quote.discount_amount', 14000)
            ->assertJsonPath('quote.taxable_amount', 126000)
            ->assertJsonPath('quote.tax_amount', 10080)
            ->assertJsonPath('quote.grand_total', 136080)
            ->assertJsonPath('quote.cash_tendered', 150000)
            ->assertJsonPath('quote.change_due', 13920)
            ->assertJsonPath('quote.vat_breakdown.0.percent', 8)
            ->assertJsonPath('quote.vat_breakdown.0.amount', 10080);
    }

    public function test_server_quote_rejects_insufficient_cash_tendered(): void
    {
        $headers = ['X-Organization-Id' => $this->organization->id];

        $response = $this->actingAs($this->cashier)->postJson('/api/v2/pos/quote', [
            'items' => [
                [
                    'product_id' => $this->drinkProduct->id,
                    'quantity' => 1,
                ],
            ],
            'payment_method' => 'cash',
            'cash_tendered' => 10000, // Price is 20,000 + 8% VAT = 21,600
        ], $headers);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Tiền mặt khách đưa không đủ để thanh toán.']);
    }

    public function test_complete_order_persists_authoritative_receipt_and_matches_financial_totals(): void
    {
        // Assign 10% membership discount to customer
        $plan = TenantMembershipPlan::create([
            'organization_id' => $this->organization->id,
            'name' => 'Hội viên Bạc',
            'version' => 1,
            'price' => 300000,
            'benefits' => ['discount_percent' => 10],
            'status' => 'published',
            'published_at' => now(),
        ]);

        TenantMembership::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->customer->id,
            'plan_id' => $plan->id,
            'plan_version' => $plan->version,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $headers = [
            'X-Organization-Id' => $this->organization->id,
            'Idempotency-Key' => 'pos-order-' . uniqid(),
        ];

        // Racket rental: 2 hours * 50k = 100k
        // Discount 10% = 10k
        // Taxable = 90k
        // VAT 8% = 7,200
        // Grand total = 97,200 VND
        // Cash tendered = 100,000 VND => Change due = 2,800 VND

        $response = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'items' => [
                [
                    'product_id' => $this->rentalProduct->id,
                    'quantity' => 1,
                    'rental_duration_hours' => 2,
                    'vat_rate' => 0.08,
                ],
            ],
            'customer_id' => $this->customer->id,
            'payment_method' => 'cash',
            'cash_tendered' => 100000,
        ], $headers);

        $response->assertCreated()
            ->assertJsonPath('order.subtotal', 100000)
            ->assertJsonPath('order.discount_amount', 10000)
            ->assertJsonPath('order.tax_amount', 7200)
            ->assertJsonPath('order.total_amount', 97200)
            ->assertJsonPath('order.cash_tendered', 100000)
            ->assertJsonPath('order.change_due', 2800)
            ->assertJsonPath('receipt.grand_total', 97200)
            ->assertJsonPath('receipt.cashier_name', 'Thu Ngan Chi')
            ->assertJsonPath('receipt.customer_name', 'Nguyen Van Khach')
            ->assertJsonPath('receipt.items.0.rental_duration_hours', 2);

        $orderId = $response->json('order.id');
        $this->assertNotNull($orderId);

        // 1. Verify database record
        $order = PosOrder::with(['items', 'payments'])->findOrFail($orderId);
        $this->assertSame(100000, $order->subtotal);
        $this->assertSame(10000, $order->discount_amount);
        $this->assertSame(7200, $order->tax_amount);
        $this->assertSame(97200, $order->total_amount);
        $this->assertSame(100000, $order->cash_tendered);
        $this->assertSame(2800, $order->change_due);

        // 2. Verify order item rental duration and tax
        $orderItem = $order->items->first();
        $this->assertSame(2, $orderItem->rental_duration_hours);
        $this->assertSame(8, $orderItem->tax_rate);
        $this->assertSame(7200, $orderItem->tax_amount);

        // 3. Verify payment amount matches order and receipt grand total
        $payment = $order->payments->first();
        $this->assertSame(97200, $payment->amount);
        $this->assertSame($order->total_amount, $payment->amount);
        $this->assertSame($order->receipt['grand_total'], $payment->amount);

        // 4. Verify financial ledger posting total matches payment and receipt grand total
        $journal = Journal::where('organization_id', $this->organization->id)
            ->where('event_type', 'pos.sale')
            ->where('event_id', "pos:{$order->id}")
            ->firstOrFail();

        $this->assertSame(97200, $journal->total_debit);
        $this->assertSame(97200, $journal->total_credit);
        $this->assertSame($order->total_amount, $journal->total_credit);
        $this->assertSame($order->receipt['grand_total'], $journal->total_credit);

        // 5. Reload via GET /api/v2/pos/orders/{id} confirms saved receipt agrees
        $showResponse = $this->actingAs($this->cashier)->getJson("/api/v2/pos/orders/{$orderId}", [
            'X-Organization-Id' => $this->organization->id,
        ]);

        $showResponse->assertOk()
            ->assertJsonPath('order.total_amount', 97200)
            ->assertJsonPath('receipt.grand_total', 97200)
            ->assertJsonPath('receipt.items.0.rental_duration_hours', 2)
            ->assertJsonPath('receipt.vat_breakdown.0.amount', 7200);
    }
}
