<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PosProduct;
use App\Models\User;
use App\Services\PosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosMembershipWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_offline_pos_cannot_finalize_payment_or_inventory(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
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
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(PosService::class)->spendWallet($organization->id, $customer->id, 100001, 'wallet-spend-1');
    }

    private function tenant(): array
    {
        $organization = Organization::create(['name' => 'POS Tenant', 'slug' => 'pos-'.uniqid()]);
        $cashier = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $cashier->id, 'role' => 'cashier', 'status' => 'active']);
        config(['features.pilot_organizations' => [(string) $organization->id], 'features.flags.tenant_pos_and_wallet' => true]);
        return [$organization, $cashier];
    }
}
