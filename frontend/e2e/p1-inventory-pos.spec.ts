import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { test, expect } from '@playwright/test'
import { getTestManifest } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'
import { execFileSync } from 'node:child_process'
import path from 'node:path'

test.describe('P1: Quản lý hàng hóa POS, Biến động kho, Mặt hàng cuối, Đổi trả & Hóa đơn xác thực @p1', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  test('8.4.1: Danh mục sản phẩm CRUD, thiết lập nhóm/đơn vị tính/thuế và tính duy nhất của SKU/mã vạch theo tenant', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const org1 = manifest.fixtures?.organizations.org1
    const org2 = manifest.fixtures?.organizations.org2
    const org1Id = org1?.id
    const org2Id = org2?.id
    if (!org1Id || !org2Id) throw new Error('Org1 or Org2 missing from manifest')

    const backendDir = path.resolve(import.meta.dirname, '../../backend')
    const backendEnv = {
      APP_ENV: 'testing',
      DB_CONNECTION: 'pgsql',
      DB_HOST: manifest.database.host,
      DB_PORT: String(manifest.database.port),
      DB_DATABASE: manifest.database.name,
      DB_USERNAME: manifest.database.user,
      DB_PASSWORD: manifest.database.password || '',
      LARAVEL_STORAGE_PATH: manifest.storagePath,
    }

    const crudScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\PosProduct;
      use App\\Models\\StockMovement;
      use App\\Models\\User;
      use Illuminate\\Support\\Str;

      $uniqueSku = 'SKU-TEST-' . Str::upper(Str::random(6));
      $uniqueBarcode = '893' . str_pad((string) mt_rand(1, 999999999), 10, '0', STR_PAD_LEFT);

      // 1. Create product in Tenant 1
      $product1 = PosProduct::create([
        'organization_id' => ${org1Id},
        'sku' => $uniqueSku,
        'barcode' => $uniqueBarcode,
        'name' => 'Vợt Cầu Lông Victor Thruster K',
        'description' => 'Vợt công thủ toàn diện',
        'price' => 1500000,
        'cost' => 1100000,
        'stock_quantity' => 12,
        'category' => 'equipment',
        'unit' => 'cây',
        'min_stock' => 3,
        'is_active' => true,
        'metadata' => [
          'tax_rate' => 8,
          'category' => 'equipment',
        ],
      ]);

      StockMovement::create([
        'organization_id' => ${org1Id},
        'product_id' => $product1->id,
        'type' => 'in',
        'quantity_delta' => 12,
        'unit_cost' => 1100000,
        'created_by' => ${getActorCredentials(manifest, 'owner1').id},
        'metadata' => ['reason' => 'Initial stock seed'],
      ]);

      // 2. Duplicate SKU in Tenant 1 MUST fail
      $duplicateSkuFailed = false;
      try {
        PosProduct::create([
          'organization_id' => ${org1Id},
          'sku' => $uniqueSku,
          'barcode' => '8939999999999',
          'name' => 'Trùng SKU Tenant 1',
          'price' => 1600000,
          'stock_quantity' => 5,
        ]);
      } catch (\\Throwable $e) {
        $duplicateSkuFailed = true;
      }

      // 3. Same SKU in Tenant 2 MUST SUCCEED (Tenant isolation)
      $tenant2Product = PosProduct::create([
        'organization_id' => ${org2Id},
        'sku' => $uniqueSku,
        'barcode' => $uniqueBarcode,
        'name' => 'Vợt Victor tại Tenant 2',
        'price' => 1550000,
        'stock_quantity' => 8,
      ]);

      // 4. Update Product
      $product1->update([
        'name' => 'Vợt Cầu Lông Victor Thruster K Enhanced',
        'price' => 1580000,
      ]);

      // 5. Soft-delete product
      $product1->update(['is_active' => false]);

      echo json_encode([
        'product1_id' => $product1->id,
        'product1_name' => $product1->fresh()->name,
        'product1_price' => $product1->fresh()->price,
        'product1_is_active' => $product1->fresh()->is_active,
        'duplicate_sku_failed' => $duplicateSkuFailed,
        'tenant2_product_id' => $tenant2Product->id,
      ]);
    `

    const crudResult = JSON.parse(
      execFileSync('php', ['-r', crudScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      product1_id: number
      product1_name: string
      product1_price: number
      product1_is_active: boolean
      duplicate_sku_failed: boolean
      tenant2_product_id: number
    }

    expect(crudResult.product1_id).toBeGreaterThan(0)
    expect(crudResult.product1_name).toBe('Vợt Cầu Lông Victor Thruster K Enhanced')
    expect(crudResult.product1_price).toBe(1580000)
    expect(crudResult.product1_is_active).toBe(false)
    expect(crudResult.duplicate_sku_failed).toBe(true)
    expect(crudResult.tenant2_product_id).toBeGreaterThan(0)

    // UI Verification: Owner visits /admin/inventory
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto('/admin/inventory', { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.admin-inventory-view')).toBeVisible({ timeout: 15000 })
    await expect(page.locator('h1')).toContainText('Quản lý Kho Hàng')
  })

  test('8.4.2: Nhật ký điều chỉnh & biến động kho (nhập, hủy, điều chỉnh, theo dõi số lượng tồn sau giao dịch)', async () => {
    const manifest = getTestManifest()
    const org1 = manifest.fixtures?.organizations.org1
    const org1Id = org1?.id
    if (!org1Id) throw new Error('Org1 missing from manifest')

    const backendDir = path.resolve(import.meta.dirname, '../../backend')
    const backendEnv = {
      APP_ENV: 'testing',
      DB_CONNECTION: 'pgsql',
      DB_HOST: manifest.database.host,
      DB_PORT: String(manifest.database.port),
      DB_DATABASE: manifest.database.name,
      DB_USERNAME: manifest.database.user,
      DB_PASSWORD: manifest.database.password || '',
      LARAVEL_STORAGE_PATH: manifest.storagePath,
    }

    const adjustScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\PosProduct;
      use App\\Models\\StockMovement;
      use App\\Services\\POS\\PosService;
      use Illuminate\\Support\\Str;

      $posService = app(PosService::class);
      $user = \\App\\Models\\User::where('email', '${getActorCredentials(manifest, 'owner1').email}')->firstOrFail();

      $product = PosProduct::create([
        'organization_id' => ${org1Id},
        'sku' => 'SKU-ADJ-' . Str::upper(Str::random(6)),
        'name' => 'Nước Điện Giải Pocari Sweat',
        'price' => 15000,
        'cost' => 9000,
        'stock_quantity' => 10,
        'is_active' => true,
      ]);

      // 1. Inbound adjustment (+15 units via receipt)
      $inRes = $posService->adjustStock($product, 15, $user, 'receipt', 'PO-POCARI-01', 'Nhập thêm 15 chai');
      $stockAfterIn = $product->fresh()->stock_quantity;

      // 2. Outbound adjustment (-5 units via disposal)
      $outRes = $posService->adjustStock($product, -5, $user, 'disposal', 'DISPOSAL-01', 'Hỏng 5 chai do va chạm');
      $stockAfterOut = $product->fresh()->stock_quantity;

      // 3. Overflow check: Attempt to decrement by -100 when only 20 in stock
      $overflowRejected = false;
      try {
        $posService->adjustStock($product, -100, $user, 'disposal', 'OVERFLOW', 'Thử giảm quá tồn');
      } catch (\\Symfony\\Component\\HttpKernel\\Exception\\HttpException $e) {
        if ($e->getStatusCode() === 409) {
          $overflowRejected = true;
        }
      }
      $stockAfterOverflow = $product->fresh()->stock_quantity;

      // 4. Retrieve movements audit trail
      $movements = StockMovement::where('product_id', $product->id)->latest('id')->get();

      echo json_encode([
        'stock_after_in' => $stockAfterIn,
        'stock_after_out' => $stockAfterOut,
        'overflow_rejected' => $overflowRejected,
        'stock_after_overflow' => $stockAfterOverflow,
        'movements_count' => $movements->count(),
        'last_movement_type' => $movements->first()->type,
        'last_movement_delta' => $movements->first()->quantity_delta,
        'last_movement_balance_after' => $movements->first()->metadata['balance_after'] ?? null,
      ]);
    `

    const adjustResult = JSON.parse(
      execFileSync('php', ['-r', adjustScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      stock_after_in: number
      stock_after_out: number
      overflow_rejected: boolean
      stock_after_overflow: number
      movements_count: number
      last_movement_type: string
      last_movement_delta: number
      last_movement_balance_after: number
    }

    expect(adjustResult.stock_after_in).toBe(25)
    expect(adjustResult.stock_after_out).toBe(20)
    expect(adjustResult.overflow_rejected).toBe(true)
    expect(adjustResult.stock_after_overflow).toBe(20) // Untouched!
    expect(adjustResult.movements_count).toBeGreaterThanOrEqual(2)
    expect(adjustResult.last_movement_type).toBe('disposal')
    expect(adjustResult.last_movement_delta).toBe(-5)
    expect(adjustResult.last_movement_balance_after).toBe(20)
  })

  test('8.4.3: Kiểm tra hết hàng và xử lý thanh toán mặt hàng cuối cùng trong kho', async () => {
    const manifest = getTestManifest()
    const org1 = manifest.fixtures?.organizations.org1
    const org1Id = org1?.id
    if (!org1Id) throw new Error('Org1 missing from manifest')

    const backendDir = path.resolve(import.meta.dirname, '../../backend')
    const backendEnv = {
      APP_ENV: 'testing',
      DB_CONNECTION: 'pgsql',
      DB_HOST: manifest.database.host,
      DB_PORT: String(manifest.database.port),
      DB_DATABASE: manifest.database.name,
      DB_USERNAME: manifest.database.user,
      DB_PASSWORD: manifest.database.password || '',
      LARAVEL_STORAGE_PATH: manifest.storagePath,
    }

    const lastItemScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\CashShift;
      use App\\Models\\PosProduct;
      use App\\Models\\User;
      use App\\Services\\POS\\PosService;
      use Illuminate\\Support\\Str;
      use Symfony\\Component\\HttpKernel\\Exception\\HttpException;

      $posService = app(PosService::class);
      $cashier = User::where('email', '${getActorCredentials(manifest, 'cashier1').email}')->firstOrFail();

      $shift = CashShift::create([
        'organization_id' => ${org1Id},
        'cashier_id' => $cashier->id,
        'opening_cash' => 200000,
        'opened_at' => now(),
        'status' => 'open',
      ]);

      // Product with exactly 1 unit left
      $product = PosProduct::create([
        'organization_id' => ${org1Id},
        'sku' => 'SKU-LAST-' . Str::upper(Str::random(6)),
        'name' => 'Áo Thi Đấu Aura Pro Limited',
        'price' => 350000,
        'cost' => 200000,
        'stock_quantity' => 1,
        'is_active' => true,
      ]);

      // 1. Checkout last item (1 unit) -> MUST SUCCEED
      $order1 = $posService->completeOrder(
        $cashier,
        ${org1Id},
        [
          'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
          ],
          'payment_method' => 'cash',
          'cash_tendered' => 400000,
        ]
      );

      $stockAfterFirstOrder = $product->fresh()->stock_quantity;

      // 2. Second checkout on 0-stock product -> MUST FAIL with 409
      $secondCheckoutRejected = false;
      try {
        $posService->completeOrder(
          $cashier,
          ${org1Id},
          [
            'items' => [
              ['product_id' => $product->id, 'quantity' => 1],
            ],
            'payment_method' => 'cash',
            'cash_tendered' => 400000,
          ]
        );
      } catch (HttpException $e) {
        if ($e->getStatusCode() === 409) {
          $secondCheckoutRejected = true;
        }
      }

      echo json_encode([
        'order1_id' => $order1->id,
        'order1_total' => $order1->total_amount,
        'stock_after_first' => $stockAfterFirstOrder,
        'second_checkout_rejected' => $secondCheckoutRejected,
      ]);
    `

    const lastItemResult = JSON.parse(
      execFileSync('php', ['-r', lastItemScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      order1_id: number
      order1_total: number
      stock_after_first: number
      second_checkout_rejected: boolean
    }

    expect(lastItemResult.order1_id).toBeGreaterThan(0)
    expect(lastItemResult.stock_after_first).toBe(0) // Stock decreased to zero
    expect(lastItemResult.second_checkout_rejected).toBe(true) // Out of stock rejected with 409!
  })

  test('8.4.4: Vòng đời đổi trả POS / điều chỉnh: hoàn kho hàng hóa, điều chỉnh tiền ca & hạch toán sổ cái tài chính', async () => {
    const manifest = getTestManifest()
    const org1 = manifest.fixtures?.organizations.org1
    const org1Id = org1?.id
    if (!org1Id) throw new Error('Org1 missing from manifest')

    const backendDir = path.resolve(import.meta.dirname, '../../backend')
    const backendEnv = {
      APP_ENV: 'testing',
      DB_CONNECTION: 'pgsql',
      DB_HOST: manifest.database.host,
      DB_PORT: String(manifest.database.port),
      DB_DATABASE: manifest.database.name,
      DB_USERNAME: manifest.database.user,
      DB_PASSWORD: manifest.database.password || '',
      LARAVEL_STORAGE_PATH: manifest.storagePath,
    }

    const returnScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\CashShift;
      use App\\Models\\Journal;
      use App\\Models\\PosProduct;
      use App\\Models\\StockMovement;
      use App\\Models\\User;
      use App\\Services\\POS\\PosService;
      use Illuminate\\Support\\Str;

      $posService = app(PosService::class);
      $cashier = User::where('email', '${getActorCredentials(manifest, 'cashier1').email}')->firstOrFail();

      $shift = CashShift::create([
        'organization_id' => ${org1Id},
        'cashier_id' => $cashier->id,
        'opening_cash' => 500000,
        'opened_at' => now(),
        'status' => 'open',
      ]);

      $product = PosProduct::create([
        'organization_id' => ${org1Id},
        'sku' => 'SKU-RET-' . Str::upper(Str::random(6)),
        'name' => 'Bóng Tennis Wilson US Open',
        'price' => 100000,
        'cost' => 60000,
        'stock_quantity' => 10,
        'is_active' => true,
      ]);

      // 1. Complete order of 2 units (stock: 10 -> 8)
      $order = $posService->completeOrder(
        $cashier,
        ${org1Id},
        [
          'items' => [
            ['product_id' => $product->id, 'quantity' => 2],
          ],
          'payment_method' => 'cash',
          'cash_tendered' => 200000,
        ]
      );

      $stockAfterSale = $product->fresh()->stock_quantity;

      // 2. Refund / return order with restock
      $refundedOrder = $posService->refundOrder(
        $order,
        $cashier,
        [
          ['product_id' => $product->id, 'quantity' => 2, 'restock' => true],
        ],
        'Khách đổi ý trả lại bóng',
        'cash'
      );

      $stockAfterReturn = $product->fresh()->stock_quantity;

      // Check stock movement created with type 'return'
      $returnMovement = StockMovement::where('product_id', $product->id)
        ->where('type', 'return')
        ->first();

      // Check journal ledger entry for return
      $returnJournal = Journal::where('organization_id', ${org1Id})
        ->where('event_type', 'refund')
        ->latest('id')
        ->first();

      echo json_encode([
        'order_id' => $order->id,
        'stock_after_sale' => $stockAfterSale,
        'stock_after_return' => $stockAfterReturn,
        'refund_status' => $refundedOrder->status,
        'has_return_movement' => $returnMovement !== null,
        'return_delta' => $returnMovement ? $returnMovement->quantity_delta : 0,
        'has_return_journal' => $returnJournal !== null,
      ]);
    `

    const returnResult = JSON.parse(
      execFileSync('php', ['-r', returnScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      order_id: number
      stock_after_sale: number
      stock_after_return: number
      refund_status: string
      has_return_movement: boolean
      return_delta: number
      has_return_journal: boolean
    }

    expect(returnResult.order_id).toBeGreaterThan(0)
    expect(returnResult.stock_after_sale).toBe(8)
    expect(returnResult.stock_after_return).toBe(10) // Restocked back to 10
    expect(returnResult.refund_status).toBe('refunded')
    expect(returnResult.has_return_movement).toBe(true)
    expect(returnResult.return_delta).toBe(2)
    expect(returnResult.has_return_journal).toBe(true)
  })

  test('8.4.5: Tạo hóa đơn xác thực, định dạng in ấn, gửi email/SMS và duy trì sau khi F5', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const cashier = getActorCredentials(manifest, 'cashier1')
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const org1Id = org1?.id
    if (!org1Id) throw new Error('Org1 missing from manifest')

    const backendDir = path.resolve(import.meta.dirname, '../../backend')
    const backendEnv = {
      APP_ENV: 'testing',
      DB_CONNECTION: 'pgsql',
      DB_HOST: manifest.database.host,
      DB_PORT: String(manifest.database.port),
      DB_DATABASE: manifest.database.name,
      DB_USERNAME: manifest.database.user,
      DB_PASSWORD: manifest.database.password || '',
      LARAVEL_STORAGE_PATH: manifest.storagePath,
    }

    // 1. Arrange completed order with VAT & change
    const arrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\CashShift;
      use App\\Models\\PosProduct;
      use App\\Models\\User;
      use App\\Services\\POS\\PosService;
      use Illuminate\\Support\\Str;

      $posService = app(PosService::class);
      $cashierUser = User::where('email', '${cashier.email}')->firstOrFail();
      $customerUser = User::where('email', '${customer.email}')->firstOrFail();

      $shift = CashShift::create([
        'organization_id' => ${org1Id},
        'cashier_id' => $cashierUser->id,
        'opening_cash' => 500000,
        'opened_at' => now(),
        'status' => 'open',
      ]);

      $drink = PosProduct::create([
        'organization_id' => ${org1Id},
        'sku' => 'SKU-DRK-' . Str::upper(Str::random(6)),
        'name' => 'Nước khoáng Lavie 500ml',
        'price' => 10000,
        'cost' => 5000,
        'stock_quantity' => 50,
        'is_active' => true,
        'metadata' => [
          'tax_rate' => 8,
          'category' => 'drinks',
        ],
      ]);

      // Complete order: 2 drinks = 20,000 + 8% VAT = 21,600. Cash tendered = 30,000. Change = 8,400.
      $order = $posService->completeOrder(
        $cashierUser,
        ${org1Id},
        [
          'items' => [
            ['product_id' => $drink->id, 'quantity' => 2, 'vat_rate' => 0.08],
          ],
          'payment_method' => 'cash',
          'cash_tendered' => 30000,
          'customer_id' => $customerUser->id,
        ]
      );

      echo json_encode([
        'order_id' => $order->id,
        'total_amount' => $order->total_amount,
        'cash_tendered' => $order->cash_tendered,
        'change_due' => $order->change_due,
        'customer_email' => $customerUser->email,
        'token' => $cashierUser->createToken('test')->plainTextToken,
      ]);
    `

    const arranged = JSON.parse(
      execFileSync('php', ['-r', arrangeScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      order_id: number
      total_amount: number
      cash_tendered: number
      change_due: number
      customer_email: string
      token: string
    }

    expect(arranged.order_id).toBeGreaterThan(0)
    expect(arranged.total_amount).toBe(21600)
    expect(arranged.cash_tendered).toBe(30000)
    expect(arranged.change_due).toBe(8400)

    // 2. Test Receipt Print formatting endpoint via authenticated API call
    const printRes = await page.request.post(
      `${manifest.backend.url}/api/v2/pos/orders/${arranged.order_id}/receipt/print`,
      {
        headers: {
          Authorization: `Bearer ${arranged.token}`,
          'X-Organization-Id': String(org1Id),
        },
      },
    )
    expect(printRes.ok()).toBe(true)
    const printData = await printRes.json()

    expect(printData.printable).toBe(true)
    expect(printData.receipt.grand_total).toBe(21600)
    expect(printData.receipt.cashier_name).toBeTruthy()
    expect(printData.receipt.customer_name).toBeTruthy()
    expect(printData.receipt.items.length).toBe(1)
    expect(printData.receipt.tax_amount).toBe(1600)

    // 3. Test Receipt Send endpoint (email dispatch)
    const sendRes = await page.request.post(
      `${manifest.backend.url}/api/v2/pos/orders/${arranged.order_id}/receipt/send`,
      {
        headers: {
          Authorization: `Bearer ${arranged.token}`,
          'X-Organization-Id': String(org1Id),
          'Content-Type': 'application/json',
        },
        data: {
          email: arranged.customer_email,
        },
      },
    )
    expect(sendRes.ok()).toBe(true)
    const sendData = await sendRes.json()

    expect(sendData.sent).toBe(true)
    expect(sendData.recipient).toBe(arranged.customer_email)
    expect(sendData.order_id).toBe(arranged.order_id)
    // 4. Persistence across reload / re-query
    const reloadScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\PosOrder;

      $order = PosOrder::with(['items', 'payments'])->findOrFail(${arranged.order_id});

      echo json_encode([
        'id' => $order->id,
        'status' => $order->status,
        'subtotal' => $order->subtotal,
        'tax_amount' => $order->tax_amount,
        'total_amount' => $order->total_amount,
        'cash_tendered' => $order->cash_tendered,
        'change_due' => $order->change_due,
        'receipt_grand_total' => $order->receipt['grand_total'] ?? null,
      ]);
    `

    const reloadResult = JSON.parse(
      execFileSync('php', ['-r', reloadScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      id: number
      status: string
      subtotal: number
      tax_amount: number
      total_amount: number
      cash_tendered: number
      change_due: number
      receipt_grand_total: number
    }

    expect(reloadResult.id).toBe(arranged.order_id)
    expect(reloadResult.status).toBe('completed')
    expect(reloadResult.subtotal).toBe(20000)
    expect(reloadResult.tax_amount).toBe(1600)
    expect(reloadResult.total_amount).toBe(21600)
    expect(reloadResult.cash_tendered).toBe(30000)
    expect(reloadResult.change_due).toBe(8400)
    expect(reloadResult.receipt_grand_total).toBe(21600)
  })
})
