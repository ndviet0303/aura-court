<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PosProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosProductStockMovementCrudTest extends TestCase
{
    use RefreshDatabase;

    private Organization $tenantA;

    private Organization $tenantB;

    private User $managerA;

    private User $cashierA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Organization::create([
            'name' => 'Aura Arena Tenant A',
            'slug' => 'aura-a-'.uniqid(),
            'status' => 'active',
        ]);

        $this->tenantB = Organization::create([
            'name' => 'Aura Arena Tenant B',
            'slug' => 'aura-b-'.uniqid(),
            'status' => 'active',
        ]);

        config([
            'features.flags.tenant_pos_and_wallet' => true,
            'features.pilot_organizations' => [
                (string) $this->tenantA->id,
                (string) $this->tenantB->id,
            ],
        ]);

        $this->managerA = User::factory()->create(['name' => 'Manager A']);
        OrganizationMember::create([
            'organization_id' => $this->tenantA->id,
            'user_id' => $this->managerA->id,
            'role' => OrganizationMember::ROLE_VENUE_MANAGER,
            'status' => 'active',
        ]);

        $this->cashierA = User::factory()->create(['name' => 'Cashier A']);
        OrganizationMember::create([
            'organization_id' => $this->tenantA->id,
            'user_id' => $this->cashierA->id,
            'role' => OrganizationMember::ROLE_CASHIER,
            'status' => 'active',
        ]);
    }

    public function test_product_crud_with_sku_barcode_and_initial_stock_movement(): void
    {
        $headers = [
            'X-Organization-Id' => $this->tenantA->id,
            'Idempotency-Key' => 'prod-create-'.uniqid(),
        ];

        // 1. Create Product
        $createRes = $this->actingAs($this->managerA)->postJson('/api/pos/products', [
            'sku' => 'GRIP-YONEX-01',
            'barcode' => '8938500123456',
            'name' => 'Quấn cán Yonex Super Grap',
            'description' => 'Quấn cán êm ái chống trơn trượt',
            'price' => 35000,
            'cost' => 18000,
            'stock_quantity' => 25,
            'category' => 'equipment',
            'unit' => 'cái',
            'min_stock' => 5,
            'tax_rate' => 8,
        ], $headers);

        $createRes->assertCreated()
            ->assertJsonPath('product.sku', 'GRIP-YONEX-01')
            ->assertJsonPath('product.barcode', '8938500123456')
            ->assertJsonPath('product.stock_quantity', 25)
            ->assertJsonPath('product.tax_rate', 8);

        $productId = $createRes->json('product.id');
        $this->assertNotNull($productId);

        // Initial stock movement created
        $this->assertDatabaseHas('stock_movements', [
            'organization_id' => $this->tenantA->id,
            'product_id' => $productId,
            'type' => 'in',
            'quantity_delta' => 25,
        ]);

        // 2. Read Product
        $showRes = $this->actingAs($this->cashierA)->getJson("/api/pos/products/{$productId}", [
            'X-Organization-Id' => $this->tenantA->id,
        ]);
        $showRes->assertOk()
            ->assertJsonPath('product.id', $productId)
            ->assertJsonPath('product.name', 'Quấn cán Yonex Super Grap');

        // 3. Update Product
        $updateRes = $this->actingAs($this->managerA)->putJson("/api/pos/products/{$productId}", [
            'name' => 'Quấn cán Yonex Super Grap AC102EX',
            'price' => 38000,
        ], array_merge($headers, ['Idempotency-Key' => 'prod-upd-'.uniqid()]));

        $updateRes->assertOk()
            ->assertJsonPath('product.name', 'Quấn cán Yonex Super Grap AC102EX')
            ->assertJsonPath('product.price', 38000);

        // 4. Soft Delete Product
        $delRes = $this->actingAs($this->managerA)->deleteJson("/api/pos/products/{$productId}", [], [
            'X-Organization-Id' => $this->tenantA->id,
        ]);
        $delRes->assertOk();

        $product = PosProduct::findOrFail($productId);
        $this->assertFalse($product->is_active);
    }

    public function test_sku_and_barcode_uniqueness_enforced_per_tenant(): void
    {
        $headersA = [
            'X-Organization-Id' => $this->tenantA->id,
            'Idempotency-Key' => 'prod-uniq-1',
        ];

        // Create product in Tenant A
        $this->actingAs($this->managerA)->postJson('/api/pos/products', [
            'sku' => 'BALL-DUNLOP',
            'barcode' => '8930000000001',
            'name' => 'Bóng Tennis Dunlop Fort',
            'price' => 120000,
            'stock_quantity' => 10,
        ], $headersA)->assertCreated();

        // Duplicate SKU in same tenant fails with 422
        $dupSkuRes = $this->actingAs($this->managerA)->postJson('/api/pos/products', [
            'sku' => 'BALL-DUNLOP',
            'barcode' => '8930000000002',
            'name' => 'Bóng Tennis Dunlop Khác',
            'price' => 125000,
        ], array_merge($headersA, ['Idempotency-Key' => 'prod-dup-sku']));

        $dupSkuRes->assertStatus(422)
            ->assertJsonValidationErrors('sku');

        // Duplicate barcode in same tenant fails with 422
        $dupBarcodeRes = $this->actingAs($this->managerA)->postJson('/api/pos/products', [
            'sku' => 'BALL-DUNLOP-NEW',
            'barcode' => '8930000000001',
            'name' => 'Bóng Tennis Dunlop Mới',
            'price' => 130000,
        ], array_merge($headersA, ['Idempotency-Key' => 'prod-dup-bar']));

        $dupBarcodeRes->assertStatus(422)
            ->assertJsonValidationErrors('barcode');

        // Same SKU in Tenant B succeeds (tenant isolation)
        $managerB = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $this->tenantB->id,
            'user_id' => $managerB->id,
            'role' => OrganizationMember::ROLE_VENUE_MANAGER,
            'status' => 'active',
        ]);

        $tenantBRes = $this->actingAs($managerB)->postJson('/api/pos/products', [
            'sku' => 'BALL-DUNLOP',
            'barcode' => '8930000000001',
            'name' => 'Dunlop Balls in Tenant B',
            'price' => 120000,
        ], [
            'X-Organization-Id' => $this->tenantB->id,
            'Idempotency-Key' => 'prod-tenant-b-1',
        ]);

        $tenantBRes->assertCreated();
    }

    public function test_stock_adjustments_and_atomic_stock_check(): void
    {
        $product = PosProduct::create([
            'organization_id' => $this->tenantA->id,
            'sku' => 'SHUTTLE-AEROSENSA',
            'name' => 'Ống cầu Yonex Aerosensa 50',
            'price' => 450000,
            'cost' => 380000,
            'stock_quantity' => 15,
            'is_active' => true,
        ]);

        $headers = [
            'X-Organization-Id' => $this->tenantA->id,
            'Idempotency-Key' => 'adj-'.uniqid(),
        ];

        // 1. Inflow / Receipt adjustment: +10 units
        $inRes = $this->actingAs($this->managerA)->postJson("/api/pos/products/{$product->id}/stock", [
            'quantity_delta' => 10,
            'type' => 'receipt',
            'reference' => 'PO-YONEX-2026-09',
            'note' => 'Nhập kho lô cầu mới',
        ], $headers);

        $inRes->assertOk()
            ->assertJsonPath('product.stock_quantity', 25);
        $this->assertSame(25, $product->fresh()->stock_quantity);

        $this->assertDatabaseHas('stock_movements', [
            'organization_id' => $this->tenantA->id,
            'product_id' => $product->id,
            'type' => 'receipt',
            'quantity_delta' => 10,
        ]);

        // 2. Outflow / Disposal adjustment: -5 units
        $outRes = $this->actingAs($this->managerA)->postJson("/api/pos/products/{$product->id}/stock", [
            'quantity_delta' => -5,
            'type' => 'disposal',
            'note' => 'Hàng mẫu và hàng hỏng',
        ], array_merge($headers, ['Idempotency-Key' => 'adj-out-'.uniqid()]));

        $outRes->assertOk()
            ->assertJsonPath('product.stock_quantity', 20);

        // 3. ATOMIC CHECK: Attempt to decrement by -50 when only 20 available
        $overflowRes = $this->actingAs($this->managerA)->postJson("/api/pos/products/{$product->id}/stock", [
            'quantity_delta' => -50,
            'type' => 'disposal',
        ], array_merge($headers, ['Idempotency-Key' => 'adj-overflow-'.uniqid()]));

        $overflowRes->assertStatus(409)
            ->assertJsonFragment(['message' => 'Insufficient stock.']);

        // Assert stock quantity remains untouched at 20
        $this->assertSame(20, $product->fresh()->stock_quantity);

        // 4. Query movements audit history
        $movementsRes = $this->actingAs($this->managerA)->getJson("/api/pos/inventory/movements?product_id={$product->id}", [
            'X-Organization-Id' => $this->tenantA->id,
        ]);

        $movementsRes->assertOk();
        $items = $movementsRes->json();
        $this->assertCount(2, $items);
        $this->assertSame(10, $items[1]['quantity_delta']);
        $this->assertSame(-5, $items[0]['quantity_delta']);
        $this->assertSame(20, $items[0]['metadata']['balance_after']);
    }
}
