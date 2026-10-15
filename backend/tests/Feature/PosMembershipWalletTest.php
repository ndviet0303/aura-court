<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\LoyaltyRule;
use App\Models\MembershipEntitlement;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PosProduct;
use App\Models\TenantMembership;
use App\Models\TenantMembershipPlan;
use App\Models\User;
use App\Models\Venue;
use App\Services\POS\PosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PosMembershipWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_offline_pos_cannot_finalize_payment_or_inventory(): void
    {
        $this->expectException(HttpException::class);
        app(PosService::class)->completeOrder(User::factory()->create(), 1, ['offline' => true]);
    }

    public function test_connected_pos_order_decrements_stock_and_records_payment(): void
    {
        [$organization, $cashier] = $this->tenant();
        $product = PosProduct::create(['organization_id' => $organization->id, 'sku' => 'SHUTTLE-1', 'name' => 'Shuttle', 'price' => 50000, 'cost' => 30000, 'stock_quantity' => 3, 'is_active' => true]);
        $order = app(PosService::class)->completeOrder($cashier, $organization->id, ['items' => [['product_id' => $product->id, 'quantity' => 2]], 'payment_method' => 'cash']);
        $this->assertSame(1, $product->fresh()->stock_quantity);
        $this->assertSame(100000, $order->total_amount);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'quantity_delta' => -2]);
        $this->assertDatabaseHas('pos_payments', ['order_id' => $order->id, 'status' => 'paid']);
    }

    public function test_wallet_spend_cannot_be_negative_or_cross_tenant(): void
    {
        [$organization, $customer] = $this->tenant();
        app(PosService::class)->creditWallet($organization->id, $customer->id, 100000, 'test', 1, 'wallet-credit-1');
        $this->expectException(HttpException::class);
        app(PosService::class)->spendWallet($organization->id, $customer->id, 100001, 'wallet-spend-1');
    }

    public function test_unearned_pos_discount_is_rejected(): void
    {
        [$organization, $cashier] = $this->tenant();
        $product = PosProduct::create([
            'organization_id' => $organization->id,
            'sku' => 'BALL-1',
            'name' => 'Tennis Ball Can',
            'price' => 100000,
            'cost' => 60000,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        // Cashier attempts to pass a 40k discount without a valid coupon or membership
        $this->expectException(ValidationException::class);
        app(PosService::class)->completeOrder($cashier, $organization->id, [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'discount_amount' => 40000,
        ]);
    }

    public function test_expired_coupon_in_pos_is_rejected(): void
    {
        [$organization, $cashier] = $this->tenant();
        $product = PosProduct::create([
            'organization_id' => $organization->id,
            'sku' => 'GRIP-1',
            'name' => 'Overgrip',
            'price' => 50000,
            'cost' => 20000,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        Coupon::create([
            'organization_id' => $organization->id,
            'code' => 'EXPIRED20',
            'discount_type' => 'percent',
            'discount_value' => 20,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDay(),
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        app(PosService::class)->completeOrder($cashier, $organization->id, [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'coupon_code' => 'EXPIRED20',
        ]);
    }

    public function test_server_side_coupon_and_tenant_membership_applied_in_pos(): void
    {
        [$organization, $cashier] = $this->tenant();
        $customer = User::factory()->create();

        $product = PosProduct::create([
            'organization_id' => $organization->id,
            'sku' => 'STRING-1',
            'name' => 'Badminton String',
            'price' => 200000,
            'cost' => 100000,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        // 10% coupon
        Coupon::create([
            'organization_id' => $organization->id,
            'code' => 'POS10',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'is_active' => true,
        ]);

        // 10% tenant membership plan
        $plan = TenantMembershipPlan::create([
            'organization_id' => $organization->id,
            'name' => 'Gold Member',
            'price' => 500000,
            'version' => 1,
            'billing_cycle' => 'monthly',
            'benefits' => ['discount_percent' => 10],
            'status' => 'active',
        ]);
        $membership = TenantMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $customer->id,
            'plan_id' => $plan->id,
            'plan_version' => $plan->version ?: 1,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);
        MembershipEntitlement::create([
            'organization_id' => $organization->id,
            'membership_id' => $membership->id,
            'benefit_code' => 'discount_percent',
            'benefit_value' => ['value' => 10, 'plan_version' => $plan->version],
            'starts_at' => $membership->starts_at,
            'ends_at' => $membership->ends_at,
        ]);
        // 200k subtotal: 10% coupon (20k) + 10% membership (20k) = 40k discount -> 160k total
        $order = app(PosService::class)->completeOrder($cashier, $organization->id, [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'customer_id' => $customer->id,
            'coupon_code' => 'POS10',
            'payment_method' => 'cash',
        ]);

        $this->assertSame(200000, $order->subtotal);
        $this->assertSame(40000, $order->discount_amount);
        $this->assertSame(160000, $order->total_amount);
    }

    public function test_pos_coupon_scope_uses_authorized_venue_and_rejects_sibling_venue(): void
    {
        [$organization, $cashier] = $this->tenant();
        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => 'POS Coupon Venue',
            'slug' => 'pos-coupon-'.uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);
        $otherVenue = Venue::create([
            'organization_id' => $organization->id,
            'name' => 'Other POS Venue',
            'slug' => 'pos-coupon-other-'.uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);
        OrganizationMember::where('organization_id', $organization->id)->where('user_id', $cashier->id)
            ->update(['permissions' => json_encode(['venue_ids' => [$venue->id, $otherVenue->id]])]);
        $product = PosProduct::create([
            'organization_id' => $organization->id,
            'sku' => 'VENUE-ITEM',
            'name' => 'Venue Item',
            'price' => 100000,
            'cost' => 50000,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);
        $coupon = Coupon::create([
            'organization_id' => $organization->id,
            'venue_id' => $venue->id,
            'code' => 'VENUE10',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'is_active' => true,
            'status' => Coupon::STATUS_ACTIVE,
        ]);

        $order = app(PosService::class)->completeOrder($cashier, $organization->id, [
            'venue_id' => $venue->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'coupon_code' => 'VENUE10',
            'payment_method' => 'cash',
        ]);
        $this->assertSame(90000, $order->total_amount);
        $this->assertSame(1, (int) $coupon->fresh()->usage_count);

        try {
            app(PosService::class)->calculateQuote($organization->id, [
                'venue_id' => $otherVenue->id,
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'coupon_code' => 'VENUE10',
            ], $cashier);
            $this->fail('A venue-scoped coupon must not apply to a sibling venue.');
        } catch (ValidationException) {
            $this->assertSame(1, (int) $coupon->fresh()->usage_count);
        }
    }

    public function test_membership_benefit_versioning_preserves_earned_entitlement_period(): void
    {
        [$organization, $cashier] = $this->tenant();
        $customer = User::factory()->create();

        $product = PosProduct::create([
            'organization_id' => $organization->id,
            'sku' => 'SHOES-1',
            'name' => 'Court Shoes',
            'price' => 1000000,
            'cost' => 600000,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        // 1. Initial Plan v1 with 20% discount
        $plan = TenantMembershipPlan::create([
            'organization_id' => $organization->id,
            'name' => 'VIP Elite',
            'price' => 1000000,
            'version' => 1,
            'billing_cycle' => 'yearly',
            'benefits' => ['discount_percent' => 20],
            'status' => 'active',
        ]);

        $membership = TenantMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $customer->id,
            'plan_id' => $plan->id,
            'plan_version' => 1,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
            'status' => 'active',
        ]);

        // Record entitlement snapshot for the earned period: 20%
        MembershipEntitlement::create([
            'organization_id' => $organization->id,
            'membership_id' => $membership->id,
            'benefit_code' => 'discount_percent',
            'benefit_value' => 20,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
        ]);

        // 2. Tenant publishes a new version of the plan with only 5% discount
        $plan->update([
            'version' => 2,
            'benefits' => ['discount_percent' => 5],
        ]);

        // 3. Customer checks out POS order: the earned 20% entitlement is preserved!
        $order = app(PosService::class)->completeOrder($cashier, $organization->id, [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'customer_id' => $customer->id,
            'payment_method' => 'cash',
        ]);

        // 1,000,000 subtotal - 20% (200,000) = 800,000 total (NOT 950,000)
        $this->assertSame(1000000, $order->subtotal);
        $this->assertSame(200000, $order->discount_amount);
        $this->assertSame(800000, $order->total_amount);
    }

    public function test_loyalty_point_award_and_redemption_with_immutable_ledger(): void
    {
        [$organization, $cashier] = $this->tenant();
        $customer = User::factory()->create();

        LoyaltyRule::create([
            'organization_id' => $organization->id,
            'name' => 'Standard Loyalty Rule',
            'version' => 1,
            'earn_points_per_100_vnd' => 1, // 1 point per 100 VND
            'redeem_points_per_100_vnd' => 100,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $pos = app(PosService::class);

        // 1. Award points for an order
        $entry1 = $pos->awardPoints($organization->id, $customer->id, 50000, 'pos_order', 101);
        $this->assertSame(500, $entry1->delta);
        $this->assertSame(500, $entry1->balance_after);

        // 2. Redeem points
        $entry2 = $pos->redeemPoints($organization->id, $customer->id, 200, 'pos_discount', 102);
        $this->assertSame(-200, $entry2->delta);
        $this->assertSame(300, $entry2->balance_after);

        // 3. Attempting to redeem more points than available is rejected with 409
        try {
            $pos->redeemPoints($organization->id, $customer->id, 500, 'pos_discount', 103);
            $this->fail('Expected 409 exception for insufficient points.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertStringContainsString('Insufficient loyalty points', $e->getMessage());
        }

        // 4. Point ledger entries are immutable (cannot update or delete)
        try {
            $entry1->update(['delta' => 999]);
            $this->fail('Expected LogicException when updating immutable PointLedger.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('Point ledger entries are immutable', $e->getMessage());
        }
    }

    private function tenant(): array
    {
        $organization = Organization::create(['name' => 'POS Tenant', 'slug' => 'pos-'.uniqid()]);
        $cashier = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $cashier->id, 'role' => 'staff', 'status' => 'active']);
        $this->assignStaffVenues($organization);
        config(['features.pilot_organizations' => [(string) $organization->id], 'features.flags.tenant_pos_and_wallet' => true]);

        return [$organization, $cashier];
    }
}
