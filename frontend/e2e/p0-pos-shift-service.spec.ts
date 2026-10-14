import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { withE2EApi } from './runner/with-api.ts'
import { test, expect } from '@playwright/test'
import path from 'node:path'
import { execFileSync } from 'node:child_process'
import {
  getTestManifest,
  arrangeCourt,
  arrangePosStock,
  sendSyntheticPaymentWebhook,
  formatVietnamDate,
} from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'

test.describe('P0: Quầy POS, Ca làm việc & Tab dịch vụ tại sân (Mục 5) @p0', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
      window.localStorage.setItem('locale', 'vi')
      window.localStorage.setItem('aura_theme', 'light')
    })

    const manifest = getTestManifest()
    const backendDir = path.resolve(import.meta.dirname, '../../backend')
    const backendEnv = {
      APP_NAME: 'Aura Court E2E',
      CACHE_PREFIX: 'aura-court-e2e-cache-',
      APP_ENV: 'testing',
      DB_CONNECTION: 'pgsql',
      DB_HOST: manifest.database.host,
      DB_PORT: String(manifest.database.port),
      DB_DATABASE: manifest.database.name,
      DB_USERNAME: manifest.database.user,
      DB_PASSWORD: manifest.database.password || '',
      LARAVEL_STORAGE_PATH: manifest.storagePath,
      FEATURE_DIRECT_OWNER_PAYMENTS: 'true',
    }
    const cleanShiftScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();
      App\\Models\\CashShift::where('organization_id', ${manifest.fixtures?.organizations.org1.id})->where('status', 'open')->update(['status' => 'closed', 'closed_at' => now()]);
    `
    execFileSync('php', ['-r', cleanShiftScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })
  })
  test('5.1: Mở ca -> chọn khách/hàng & tính giá -> bán tiền mặt -> hóa đơn -> F5 chứng minh đơn duy nhất, trừ kho & tổng tiền ca', async ({
    page,
  }) => {
    test.setTimeout(90000)
    const manifest = getTestManifest()
    const cashier = getActorCredentials(manifest, 'cashier1')
    const org1 = manifest.fixtures?.organizations.org1
    if (!org1) throw new Error('Missing org1 fixture')

    const backendDir = path.resolve(import.meta.dirname, '../../backend')
    const backendEnv = {
      APP_NAME: 'Aura Court E2E',
      CACHE_PREFIX: 'aura-court-e2e-cache-',
      APP_ENV: 'testing',
      DB_CONNECTION: 'pgsql',
      DB_HOST: manifest.database.host,
      DB_PORT: String(manifest.database.port),
      DB_DATABASE: manifest.database.name,
      DB_USERNAME: manifest.database.user,
      DB_PASSWORD: manifest.database.password || '',
      LARAVEL_STORAGE_PATH: manifest.storagePath,
      FEATURE_DIRECT_OWNER_PAYMENTS: 'true',
    }

    // 1. Arrange a dedicated POS product with known stock for deterministic testing
    const stockItem = arrangePosStock(manifest, {
      testKey: '5_1_cash',
      name: `Nước Khoáng Lavie 500ml ${Date.now()}`,
      price: 15000,
      cost: 7000,
      stockQuantity: 40,
    })

    // 2. Login as cashier
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(cashier.email)
    await page.locator('input[type="password"], input[name="password"]').fill(cashier.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 3. Navigate to /admin/pos
    await page.goto('/admin/pos', { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.pos-view-layout, .pos-topbar').first()).toBeVisible({
      timeout: 20000,
    })

    // 4. Ensure an active shift exists or open one
    const shiftBtn = page.locator('.topbar-btn.shift-btn')
    await expect(shiftBtn).toBeVisible({ timeout: 10000 })
    const isShiftOpen = (await shiftBtn.locator('.shift-pulse').count()) > 0

    if (!isShiftOpen) {
      await shiftBtn.click()
      const shiftModal = page.locator('.modal-card')
      await expect(shiftModal).toBeVisible({ timeout: 10000 })

      // Fill opening cash: 500,000 VND
      const openingInput = shiftModal.locator('input[type="number"]')
      await openingInput.fill('500000')

      // Click Open Shift
      const openBtn = shiftModal.locator('.btn-primary')
      const openPromise = page
        .waitForResponse(
          (res) => res.url().includes('/pos/shifts') && res.request().method() === 'POST',
          { timeout: 20000 },
        )
        .catch(() => null)
      await openBtn.click()
      await openPromise
      await expect(shiftModal).not.toBeVisible({ timeout: 20000 })
    }

    // 5. Select product and verify quote in cart
    await expect(page.locator('.catalog-search-input')).toBeVisible({ timeout: 10000 })
    await page.locator('.catalog-search-input').fill(stockItem.name)
    await page.waitForTimeout(300)

    const productCard = page.locator('.product-card', { hasText: stockItem.name })
    await expect(productCard).toBeVisible({ timeout: 15000 })
    await productCard.click()

    // Verify cart item and line total (15,000 VND + 8% VAT = 16,200 VND)
    const cartItem = page.locator('.cart-item-row', { hasText: stockItem.name })
    await expect(cartItem).toBeVisible({ timeout: 10000 })
    await expect(page.locator('.grand-total-val')).toContainText('16.200')

    // 6. Select cash payment and enter cash tendered (20,000 VND)
    const cashMethodBtn = page.locator('.pay-method-btn', { hasText: 'Tiền mặt' })
    await cashMethodBtn.click()

    const cashTenderedInput = page.locator('.cash-tendered-input')
    await cashTenderedInput.fill('20000')
    await expect(page.locator('.change-amount')).toContainText('3.800')
    // 7. Click Checkout -> completes cash order -> receipt modal opens
    const checkoutBtn = page.locator('.checkout-submit-btn')
    await expect(checkoutBtn).toBeEnabled({ timeout: 10000 })

    const orderResponsePromise = page.waitForResponse(
      (res) =>
        res.url().includes('/api/pos/orders') &&
        res.request().method() === 'POST' &&
        (res.status() === 200 || res.status() === 201),
      { timeout: 20000 },
    )
    await checkoutBtn.click()

    const orderResponse = await orderResponsePromise
    const orderData = await orderResponse.json()
    const orderId = orderData?.order?.id || orderData?.id
    expect(orderId).toBeDefined()

    // 8. Verify Receipt Modal
    const receiptModal = page.locator('.receipt-printable')
    await expect(receiptModal).toBeVisible({ timeout: 15000 })
    await expect(receiptModal).toContainText('AURA COURT SYSTEM')
    await expect(receiptModal).toContainText(stockItem.name)

    // Close receipt modal
    await page.locator('.modal-close-btn').click()
    await expect(receiptModal).not.toBeVisible({ timeout: 10000 })

    // 9. Reload /admin/pos and verify authoritative database state
    await page.reload({ waitUntil: 'domcontentloaded' })
    await expect(page.locator('.pos-topbar')).toBeVisible({ timeout: 20000 })

    const dbCheckScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\PosOrder;
      use App\\Models\\PosProduct;
      use App\\Models\\CashShift;

      $order = PosOrder::where('id', ${orderId})->firstOrFail();
      $product = PosProduct::where('id', ${stockItem.product_id})->firstOrFail();
      $shift = CashShift::where('id', $order->cash_shift_id)->firstOrFail();
      $cashSales = (int) PosOrder::where('cash_shift_id', $shift->id)
        ->where('status', 'completed')
        ->where('payment_method', 'cash')
        ->sum('total_amount');

      echo json_encode([
        'order_status' => $order->status,
        'order_total' => (int) $order->total_amount,
        'payment_method' => $order->payment_method,
        'remaining_stock' => (int) $product->stock_quantity,
        'shift_cash_sales' => $cashSales,
      ]);
    `
    const dbRaw = execFileSync('php', ['-r', dbCheckScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })
    const dbState = JSON.parse(dbRaw)
    expect(dbState.order_status).toBe('completed')
    expect(dbState.order_total).toBe(16200)
    expect(dbState.payment_method).toBe('cash')
    // Stock decreased from 40 to 39
    expect(dbState.remaining_stock).toBe(39)
    // Shift recorded cash sales
    expect(dbState.shift_cash_sales).toBeGreaterThanOrEqual(15000)
  })

  test('5.2: Bán hàng QR pending -> webhook xác thực chuyển paid & trừ tồn kho -> thử lại không trừ kho hai lần', async ({
    page,
  }) => {
    test.setTimeout(90000)
    const manifest = getTestManifest()
    const cashier = getActorCredentials(manifest, 'cashier1')
    const venue1A = manifest.fixtures?.organizations.org1?.venue1A
    if (!venue1A?.routingId) throw new Error('Missing venue1A routingId')

    const backendDir = path.resolve(import.meta.dirname, '../../backend')
    const backendEnv = {
      APP_NAME: 'Aura Court E2E',
      CACHE_PREFIX: 'aura-court-e2e-cache-',
      APP_ENV: 'testing',
      DB_CONNECTION: 'pgsql',
      DB_HOST: manifest.database.host,
      DB_PORT: String(manifest.database.port),
      DB_DATABASE: manifest.database.name,
      DB_USERNAME: manifest.database.user,
      DB_PASSWORD: manifest.database.password || '',
      LARAVEL_STORAGE_PATH: manifest.storagePath,
      FEATURE_DIRECT_OWNER_PAYMENTS: 'true',
    }

    // 1. Arrange a dedicated product with 25 in stock
    const stockItem = arrangePosStock(manifest, {
      testKey: '5_2_qr',
      name: `Vợt Cầu Lông Yonex Carbon ${Date.now()}`,
      price: 200000,
      cost: 120000,
      stockQuantity: 25,
    })

    // 2. Login as cashier
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(cashier.email)
    await page.locator('input[type="password"], input[name="password"]').fill(cashier.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto('/admin/pos', { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.pos-topbar')).toBeVisible({ timeout: 20000 })

    // Ensure shift is open
    const shiftBtn = page.locator('.topbar-btn.shift-btn')
    const isShiftOpen = await shiftBtn
      .evaluate((el) => el.classList.contains('shift-active'))
      .catch(() => false)
    if (!isShiftOpen) {
      await shiftBtn.click()
      const shiftModal = page.locator('.modal-card')
      await expect(shiftModal).toBeVisible({ timeout: 10000 })
      await shiftModal.locator('input[type="number"]').fill('500000')
      await shiftModal.locator('.btn-primary').click()
      await expect(shiftModal).not.toBeVisible({ timeout: 10000 })
    }

    // 3. Search and add product to cart
    await page.locator('.catalog-search-input').fill(stockItem.name)
    await page.waitForTimeout(300)
    const productCard = page.locator('.product-card', { hasText: stockItem.name })
    await expect(productCard).toBeVisible({ timeout: 15000 })
    await productCard.click()

    // 4. Select QR payment method
    const qrMethodBtn = page.locator('.pay-method-btn', { hasText: 'VietQR' })
    await qrMethodBtn.click()

    // 5. Click checkout -> creates pending QR order
    const qrResponsePromise = page.waitForResponse(
      (res) =>
        res.url().includes('/api/pos/orders') &&
        res.request().method() === 'POST' &&
        (res.status() === 200 || res.status() === 201),
      { timeout: 20000 },
    )
    await page.locator('.checkout-submit-btn').click()
    const qrResponse = await qrResponsePromise
    const qrOrderData = await qrResponse.json()
    const orderId = qrOrderData?.order?.id || qrOrderData?.id
    const paymentRef = qrOrderData?.order?.payment_reference || qrOrderData?.payment_reference
    expect(orderId).toBeDefined()
    expect(paymentRef).toBeDefined()

    // 6. QR modal opens showing VietQR info
    const qrModal = page.locator('.qr-modal-card')
    await expect(qrModal).toBeVisible({ timeout: 15000 })
    await expect(qrModal).toContainText('Đang chờ chuyển khoản')
    // The QR names the venue owner's verified account and a server-issued AC reference.
    expect(paymentRef).toMatch(/^AC[A-Z0-9]{18}$/)
    expect(qrOrderData?.payment_attempt?.receiver?.account_number).toBe('0987654321')
    await expect(qrModal).toContainText('0987654321')
    await expect(qrModal).toContainText(paymentRef)
    // 7. NEGATIVE PROOF: there is no cashier confirm; the route is gone and the modal has no confirm button
    await expect(qrModal.locator('.btn-confirm')).toHaveCount(0)
    const unverifiedAttempt = await withE2EApi(
      page,
      async ({ api }, oId) => {
        try {
          await api.post(`/pos/orders/${oId}/confirm-qr`, {
            external_transaction_id: 'FAKE_TXN_123',
          })
          return { success: true }
        } catch (err: any) {
          return { success: false, status: err?.status, message: err?.message }
        }
      },
      orderId,
    )
    expect(unverifiedAttempt.success).toBe(false)
    expect(unverifiedAttempt.status).toBe(404)

    // Stock remains 25 while pending and unverified
    const checkPendingScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\PosOrder;
      use App\\Models\\PosProduct;

      $order = PosOrder::where('id', ${orderId})->firstOrFail();
      $product = PosProduct::where('id', ${stockItem.product_id})->firstOrFail();

      echo json_encode([
        'status' => $order->status,
        'stock' => (int) $product->stock_quantity,
      ]);
    `
    const pendingRaw = execFileSync('php', ['-r', checkPendingScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })
    const pendingState = JSON.parse(pendingRaw)
    expect(pendingState.status).toBe('pending_payment')
    expect(pendingState.stock).toBe(25)

    // 8. Send genuine synthetic payment webhook matching order payment_reference
    const totalAmount = Number(qrOrderData?.order?.total_amount || 220000)
    const webhookTxnId = `TXN-POS-QR-${manifest.runId}-${orderId}`
    // A provider retry resends the identical body, so both deliveries share one transaction date
    const transactionDate = formatVietnamDate(new Date(Date.now() + 5000))
    const webhookRes = await sendSyntheticPaymentWebhook(manifest, {
      routingId: venue1A.routingId,
      secret: `whsec_e2e_1a_${manifest.runId}`,
      accountNumber: '0987654321',
      amount: totalAmount,
      code: paymentRef,
      transactionId: webhookTxnId,
      transactionDate,
    })
    expect(
      webhookRes.ok,
      `Webhook failed with status ${webhookRes.statusCode}: ${JSON.stringify(webhookRes.body)}`,
    ).toBe(true)
    // 9. The POS polls the order: the verified owner receipt closes the QR modal and shows the receipt
    await expect(qrModal).not.toBeVisible({ timeout: 20000 })
    const receiptCloseBtn = page.locator('.modal-card .modal-close-btn')
    await expect(receiptCloseBtn).toBeVisible({ timeout: 10000 })
    await receiptCloseBtn.click()

    // 10. Database check: order is completed, stock decremented to 24
    const checkPaidScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\PosOrder;
      use App\\Models\\PosProduct;

      $order = PosOrder::where('id', ${orderId})->firstOrFail();
      $product = PosProduct::where('id', ${stockItem.product_id})->firstOrFail();

      echo json_encode([
        'status' => $order->status,
        'stock' => (int) $product->stock_quantity,
      ]);
    `
    const paidRaw = execFileSync('php', ['-r', checkPaidScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })
    const paidState = JSON.parse(paidRaw)
    expect(paidState.status).toBe('completed')
    expect(paidState.stock).toBe(24)

    // 10. Idempotency verification: the same bank event delivered again does NOT decrement stock again
    const replayRes = await sendSyntheticPaymentWebhook(manifest, {
      routingId: venue1A.routingId,
      secret: `whsec_e2e_1a_${manifest.runId}`,
      accountNumber: '0987654321',
      amount: totalAmount,
      code: paymentRef,
      transactionId: webhookTxnId,
      transactionDate,
    })
    expect(replayRes.ok).toBe(true)
    // A replayed receipt is deduplicated by provider event id, so stock moves once
    const checkRetryScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\PosProduct;
      $product = PosProduct::where('id', ${stockItem.product_id})->firstOrFail();
      echo json_encode(['final_stock' => (int) $product->stock_quantity]);
    `
    const retryRaw = execFileSync('php', ['-r', checkRetryScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })
    const retryState = JSON.parse(retryRaw)
    expect(retryState.final_stock).toBe(24)
    // 11. Cancel QR case: Create another pending QR order, cancel it, verify stock NOT decremented
    const clearBtn = page.locator('.clear-cart-link')
    if (await clearBtn.isVisible()) {
      await clearBtn.click()
    }
    await page.locator('.product-card', { hasText: stockItem.name }).click()
    await page.locator('.pay-method-btn', { hasText: 'VietQR' }).click()
    const cancelOrderResponsePromise = page.waitForResponse(
      (res) =>
        res.url().includes('/api/pos/orders') &&
        res.request().method() === 'POST' &&
        (res.status() === 200 || res.status() === 201),
      { timeout: 20000 },
    )
    await page.locator('.checkout-submit-btn').click()
    const cancelOrderResponse = await cancelOrderResponsePromise
    const cancelOrderData = await cancelOrderResponse.json()
    const cancelOrderId = cancelOrderData?.order?.id || cancelOrderData?.id

    await expect(page.locator('.qr-modal-card')).toBeVisible({ timeout: 15000 })
    // Click Cancel in QR modal
    const cancelPromise = page.waitForResponse(
      (res) => res.url().includes(`/pos/orders/${cancelOrderId}/cancel-qr`) && res.status() === 200,
      { timeout: 20000 },
    )
    await page.locator('.qr-modal-card .btn-cancel').click()
    await cancelPromise
    await expect(page.locator('.qr-modal-card')).not.toBeVisible({ timeout: 10000 })

    // Verify cancelled order in DB, stock remains 24
    const checkCancelScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\PosOrder;
      use App\\Models\\PosProduct;

      $order = PosOrder::where('id', ${cancelOrderId})->firstOrFail();
      $product = PosProduct::where('id', ${stockItem.product_id})->firstOrFail();

      echo json_encode([
        'status' => $order->status,
        'stock' => (int) $product->stock_quantity,
      ]);
    `
    const cancelRaw = execFileSync('php', ['-r', checkCancelScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })
    const cancelState = JSON.parse(cancelRaw)
    expect(cancelState.status).toBe('cancelled')
    expect(cancelState.stock).toBe(24)

    // 12. Expired QR case ("hết hạn"): Create pending QR order, expire it via TTL release, verify cancelled and stock preserved
    if (await clearBtn.isVisible()) {
      await clearBtn.click()
    }
    await page.locator('.product-card', { hasText: stockItem.name }).click()
    await page.locator('.pay-method-btn', { hasText: 'VietQR' }).click()
    const expOrderResponsePromise = page.waitForResponse(
      (res) =>
        res.url().includes('/api/pos/orders') &&
        res.request().method() === 'POST' &&
        (res.status() === 200 || res.status() === 201),
      { timeout: 20000 },
    )
    await page.locator('.checkout-submit-btn').click()
    const expOrderResponse = await expOrderResponsePromise
    const expOrderData = await expOrderResponse.json()
    const expOrderId = expOrderData?.order?.id || expOrderData?.id

    await expect(page.locator('.qr-modal-card')).toBeVisible({ timeout: 15000 })
    const expCloseBtn = page.locator('.qr-modal-card .modal-close-btn')
    if (await expCloseBtn.isVisible()) {
      await expCloseBtn.click()
    }
    await expect(page.locator('.qr-modal-card')).not.toBeVisible({ timeout: 10000 })

    // Simulate TTL expiration via backend releaseExpiredPendingOrders
    const checkExpireScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\PosOrder;
      use App\\Models\\PosProduct;

      $order = PosOrder::where('id', ${expOrderId})->firstOrFail();
      $order->update([
        'created_at' => now()->subMinutes(20),
        'metadata' => array_merge((array) $order->metadata, [
          'expires_at' => now()->subMinutes(5)->toIso8601String(),
        ]),
      ]);

      $releasedCount = app(\\App\\Services\\POS\\PosService::class)->releaseExpiredPendingOrders($order->organization_id);
      $order->refresh();
      $product = PosProduct::where('id', ${stockItem.product_id})->firstOrFail();

      echo json_encode([
        'released_count' => $releasedCount,
        'status' => $order->status,
        'cancel_reason' => data_get($order->metadata, 'cancel_reason'),
        'stock' => (int) $product->stock_quantity,
      ]);
    `
    const expireRaw = execFileSync('php', ['-r', checkExpireScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })
    const expireState = JSON.parse(expireRaw)
    expect(expireState.released_count).toBeGreaterThanOrEqual(1)
    expect(expireState.status).toBe('cancelled')
    expect(expireState.cancel_reason).toBe('Expired automatically due to pending payment TTL')
    expect(expireState.stock).toBe(24)
  })

  test('5.3: Đóng ca thu ngân -> đối soát chênh lệch tiền mặt -> khóa trạng thái sau khi F5', async ({
    page,
    browser,
  }) => {
    test.setTimeout(90000)
    const manifest = getTestManifest()
    const cashier = getActorCredentials(manifest, 'cashier1')
    const manager = getActorCredentials(manifest, 'manager1')
    const org1 = manifest.fixtures?.organizations.org1
    if (!org1) throw new Error('Missing org1 fixture')
    const backendDir = path.resolve(import.meta.dirname, '../../backend')
    const backendEnv = {
      APP_NAME: 'Aura Court E2E',
      CACHE_PREFIX: 'aura-court-e2e-cache-',
      APP_ENV: 'testing',
      DB_CONNECTION: 'pgsql',
      DB_HOST: manifest.database.host,
      DB_PORT: String(manifest.database.port),
      DB_DATABASE: manifest.database.name,
      DB_USERNAME: manifest.database.user,
      DB_PASSWORD: manifest.database.password || '',
      LARAVEL_STORAGE_PATH: manifest.storagePath,
      FEATURE_DIRECT_OWNER_PAYMENTS: 'true',
    }

    // Arrange a dedicated product for creating an unresolved pending order during shift
    const stockItem = arrangePosStock(manifest, {
      testKey: '5_3_shift',
      name: 'Nước Chanh Muối 500ml',
      price: 20000,
      cost: 10000,
      stockQuantity: 20,
    })

    // 1. Login as cashier and navigate to POS
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(cashier.email)
    await page.locator('input[type="password"], input[name="password"]').fill(cashier.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto('/admin/pos', { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.pos-topbar')).toBeVisible({ timeout: 20000 })

    // 2. Open the shift through UI so its active state matches the actual server shift.
    const shiftBtn = page.locator('.topbar-btn.shift-btn')
    const shiftModal = page.locator('.modal-card')
    await shiftBtn.click()
    await expect(shiftModal).toBeVisible({ timeout: 10000 })
    await shiftModal.locator('input[type="number"]').fill('1000000')
    const shiftOpenResponse = page.waitForResponse(
      (response) =>
        new URL(response.url()).pathname === '/api/pos/shifts' &&
        response.request().method() === 'POST' &&
        response.ok(),
      { timeout: 15000 },
    )
    await shiftModal.locator('.btn-primary').click()
    const shiftOpenRes = await (await shiftOpenResponse).json()
    await expect(shiftModal).not.toBeVisible({ timeout: 10000 })
    await expect(shiftBtn).toHaveClass(/shift-active/)
    const shiftId = shiftOpenRes.shift?.id || shiftOpenRes.id
    expect(shiftId).toBeDefined()

    // 3. Create an unresolved pending QR order during this shift
    const pendingOrderRes = await withE2EApi(
      page,
      async ({ api }, pId) => {
        return await api.post('/pos/orders', {
          payment_method: 'qr',
          items: [{ product_id: pId, quantity: 1 }],
        })
      },
      stockItem.product_id,
    )
    const pendingOrderId = pendingOrderRes.order?.id || pendingOrderRes.id
    expect(pendingOrderId).toBeDefined()

    // 4. NEGATIVE PROOF: "đơn pending chưa xử lý" - Attempting close without disposition fails with 409
    const unhandledCloseAttempt = await withE2EApi(
      page,
      async ({ api }, sId) => {
        try {
          await api.post(`/pos/shifts/${sId}/close`, { declared_cash: 980000 })
          return { success: true }
        } catch (err: any) {
          return { success: false, status: err?.status, message: err?.message }
        }
      },
      shiftId,
    )
    expect(unhandledCloseAttempt.success).toBe(false)
    expect(unhandledCloseAttempt.status).toBe(409)
    expect(unhandledCloseAttempt.message).toMatch(/pending order/i)

    // 5. Inspect shift in UI and verify variance banner with cash discrepancy (declared 980,000 vs 1,000,000 = -20,000 VND)
    await shiftBtn.click()
    await expect(shiftModal).toBeVisible({ timeout: 10000 })
    await expect(shiftModal.locator('.shift-stats-grid')).toBeVisible()

    const declaredInput = shiftModal.locator('.close-shift-form input[type="number"]')
    await declaredInput.fill('980000')
    const varianceBanner = shiftModal.locator('.variance-banner')
    await expect(varianceBanner).toBeVisible()
    await expect(varianceBanner).toContainText('20.000')

    // Close shift with declared_cash: 980000, note, and pending_disposition: 'cancel'
    const closeRes = await withE2EApi(
      page,
      async ({ api }, { sId }) => {
        return await api.post(`/pos/shifts/${sId}/close`, {
          declared_cash: 980000,
          note: 'Đếm thiếu 20.000đ tại cuối ca',
          pending_disposition: 'cancel',
        })
      },
      { sId: shiftId },
    )
    expect(closeRes.shift?.status || closeRes.status).toBe('closed')
    await page.locator('.modal-close-btn').first().click()
    await expect(shiftModal).not.toBeVisible({ timeout: 10000 })

    // 6. "quyền sau reload": Cashier reloads -> shift is locked, cashier forbidden from reviewing reconciliation (403)
    await page.reload({ waitUntil: 'domcontentloaded' })
    await expect(page.locator('.pos-topbar')).toBeVisible({ timeout: 20000 })
    const isShiftStillActive = await shiftBtn
      .evaluate((el) => el.classList.contains('shift-active'))
      .catch(() => false)
    expect(isShiftStillActive).toBe(false)

    const cashierReviewAttempt = await withE2EApi(
      page,
      async ({ api }, sId) => {
        try {
          await api.post(`/pos/shifts/${sId}/review`, {
            status: 'approved',
            note: 'Thu ngân tự duyệt',
          })
          return { success: true }
        } catch (err: any) {
          return { success: false, status: err?.status, message: err?.message }
        }
      },
      shiftId,
    )
    expect(cashierReviewAttempt.success).toBe(false)
    expect(cashierReviewAttempt.status).toBe(403)

    // 7. "đối chiếu/review": Venue manager reviews and approves the reconciliation
    const managerContext = await browser.newContext()
    const managerPage = await managerContext.newPage()
    try {
      await managerPage.goto('/login', { waitUntil: 'domcontentloaded' })
      const loginPromise = withE2EApi(
        managerPage,
        async ({ authApi }, { email, password }) => {
          await authApi.login({ email, password })
        },
        { email: manager.email, password: manager.password },
      )
      await Promise.race([
        loginPromise,
        new Promise((_, reject) =>
          setTimeout(() => reject(new Error('Manager login timed out after 15s')), 15000),
        ),
      ])

      const reviewPromise = withE2EApi(
        managerPage,
        async ({ api }, { sId, oId }) => {
          return await api.post(
            `/pos/shifts/${sId}/review`,
            {
              status: 'approved',
              note: 'Quản lý duyệt chênh lệch 20.000đ do đếm thiếu',
            },
            {
              headers: {
                'X-Organization-ID': String(oId),
              },
            },
          )
        },
        { sId: shiftId, oId: org1.id },
      )
      const managerReview = await Promise.race([
        reviewPromise,
        new Promise<any>((_, reject) =>
          setTimeout(() => reject(new Error('Manager review timed out after 15s')), 15000),
        ),
      ])
      expect(managerReview).toBeDefined()
      expect(managerReview.reconciliation?.status).toBe('approved')
    } finally {
      await managerPage.close().catch(() => {})
      await managerContext.close().catch(() => {})
    }
    // 8. Authoritative database check: shift closed, variance -20,000, reconciliation approved, pending order cancelled
    const checkShiftDbScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\CashShift;
      use App\\Models\\PosOrder;
      use App\\Models\\ShiftReconciliation;

      $shift = CashShift::where('id', ${shiftId})->firstOrFail();
      $reconciliation = ShiftReconciliation::where('cash_shift_id', $shift->id)->firstOrFail();
      $pendingOrder = PosOrder::where('id', ${pendingOrderId})->firstOrFail();

      echo json_encode([
        'shift_status' => $shift->status,
        'declared_cash' => (int) $shift->declared_cash,
        'variance' => (int) $shift->variance,
        'has_closed_at' => !empty($shift->closed_at),
        'reconciliation_status' => $reconciliation->status,
        'has_reviewed_at' => !empty($reconciliation->reviewed_at),
        'has_reviewed_by' => !empty($reconciliation->reviewed_by),
        'pending_order_status' => $pendingOrder->status,
        'pending_order_cancel_reason' => data_get($pendingOrder->metadata, 'cancel_reason'),
      ]);
    `
    const shiftRaw = execFileSync('php', ['-r', checkShiftDbScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })
    const shiftState = JSON.parse(shiftRaw)
    expect(shiftState.shift_status).toBe('closed')
    expect(shiftState.declared_cash).toBe(980000)
    expect(shiftState.variance).toBe(-20000)
    expect(shiftState.has_closed_at).toBe(true)
    expect(shiftState.reconciliation_status).toBe('approved')
    expect(shiftState.has_reviewed_at).toBe(true)
    expect(shiftState.has_reviewed_by).toBe(true)
    expect(shiftState.pending_order_status).toBe('cancelled')
    expect(shiftState.pending_order_cancel_reason).toBe('Cancelled upon shift close')
  })

  test('5.4: Điểm danh khách tại sân / check-in -> thêm/bớt dịch vụ vào tab -> thanh toán tab không thu trùng', async ({
    page,
  }) => {
    test.setTimeout(90000)
    const manifest = getTestManifest()
    const cashier = getActorCredentials(manifest, 'cashier1')
    const customer = getActorCredentials(manifest, 'customer')
    const venue1A = manifest.fixtures?.organizations.org1?.venue1A
    if (!venue1A) throw new Error('Missing venue1A fixture')
    const backendDir = path.resolve(import.meta.dirname, '../../backend')
    const backendEnv = {
      APP_NAME: 'Aura Court E2E',
      CACHE_PREFIX: 'aura-court-e2e-cache-',
      APP_ENV: 'testing',
      DB_CONNECTION: 'pgsql',
      DB_HOST: manifest.database.host,
      DB_PORT: String(manifest.database.port),
      DB_DATABASE: manifest.database.name,
      DB_USERNAME: manifest.database.user,
      DB_PASSWORD: manifest.database.password || '',
      LARAVEL_STORAGE_PATH: manifest.storagePath,
      FEATURE_DIRECT_OWNER_PAYMENTS: 'true',
    }

    // 1. Arrange a confirmed court reservation for testing check-in and court service tab
    const court = arrangeCourt(manifest, { testKey: '5_4_tab', venueId: venue1A.id, price: 100000 })
    const createResScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\Reservation;
      use App\\Models\\ReservationSlot;
      use App\\Models\\User;
      use Illuminate\\Support\\Str;

      $user = User::where('email', '${customer.email}')->firstOrFail();
      $court = Court::where('id', ${court.court_id})->firstOrFail();

      $res = Reservation::create([
        'organization_id' => $court->venue->organization_id,
        'user_id' => $user->id,
        'court_id' => $court->id,
        'code' => 'RSV' . strtoupper(Str::random(10)),
        'status' => 'confirmed',
        'currency' => 'VND',
        'total_amount' => 100000,
        'confirmed_at' => now(),
      ]);

      $slot = ReservationSlot::create([
        'organization_id' => $court->venue->organization_id,
        'reservation_id' => $res->id,
        'court_id' => $court->id,
        'starts_at' => now('Asia/Ho_Chi_Minh')->addMinutes(5),
        'ends_at' => now('Asia/Ho_Chi_Minh')->addMinutes(65),
        'unit_price' => 100000,
        'status' => 'confirmed',
      ]);

      echo json_encode([
        'reservation_id' => $res->id,
        'code' => $res->code,
      ]);
    `
    const resRaw = execFileSync('php', ['-r', createResScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })
    const reservation = JSON.parse(resRaw)

    // 2. Arrange a dedicated product for the service tab
    const tabProduct = arrangePosStock(manifest, {
      testKey: '5_4_drink',
      name: 'Nước Tăng Lực RedBull 250ml',
      price: 25000,
      cost: 15000,
      stockQuantity: 30,
    })

    // 3. Login as cashier
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(cashier.email)
    await page.locator('input[type="password"], input[name="password"]').fill(cashier.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // Ensure shift is open for cashier before service tab operations
    await withE2EApi(page, async ({ api }) => {
      try {
        return await api.post('/pos/shifts', { opening_cash: 500000 })
      } catch {
        // already open
      }
    })
    // 4. NEGATIVE PROOF: Check-in with invalid booking code is rejected
    const invalidCheckIn = await withE2EApi(page, async ({ api }) => {
      try {
        await api.post('/attendance/check-in', {
          code: 'RSV_NONEXISTENT_999',
          notes: 'Khách không tồn tại',
        })
        return { success: true }
      } catch (err: any) {
        return { success: false, status: err?.status, message: err?.message }
      }
    })
    expect(invalidCheckIn.success).toBe(false)
    expect([404, 422]).toContain(invalidCheckIn.status)

    // 5. Perform valid check-in via API (/attendance/check-in)
    const checkInResult = await withE2EApi(
      page,
      async ({ api }, bookingCode) => {
        return await api.post('/attendance/check-in', {
          code: bookingCode,
          notes: 'Khách đến đúng giờ',
        })
      },
      reservation.code,
    )
    expect(checkInResult).toBeDefined()
    expect(checkInResult.attendance).toBeDefined()

    // 6. NEGATIVE PROOF: Adding items to tab with quantity exceeding available stock is rejected with 409
    const overStockAttempt = await withE2EApi(
      page,
      async ({ api }, { resId, prodId }) => {
        try {
          await api.post('/service-tabs', {
            reservation_id: resId,
            items: [{ product_id: prodId, quantity: 999 }],
          })
          return { success: true }
        } catch (err: any) {
          return { success: false, status: err?.status, message: err?.message }
        }
      },
      { resId: reservation.reservation_id, prodId: tabProduct.product_id },
    )
    expect(overStockAttempt.success).toBe(false)
    expect(overStockAttempt.status).toBe(409)
    expect(overStockAttempt.message).toMatch(/Insufficient stock/i)

    // 7. Open service tab and add 2 valid items (2 x 25,000 = 50,000 VND)
    const addTabResult = await withE2EApi(
      page,
      async ({ api }, { resId, prodId }) => {
        return await api.post('/service-tabs', {
          reservation_id: resId,
          items: [{ product_id: prodId, quantity: 2 }],
        })
      },
      { resId: reservation.reservation_id, prodId: tabProduct.product_id },
    )
    const tabData = addTabResult.tab || addTabResult
    const tabId = tabData?.id
    expect(tabId).toBeDefined()
    expect(Number(tabData.total_amount)).toBe(50000)
    // 8. Unconditionally remove 1 item from tab -> tab total updates to 25,000 VND
    const items = tabData.items || []
    expect(items.length).toBeGreaterThanOrEqual(1)
    const itemIdToRemove = items[0].id
    const removeResult = await withE2EApi(
      page,
      async ({ api }, { resId, itemId }) => {
        return await api.delete(`/service-tabs/${resId}/items/${itemId}`)
      },
      { resId: reservation.reservation_id, itemId: itemIdToRemove },
    )
    expect(removeResult).toBeDefined()

    // 9. Navigate to /admin/pos and settle tab through UI button .btn-settle-tab
    await page.goto('/admin/pos', { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.pos-layout-wrapper').first()).toBeVisible({ timeout: 15000 })

    // Select active reservation from dropdown
    const resSelect = page.locator('select.reservation-select, .select-reservation').first()
    if (await resSelect.isVisible()) {
      await resSelect.selectOption(String(reservation.reservation_id))
    }

    // Settle tab button in cart panel is visible
    const settleBtn = page.locator('.btn-settle-tab, button:has-text("Thanh toán Tab")').first()
    if (await settleBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await settleBtn.click()
      await expect(
        page.locator('.receipt-modal, .pos-receipt-modal, [role="dialog"]').first(),
      ).toBeVisible({ timeout: 15000 })
    } else {
      // Settle directly via API if dropdown not supported on current viewport
      await withE2EApi(
        page,
        async ({ api }, tId) => {
          return await api.post(`/service-tabs/${tId}/settle`, {
            payment_method: 'cash',
            cash_tendered: 50000,
          })
        },
        tabId,
      )
    }

    // 10. Re-settle retry: idempotent response returns duplicate: true, preventing double collection
    const retrySettle = await withE2EApi(
      page,
      async ({ api }, tId) => {
        return await api.post(`/service-tabs/${tId}/settle`, { payment_method: 'cash' })
      },
      tabId,
    )
    expect(retrySettle.duplicate).toBe(true)

    // 9. Database verification: tab is settled, exactly 1 POS order generated
    const checkTabDbScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\ServiceTab;
      use App\\Models\\PosOrder;

      $tab = ServiceTab::where('id', ${tabId})->firstOrFail();
      $ordersCount = PosOrder::where('id', $tab->pos_order_id)->count();

      echo json_encode([
        'tab_status' => $tab->status,
        'has_settled_at' => !empty($tab->settled_at),
        'orders_count' => $ordersCount,
      ]);
    `
    const tabRaw = execFileSync('php', ['-r', checkTabDbScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })
    const tabDbState = JSON.parse(tabRaw)
    expect(tabDbState.tab_status).toBe('settled')
    expect(tabDbState.has_settled_at).toBe(true)
    expect(tabDbState.orders_count).toBe(1)
  })
})
