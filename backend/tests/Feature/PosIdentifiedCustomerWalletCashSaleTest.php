<?php

namespace Tests\Feature;

use App\Models\CashShift;
use App\Models\Coupon;
use App\Models\Journal;
use App\Models\MembershipEntitlement;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PosOrder;
use App\Models\PosPayment;
use App\Models\PosProduct;
use App\Models\TenantMembership;
use App\Models\TenantMembershipPlan;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosIdentifiedCustomerWalletCashSaleTest extends TestCase
{
    use RefreshDatabase;

    private Organization $tenant;

    private User $cashier;

    private User $customer;

    private CashShift $shift;

    private PosProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Organization::create([
            'name' => 'Aura Arena Central',
            'slug' => 'aura-central-'.uniqid(),
            'status' => 'active',
        ]);

        config([
            'features.flags.tenant_pos_and_wallet' => true,
            'features.pilot_organizations' => [(string) $this->tenant->id],
        ]);

        $this->cashier = User::factory()->create(['name' => 'Cashier Huong']);
        OrganizationMember::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->cashier->id,
            'role' => OrganizationMember::ROLE_CASHIER,
            'status' => 'active',
        ]);

        $this->customer = User::factory()->create(['name' => 'Customer Hoang']);

        $this->shift = CashShift::create([
            'organization_id' => $this->tenant->id,
            'cashier_id' => $this->cashier->id,
            'opening_cash' => 1000000,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        $this->product = PosProduct::create([
            'organization_id' => $this->tenant->id,
            'sku' => 'BALL-WILSON-01',
            'name' => 'Hộp bóng Wilson US Open 4 quả',
            'price' => 150000,
            'cost' => 100000,
            'stock_quantity' => 40,
            'is_active' => true,
            'metadata' => ['tax_rate' => 8],
        ]);
    }

    public function test_identified_customer_wallet_sale_path_and_total_agreement(): void
    {
        // 1. Setup customer wallet with 500,000 VND
        $wallet = WalletAccount::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->customer->id,
            'balance' => 500000,
            'currency' => 'VND',
            'status' => 'active',
        ]);

        // Attempting wallet checkout without customer fails with 422
        $noCustRes = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
            'payment_method' => 'wallet',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'wallet-nocust-'.uniqid(),
        ]);
        $noCustRes->assertStatus(422);

        // Subtotal: 2 * 150,000 = 300,000 VND
        // VAT 8%: 24,000 VND
        // Grand Total: 324,000 VND
        $checkoutRes = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'customer_id' => $this->customer->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
            'payment_method' => 'wallet',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'wallet-sale-'.uniqid(),
        ]);

        $checkoutRes->assertCreated();

        $orderId = $checkoutRes->json('order.id');
        $order = PosOrder::findOrFail($orderId);
        $this->assertSame('completed', $order->status);
        $this->assertSame(324000, $order->total_amount);

        // 2. Customer wallet balance is atomically debited: 500,000 - 324,000 = 176,000
        $this->assertSame(176000, $wallet->fresh()->balance);

        $walletTx = WalletTransaction::where('wallet_account_id', $wallet->id)
            ->where('reference', "pos:{$orderId}")
            ->first();
        $this->assertNotNull($walletTx);
        $this->assertSame('debit', $walletTx->type);
        $this->assertSame(324000, $walletTx->amount);
        $this->assertSame(176000, $walletTx->balance_after);

        // 3. Stock is decremented: 40 - 2 = 38
        $this->assertSame(38, $this->product->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'organization_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'reference_type' => PosOrder::class,
            'reference_id' => $orderId,
            'type' => 'sale',
            'quantity_delta' => -2,
        ]);

        // 4. Payment record is paid
        $payment = PosPayment::where('order_id', $orderId)->first();
        $this->assertNotNull($payment);
        $this->assertSame('paid', $payment->status);
        $this->assertSame('wallet', $payment->method);
        $this->assertSame(324000, $payment->amount);

        // 5. Financial journal agreement
        $journal = Journal::where('organization_id', $this->tenant->id)
            ->where('event_type', 'wallet.spend')
            ->where('event_id', "wallet:{$wallet->id}:spend:pos:{$orderId}")
            ->with('entries')
            ->first();

        $this->assertNotNull($journal);
        $debitEntry = $journal->entries->firstWhere('side', 'debit');
        $creditEntry = $journal->entries->firstWhere('side', 'credit');
        $this->assertSame(324000, (int) $debitEntry->amount);
        $this->assertSame(324000, (int) $creditEntry->amount);

        // 6. Absolute Agreement: receipt.grand_total === order.total_amount === payment.amount === wallet_transaction.amount === journal.entries.amount
        $receipt = $checkoutRes->json('receipt');
        $this->assertSame(324000, $receipt['grand_total']);
        $this->assertSame($order->total_amount, $receipt['grand_total']);
        $this->assertSame($payment->amount, $receipt['grand_total']);
        $this->assertSame($walletTx->amount, $receipt['grand_total']);
        $this->assertSame((int) $debitEntry->amount, $receipt['grand_total']);
    }

    public function test_wallet_sale_aborts_on_insufficient_balance(): void
    {
        // Setup wallet with only 50,000 VND
        WalletAccount::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->customer->id,
            'balance' => 50000,
            'currency' => 'VND',
            'status' => 'active',
        ]);

        // Order total is 162,000 VND (150,000 + 8% VAT = 162,000)
        $res = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'customer_id' => $this->customer->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
            'payment_method' => 'wallet',
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'wallet-insufficient-'.uniqid(),
        ]);

        $res->assertStatus(409)
            ->assertJsonFragment(['message' => 'Insufficient tenant wallet credit.']);

        // Assert stock remains untouched
        $this->assertSame(40, $this->product->fresh()->stock_quantity);

        // Assert no order or journal created
        $this->assertDatabaseMissing('pos_orders', [
            'organization_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    public function test_cash_sale_validates_tender_and_computes_exact_change(): void
    {
        // 2 items * 150,000 = 300,000 + 24,000 VAT = 324,000 VND
        // 1. Underpaid cash fails with 422
        $underpaidRes = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
            'payment_method' => 'cash',
            'cash_tendered' => 300000, // Shortfall of 24,000
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'cash-shortfall-'.uniqid(),
        ]);

        $underpaidRes->assertStatus(422)
            ->assertJsonFragment(['message' => 'Tiền mặt khách đưa không đủ để thanh toán.']);

        // 2. Exact or surplus tender succeeds and computes exact change
        $cashRes = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
            'payment_method' => 'cash',
            'cash_tendered' => 500000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'cash-success-'.uniqid(),
        ]);

        $cashRes->assertCreated()
            ->assertJsonPath('order.cash_tendered', 500000)
            ->assertJsonPath('order.change_due', 176000)
            ->assertJsonPath('receipt.cash_tendered', 500000)
            ->assertJsonPath('receipt.change_due', 176000);

        $orderId = $cashRes->json('order.id');

        // Balanced journal posted for cash sale
        $journal = Journal::where('organization_id', $this->tenant->id)
            ->where('event_type', 'pos.sale')
            ->where('event_id', "pos:{$orderId}")
            ->with('entries')
            ->first();

        $this->assertNotNull($journal);
        $debitEntry = $journal->entries->firstWhere('side', 'debit');
        $creditEntry = $journal->entries->firstWhere('side', 'credit');
        $this->assertSame(324000, (int) $debitEntry->amount);
        $this->assertSame(324000, (int) $creditEntry->amount);
    }

    public function test_membership_applied_once_and_conflicting_coupon_rejected(): void
    {
        // 1. Create a membership plan with 10% POS discount
        $plan = TenantMembershipPlan::create([
            'organization_id' => $this->tenant->id,
            'name' => 'VIP Member Plan',
            'version' => 1,
            'price' => 1000000,
            'benefits' => [
                'pos_discount_percent' => 10,
            ],
            'status' => 'published',
            'published_at' => now(),
        ]);

        $membership = TenantMembership::create([
            'organization_id' => $this->tenant->id,
            'user_id' => $this->customer->id,
            'plan_id' => $plan->id,
            'plan_version' => 1,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
            'status' => 'active',
        ]);
        MembershipEntitlement::create([
            'organization_id' => $this->tenant->id,
            'membership_id' => $membership->id,
            'benefit_code' => 'pos_discount_percent',
            'benefit_value' => ['value' => 10, 'plan_version' => 1],
            'starts_at' => $membership->starts_at,
            'ends_at' => $membership->ends_at,
        ]);

        // 2. Quote verification: 2 items * 150,000 = 300,000 VND
        // 10% membership discount applied once = 30,000 VND
        // Taxable: 270,000 VND
        // 8% VAT: 21,600 VND
        // Grand total: 291,600 VND
        $quoteRes = $this->actingAs($this->cashier)->postJson('/api/v2/pos/quote', [
            'customer_id' => $this->customer->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
        ], [
            'X-Organization-Id' => $this->tenant->id,
        ]);

        $quoteRes->assertOk()
            ->assertJsonPath('quote.subtotal', 300000)
            ->assertJsonPath('quote.discount_amount', 30000)
            ->assertJsonPath('quote.grand_total', 291600);

        // 3. Attempting synthetic extra discount beyond 30,000 rejects with 422
        $syntheticRes = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'customer_id' => $this->customer->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
            'discount_amount' => 80000, // Synthetic discount exceeds calculated 30,000!
            'payment_method' => 'cash',
            'cash_tendered' => 300000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'synthetic-disc-'.uniqid(),
        ]);

        $syntheticRes->assertStatus(422)
            ->assertJsonValidationErrors('discount_amount');

        // 4. Create active coupon code
        $coupon = Coupon::create([
            'organization_id' => $this->tenant->id,
            'code' => 'COUPON50K',
            'discount_type' => 'fixed',
            'discount_value' => 50000,
            'min_spend' => 100000,
            'is_active' => true,
            'can_combine_with_membership' => false,
        ]);

        // Attempting to combine coupon with active membership discount fails with 422
        $conflictRes = $this->actingAs($this->cashier)->postJson('/api/v2/pos/orders', [
            'customer_id' => $this->customer->id,
            'coupon_code' => 'COUPON50K',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
            'payment_method' => 'cash',
            'cash_tendered' => 300000,
        ], [
            'X-Organization-Id' => $this->tenant->id,
            'Idempotency-Key' => 'conflict-coupon-'.uniqid(),
        ]);

        $conflictRes->assertStatus(422)
            ->assertJsonValidationErrors('coupon_code');
        $this->assertSame(0, (int) $coupon->fresh()->usage_count);
    }
}
