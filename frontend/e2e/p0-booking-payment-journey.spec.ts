import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { withE2EApi } from './runner/with-api.ts'
import { test, expect } from '@playwright/test'
import path from 'node:path'
import { execFileSync } from 'node:child_process'
import {
  getTestManifest,
  getFuturePlayDate,
  formatVietnamDate,
  sendSyntheticPaymentWebhook,
  expireHolds,
  arrangeCourt,
  arrangeCoupon,
  grantLoyaltyPoints,
} from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'

test.describe('P0: Hành trình đặt sân, Thanh toán & Vòng đời giao dịch @p0', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  test('4.1: Luồng đặt sân trọn vẹn: Giữ chỗ UI -> QR/người nhận/số tiền -> Webhook test -> Booking xác nhận & trạng thái chuẩn', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const venue1A = manifest.fixtures?.organizations.org1?.venue1A
    if (!venue1A?.routingId) {
      throw new Error('E2E fixture missing: venue1A routingId')
    }
    const court = arrangeCourt(manifest, { testKey: '4_1_journey', venueId: venue1A.id })
    const courtId = court.court_id

    // 1. Authenticate as active customer
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Navigate to court schedule
    await page.goto(`/courts/${courtId}/schedule`, { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.schedule-split-layout, .schedule-view-page').first()).toBeVisible({
      timeout: 20000,
    })

    // 3. Select a future date (card index 1 = tomorrow)
    const dateCards = page.locator('.date-carousel .date-card-btn')
    const count = await dateCards.count()
    expect(count).toBeGreaterThanOrEqual(4)
    const targetCard = dateCards.nth(1)
    await targetCard.waitFor({ state: 'visible', timeout: 15000 })
    await targetCard.click()
    await expect(targetCard).toHaveClass(/active/, { timeout: 10000 })

    // 4. Select an available slot on the chosen date
    const availableSlot = page.locator('.slots-grid .slot-cell-btn.is-available').first()
    await expect(availableSlot).toBeVisible({ timeout: 25000 })
    await availableSlot.click()

    // 5. Checkout button in summary becomes enabled
    const checkoutBtn = page.locator('.schedule-summary-card .btn-checkout')
    await expect(checkoutBtn).toBeEnabled({ timeout: 10000 })

    // 6. Click checkout, listen for the payment attempt creation response
    const paymentResponsePromise = page.waitForResponse(
      (res) =>
        res.url().includes('/payment') &&
        res.request().method() === 'POST' &&
        (res.status() === 200 || res.status() === 201),
      { timeout: 25000 },
    )

    await checkoutBtn.click()

    // 7. Verify navigation to payment page
    await expect(page).toHaveURL(/\/payment\/BK[A-Z0-9_-]+/i, { timeout: 25000 })
    const paymentResponse = await paymentResponsePromise
    const paymentBody = (await paymentResponse.json()) as {
      booking: { code: string; status: string }
      payment_attempt: {
        id: number
        amount: number
        transfer_content: string
        receiver: {
          account_number: string
          bank_code: string
          account_name: string
        }
        status: string
        qr_url: string
      }
    }

    const bookingCode = paymentBody.booking.code
    const attempt = paymentBody.payment_attempt
    expect(bookingCode).toBeTruthy()
    expect(attempt.transfer_content).toBeTruthy()
    expect(attempt.amount).toBeGreaterThan(0)
    expect(attempt.receiver.account_number).toBeTruthy()

    // 8. Verify UI displays accurate payment instructions
    const instructionsPanel = page.locator('[data-testid="owner-payment-instructions"]')
    await expect(instructionsPanel).toBeVisible({ timeout: 20000 })

    // Status starts pending
    await expect(page.locator('[data-testid="owner-payment-status"]')).toBeVisible({
      timeout: 10000,
    })

    // QR code is rendered with valid URL
    const qrImage = page.locator('[data-testid="owner-payment-qr"]')
    await expect(qrImage).toBeVisible({ timeout: 10000 })
    const qrSrc = await qrImage.getAttribute('src')
    expect(qrSrc).toBeTruthy()
    expect(qrSrc).toBe(attempt.qr_url)

    // Receiver account number, bank code, and transfer content match
    await expect(instructionsPanel).toContainText(attempt.receiver.account_number)
    await expect(instructionsPanel).toContainText(attempt.receiver.bank_code)
    await expect(instructionsPanel).toContainText(attempt.transfer_content)

    // 9. Send authenticated synthetic payment webhook matching the exact attempt
    const secret = `whsec_e2e_1a_${manifest.runId}`
    const transactionId = `TXN-41-${Date.now()}`
    const transactionDate = formatVietnamDate(new Date(Date.now() + 10000))

    const webhookRes = await sendSyntheticPaymentWebhook(manifest, {
      routingId: venue1A.routingId,
      secret,
      accountNumber: attempt.receiver.account_number,
      amount: attempt.amount,
      code: attempt.transfer_content,
      referenceCode: attempt.transfer_content,
      transactionId,
      transactionDate,
    })
    expect(webhookRes.statusCode).toBe(200)

    // Status transitions to paid
    const paidBadge = page.locator('[data-testid="owner-payment-paid"]')
    await expect(paidBadge).toBeVisible({ timeout: 15000 })

    // 11. Reload page: confirmed status must persist
    await page.reload({ waitUntil: 'domcontentloaded' })
    await expect(page.locator('[data-testid="owner-payment-paid"]')).toBeVisible({ timeout: 15000 })

    // 12. Query authoritative backend database to prove single booking and single payment allocation
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
      FEATURE_DIRECT_OWNER_PAYMENTS: 'true',
      PILOT_ORGANIZATION_IDS: '*',
    }

    const verifyScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Booking;
      use App\\Models\\Reservation;
      use App\\Models\\LegacyBookingMapping;
      use App\\Models\\OwnerPaymentReceipt;
      use App\\Models\\OwnerPaymentAllocation;

      $booking = Booking::withoutGlobalScope('tenant_isolation')->where('code', '${bookingCode}')->firstOrFail();
      $mapping = LegacyBookingMapping::withoutGlobalScope('tenant_isolation')->where('legacy_booking_id', $booking->id)->firstOrFail();
      $reservation = Reservation::withoutGlobalScope('tenant_isolation')->findOrFail($mapping->reservation_id);
      $receipts = OwnerPaymentReceipt::where('payment_code', '${attempt.transfer_content}')->get();
      $allocations = OwnerPaymentAllocation::where('purpose_id', $reservation->code)->get();

      echo json_encode([
        'booking_status' => $booking->status,
        'reservation_status' => $reservation->status,
        'receipt_count' => $receipts->count(),
        'receipt_status' => $receipts->first()?->status,
        'allocation_count' => $allocations->count(),
      ]);
    `

    const verifyOut = execFileSync('php', ['-r', verifyScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })

    const state = JSON.parse(verifyOut) as {
      booking_status: string
      reservation_status: string
      receipt_count: number
      receipt_status: string
      allocation_count: number
    }

    expect(state.booking_status).toBe('confirmed')
    expect(state.reservation_status).toBe('confirmed')
    expect(state.receipt_count).toBe(1)
    expect(state.receipt_status).toBe('allocated')
    expect(state.allocation_count).toBe(1)
  })

  test('4.2: Bảo mật thanh toán: Client không tự xác nhận, webhook sai tham số báo lỗi, gửi lặp sự kiện đảm bảo idempotent', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const venue1A = manifest.fixtures?.organizations.org1?.venue1A
    if (!venue1A?.routingId) {
      throw new Error('E2E fixture missing: venue1A routingId')
    }
    const court = arrangeCourt(manifest, { testKey: '4_2_security', venueId: venue1A.id })
    const courtId = court.court_id

    // 1. Authenticate as active customer
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Navigate to court schedule and select future date (index 2)
    await page.goto(`/courts/${courtId}/schedule`, { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.schedule-split-layout, .schedule-view-page').first()).toBeVisible({
      timeout: 20000,
    })

    const dateCards = page.locator('.date-carousel .date-card-btn')
    const count = await dateCards.count()
    expect(count).toBeGreaterThanOrEqual(4)
    const targetCard = dateCards.nth(2)
    await targetCard.waitFor({ state: 'visible', timeout: 15000 })
    await targetCard.click()
    await expect(targetCard).toHaveClass(/active/, { timeout: 10000 })

    // 3. Select available slot
    const availableSlot = page.locator('.slots-grid .slot-cell-btn.is-available').first()
    await expect(availableSlot).toBeVisible({ timeout: 25000 })
    await availableSlot.click()

    // 4. Proceed to checkout
    const checkoutBtn = page.locator('.schedule-summary-card .btn-checkout')
    await expect(checkoutBtn).toBeEnabled({ timeout: 10000 })

    const paymentResponsePromise = page.waitForResponse(
      (res) =>
        res.url().includes('/payment') &&
        res.request().method() === 'POST' &&
        (res.status() === 200 || res.status() === 201),
      { timeout: 25000 },
    )
    await checkoutBtn.click()

    await expect(page).toHaveURL(/\/payment\/BK[A-Z0-9_-]+/i, { timeout: 25000 })
    const paymentResponse = await paymentResponsePromise
    const paymentBody = (await paymentResponse.json()) as {
      booking: { code: string; status: string }
      payment_attempt: {
        id: number
        amount: number
        transfer_content: string
        receiver: {
          account_number: string
          bank_code: string
          account_name: string
        }
        status: string
      }
    }

    const bookingCode = paymentBody.booking.code
    const attempt = paymentBody.payment_attempt

    // 5. Verify unilateral client refresh without webhook does NOT confirm booking
    const refreshBtn = page.locator('[data-testid="owner-payment-refresh"]')
    await expect(refreshBtn).toBeVisible({ timeout: 10000 })
    await refreshBtn.click()

    // Paid badge MUST NOT be present
    await expect(page.locator('[data-testid="owner-payment-paid"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="owner-payment-status"]')).toBeVisible()

    const secret = `whsec_e2e_1a_${manifest.runId}`
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
      FEATURE_DIRECT_OWNER_PAYMENTS: 'true',
      PILOT_ORGANIZATION_IDS: '*',
    }

    // 6. Test invalid authentication (tampered HMAC signature): rejected with HTTP 401
    const invalidSigRes = await sendSyntheticPaymentWebhook(manifest, {
      routingId: venue1A.routingId,
      secret,
      accountNumber: attempt.receiver.account_number,
      amount: attempt.amount,
      code: attempt.transfer_content,
      referenceCode: attempt.transfer_content,
      tamperSignature: true,
    })
    expect(invalidSigRes.statusCode).toBe(401)

    // 7. Test mismatched foreign receiver account number
    const wrongAccountRes = await sendSyntheticPaymentWebhook(manifest, {
      routingId: venue1A.routingId,
      secret,
      accountNumber: '9999999999',
      amount: attempt.amount,
      code: attempt.transfer_content,
      referenceCode: attempt.transfer_content,
      transactionDate: formatVietnamDate(new Date(Date.now() + 5000)),
    })
    expect(wrongAccountRes.statusCode).toBe(200)

    // 8. Test partial amount
    const partialAmountRes = await sendSyntheticPaymentWebhook(manifest, {
      routingId: venue1A.routingId,
      secret,
      accountNumber: attempt.receiver.account_number,
      amount: attempt.amount - 10000,
      code: attempt.transfer_content,
      referenceCode: attempt.transfer_content,
      transactionDate: formatVietnamDate(new Date(Date.now() + 6000)),
    })
    expect(partialAmountRes.statusCode).toBe(200)

    // 9. Test unmatched reference code
    const unmatchedCode = `UNMATCHED-${Date.now()}`
    const wrongRefRes = await sendSyntheticPaymentWebhook(manifest, {
      routingId: venue1A.routingId,
      secret,
      accountNumber: attempt.receiver.account_number,
      amount: attempt.amount,
      code: unmatchedCode,
      referenceCode: unmatchedCode,
      transactionDate: formatVietnamDate(new Date(Date.now() + 7000)),
    })
    expect(wrongRefRes.statusCode).toBe(200)

    // Verify DB: booking still pending, exceptions recorded
    const exceptionsScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Booking;
      use App\\Models\\OwnerPaymentReceipt;

      $booking = Booking::withoutGlobalScope('tenant_isolation')->where('code', '${bookingCode}')->firstOrFail();
      $foreignReceipt = OwnerPaymentReceipt::where('payment_code', '${attempt.transfer_content}')->where('exception_code', 'foreign_receiver')->first();
      $partialReceipt = OwnerPaymentReceipt::where('payment_code', '${attempt.transfer_content}')->where('exception_code', 'partial_amount')->first();
      $unmatchedReceipt = OwnerPaymentReceipt::where('payment_code', '${unmatchedCode}')->where('exception_code', 'unmatched_reference')->first();

      echo json_encode([
        'booking_status' => $booking->status,
        'foreign_status' => $foreignReceipt?->status,
        'partial_status' => $partialReceipt?->status,
        'unmatched_status' => $unmatchedReceipt?->status,
      ]);
    `
    const exceptionsOut = execFileSync('php', ['-r', exceptionsScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })
    const exceptionsState = JSON.parse(exceptionsOut) as {
      booking_status: string
      foreign_status: string
      partial_status: string
      unmatched_status: string
    }
    expect(exceptionsState.booking_status).toBe('pending_payment')
    expect(exceptionsState.foreign_status).toBe('exception')
    expect(exceptionsState.partial_status).toBe('exception')
    expect(exceptionsState.unmatched_status).toBe('unmatched')

    // 10. Valid delivery followed by identical replay (duplicate delivery idempotency)
    const validTxnId = `TXN-DUP-42-${Date.now()}`
    const validTxnDate = formatVietnamDate(new Date(Date.now() + 8000))

    // Delivery 1
    const res1 = await sendSyntheticPaymentWebhook(manifest, {
      routingId: venue1A.routingId,
      secret,
      accountNumber: attempt.receiver.account_number,
      amount: attempt.amount,
      code: attempt.transfer_content,
      referenceCode: attempt.transfer_content,
      transactionId: validTxnId,
      transactionDate: validTxnDate,
    })
    expect(res1.statusCode).toBe(200)

    // Delivery 2 (replay)
    const res2 = await sendSyntheticPaymentWebhook(manifest, {
      routingId: venue1A.routingId,
      secret,
      accountNumber: attempt.receiver.account_number,
      amount: attempt.amount,
      code: attempt.transfer_content,
      referenceCode: attempt.transfer_content,
      transactionId: validTxnId,
      transactionDate: validTxnDate,
    })
    expect(res2.statusCode).toBe(200)

    // Automatic payment polling observes the verified receipt.
    await expect(page.locator('[data-testid="owner-payment-paid"]')).toBeVisible({ timeout: 15000 })

    // 11. Verify authoritative state: exactly 1 allocated receipt and 1 allocation for this booking
    const verifyScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Booking;
      use App\\Models\\Reservation;
      use App\\Models\\LegacyBookingMapping;
      use App\\Models\\OwnerPaymentReceipt;
      use App\\Models\\OwnerPaymentAllocation;

      $booking = Booking::withoutGlobalScope('tenant_isolation')->where('code', '${bookingCode}')->firstOrFail();
      $mapping = LegacyBookingMapping::withoutGlobalScope('tenant_isolation')->where('legacy_booking_id', $booking->id)->firstOrFail();
      $reservation = Reservation::withoutGlobalScope('tenant_isolation')->findOrFail($mapping->reservation_id);
      $allocatedReceipts = OwnerPaymentReceipt::where('payment_code', '${attempt.transfer_content}')->where('status', 'allocated')->get();
      $allocations = OwnerPaymentAllocation::where('purpose_id', $reservation->code)->get();

      echo json_encode([
        'booking_status' => $booking->status,
        'allocated_receipt_count' => $allocatedReceipts->count(),
        'allocation_count' => $allocations->count(),
      ]);
    `
    const verifyOut = execFileSync('php', ['-r', verifyScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })
    const finalState = JSON.parse(verifyOut) as {
      booking_status: string
      allocated_receipt_count: number
      allocation_count: number
    }
    expect(finalState.booking_status).toBe('confirmed')
    expect(finalState.allocated_receipt_count).toBe(1)
    expect(finalState.allocation_count).toBe(1)
  })

  test('4.3: Xung đột đồng thời: Hai trình duyệt tranh cùng một slot -> đúng 1 người giữ chỗ thành công, người thua nhận thông báo xung đột', async ({
    browser,
  }) => {
    const manifest = getTestManifest()
    const customer1 = getActorCredentials(manifest, 'customer')
    const customer2 = getActorCredentials(manifest, 'customer2')
    const venue1A = manifest.fixtures?.organizations.org1?.venue1A
    const court = arrangeCourt(manifest, { testKey: '4_3_concurrency', venueId: venue1A?.id })
    const courtId = court.court_id

    // 1. Create two distinct browser contexts and pages
    const context1 = await browser.newContext()
    const context2 = await browser.newContext()
    const page1 = await context1.newPage()
    const page2 = await context2.newPage()

    await page1.addInitScript(() => sessionStorage.setItem('aura_demo_warned', 'true'))
    await page2.addInitScript(() => sessionStorage.setItem('aura_demo_warned', 'true'))

    try {
      // 2. Concurrently authenticate both customers
      await Promise.all([
        (async () => {
          await page1.goto('/login', { waitUntil: 'domcontentloaded' })
          await page1.locator('input[type="email"], input[name="email"]').fill(customer1.email)
          await page1
            .locator('input[type="password"], input[name="password"]')
            .fill(customer1.password)
          await page1.locator('button[type="submit"]').click()
          await expect(page1).not.toHaveURL(/\/login/, { timeout: 15000 })
        })(),
        (async () => {
          await page2.goto('/login', { waitUntil: 'domcontentloaded' })
          await page2.locator('input[type="email"], input[name="email"]').fill(customer2.email)
          await page2
            .locator('input[type="password"], input[name="password"]')
            .fill(customer2.password)
          await page2.locator('button[type="submit"]').click()
          await expect(page2).not.toHaveURL(/\/login/, { timeout: 15000 })
        })(),
      ])

      // 3. Concurrently navigate to same court schedule
      await Promise.all([
        page1.goto(`/courts/${courtId}/schedule`, { waitUntil: 'domcontentloaded' }),
        page2.goto(`/courts/${courtId}/schedule`, { waitUntil: 'domcontentloaded' }),
      ])
      await Promise.all([
        expect(page1.locator('.schedule-split-layout, .schedule-view-page').first()).toBeVisible({
          timeout: 20000,
        }),
        expect(page2.locator('.schedule-split-layout, .schedule-view-page').first()).toBeVisible({
          timeout: 20000,
        }),
      ])

      // 4. Select the same future date (card index 3) on both pages
      const dateCard1 = page1.locator('.date-carousel .date-card-btn').nth(3)
      const dateCard2 = page2.locator('.date-carousel .date-card-btn').nth(3)
      await dateCard1.waitFor({ state: 'visible', timeout: 15000 })
      await dateCard2.waitFor({ state: 'visible', timeout: 15000 })
      await dateCard1.click()
      await dateCard2.click()
      await expect(dateCard1).toHaveClass(/active/, { timeout: 10000 })
      await expect(dateCard2).toHaveClass(/active/, { timeout: 10000 })
      // 5. Locate an available slot and select it in context 1
      const slotBtn1 = page1.locator('.slots-grid .slot-cell-btn.is-available').first()
      await expect(slotBtn1).toBeVisible({ timeout: 25000 })
      const slotTimeText = await slotBtn1.locator('.s-time').textContent()
      expect(slotTimeText).toBeTruthy()
      await slotBtn1.click()

      // 6. Select the EXACT same slot in context 2
      const slotBtn2 = page2
        .locator('.slots-grid .slot-cell-btn')
        .filter({ hasText: slotTimeText?.trim() || '' })
      await expect(slotBtn2).toBeVisible({ timeout: 15000 })
      await slotBtn2.click()

      // 7. Verify checkout buttons are enabled for both
      const checkout1 = page1.locator('.schedule-summary-card .btn-checkout')
      const checkout2 = page2.locator('.schedule-summary-card .btn-checkout')
      await expect(checkout1).toBeEnabled({ timeout: 10000 })
      await expect(checkout2).toBeEnabled({ timeout: 10000 })

      // 8. Fire concurrent checkout submissions for the exact same slot
      await Promise.allSettled([checkout1.click(), checkout2.click()])

      // 9. Evaluate race outcome: exactly ONE context transitions to payment; the other receives conflict error
      const page1PaymentPromise = page1
        .waitForURL(/\/payment\/BK[A-Z0-9_-]+/i, { timeout: 15000 })
        .then(() => true)
        .catch(() => false)
      const page2PaymentPromise = page2
        .waitForURL(/\/payment\/BK[A-Z0-9_-]+/i, { timeout: 15000 })
        .then(() => true)
        .catch(() => false)

      const [page1Won, page2Won] = await Promise.all([page1PaymentPromise, page2PaymentPromise])

      // Invariant: Exactly one winner (XOR)
      expect(page1Won !== page2Won).toBe(true)

      if (page1Won) {
        // Page 1 is on payment page
        await expect(page1.locator('[data-testid="owner-payment-instructions"]')).toBeVisible({
          timeout: 15000,
        })
        // Page 2 stays on schedule and displays conflict error
        await expect(page2).toHaveURL(new RegExp(`/courts/${courtId}/schedule`))
        const errorBox2 = page2.locator('.summary-error-box, .alert-pill-error').first()
        await expect(errorBox2).toBeVisible({ timeout: 15000 })
        await expect(errorBox2).toContainText(
          /người khác|không còn|no longer available|trùng|thay đổi/i,
        )
      } else {
        // Page 2 is on payment page
        await expect(page2.locator('[data-testid="owner-payment-instructions"]')).toBeVisible({
          timeout: 15000,
        })
        // Page 1 stays on schedule and displays conflict error
        await expect(page1).toHaveURL(new RegExp(`/courts/${courtId}/schedule`))
        const errorBox1 = page1.locator('.summary-error-box, .alert-pill-error').first()
        await expect(errorBox1).toBeVisible({ timeout: 15000 })
        await expect(errorBox1).toContainText(
          /người khác|không còn|no longer available|trùng|thay đổi/i,
        )
      }
    } finally {
      await context1.close()
      await context2.close()
    }
  })

  test('4.4: Hết hạn giữ chỗ tự động giải phóng slot và tiền đến muộn không hồi sinh booking', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const venue1A = manifest.fixtures?.organizations.org1?.venue1A
    if (!venue1A?.routingId) {
      throw new Error('E2E fixture missing: venue1A routingId')
    }
    const court = arrangeCourt(manifest, { testKey: '4_4_expiry', venueId: venue1A.id })
    const courtId = court.court_id

    // 1. Authenticate customer
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Navigate to schedule and select future date (card index 4)
    await page.goto(`/courts/${courtId}/schedule`, { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.schedule-split-layout, .schedule-view-page').first()).toBeVisible({
      timeout: 20000,
    })

    const dateCards = page.locator('.date-carousel .date-card-btn')
    const targetCard = dateCards.nth(4)
    await targetCard.waitFor({ state: 'visible', timeout: 15000 })
    await targetCard.click()
    await expect(targetCard).toHaveClass(/active/, { timeout: 10000 })
    // 3. Select an available slot and checkout
    const availableSlot = page.locator('.slots-grid .slot-cell-btn.is-available').first()
    await expect(availableSlot).toBeVisible({ timeout: 25000 })
    await availableSlot.click()

    const checkoutBtn = page.locator('.schedule-summary-card .btn-checkout')
    await expect(checkoutBtn).toBeEnabled({ timeout: 10000 })

    const paymentResponsePromise = page.waitForResponse(
      (res) =>
        res.url().includes('/payment') &&
        res.request().method() === 'POST' &&
        (res.status() === 200 || res.status() === 201),
      { timeout: 25000 },
    )
    await checkoutBtn.click()

    await expect(page).toHaveURL(/\/payment\/BK[A-Z0-9_-]+/i, { timeout: 25000 })
    const paymentResponse = await paymentResponsePromise
    const paymentBody = (await paymentResponse.json()) as {
      booking: { code: string; status: string }
      payment_attempt: {
        id: number
        amount: number
        transfer_content: string
        receiver: {
          account_number: string
          bank_code: string
        }
        expires_at: string
      }
    }

    const bookingCode = paymentBody.booking.code
    const attempt = paymentBody.payment_attempt

    // 4. Force real expiry sweep via test-only CLI
    const expireResult = expireHolds(manifest, { bookingCode, minutes: 45 })
    expect(expireResult.status).toBe('success')
    expect(expireResult.backdated_bookings).toContain(bookingCode)
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
      FEATURE_DIRECT_OWNER_PAYMENTS: 'true',
      PILOT_ORGANIZATION_IDS: '*',
    }

    // 5. Verify backend released the hold: booking status is cancelled
    const checkExpiryScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Booking;
      use App\\Models\\OwnerPaymentAttempt;

      $booking = Booking::withoutGlobalScope('tenant_isolation')->where('code', '${bookingCode}')->firstOrFail();
      $attempt = OwnerPaymentAttempt::where('reference', '${attempt.transfer_content}')->firstOrFail();

      echo json_encode([
        'booking_status' => $booking->status,
        'attempt_status' => $attempt->status,
      ]);
    `
    const expiryOut = execFileSync('php', ['-r', checkExpiryScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })
    const expiryState = JSON.parse(expiryOut) as { booking_status: string; attempt_status: string }
    expect(expiryState.booking_status).toBe('expired')
    expect(expiryState.attempt_status).toBe('expired')

    // 6. Send late payment event (transactionDate > attempt.expires_at)
    const secret = `whsec_e2e_1a_${manifest.runId}`
    const lateTxnId = `TXN-LATE-${Date.now()}`
    const lateDate = formatVietnamDate(new Date(Date.now() + 60000))

    const lateWebhookRes = await sendSyntheticPaymentWebhook(manifest, {
      routingId: venue1A.routingId,
      secret,
      accountNumber: attempt.receiver.account_number,
      amount: attempt.amount,
      code: attempt.transfer_content,
      referenceCode: attempt.transfer_content,
      transactionId: lateTxnId,
      transactionDate: lateDate,
    })
    expect(lateWebhookRes.statusCode).toBe(200)

    // 7. Verify authoritative state: late payment does NOT resurrect booking
    const checkLateStateScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Booking;
      use App\\Models\\Reservation;
      use App\\Models\\LegacyBookingMapping;
      use App\\Models\\OwnerPaymentReceipt;
      use App\\Models\\OwnerPaymentAllocation;
      use App\\Models\\FinanceException;

      $booking = Booking::withoutGlobalScope('tenant_isolation')->where('code', '${bookingCode}')->firstOrFail();
      $mapping = LegacyBookingMapping::withoutGlobalScope('tenant_isolation')->where('legacy_booking_id', $booking->id)->firstOrFail();
      $reservation = Reservation::withoutGlobalScope('tenant_isolation')->findOrFail($mapping->reservation_id);
      $lateReceipt = OwnerPaymentReceipt::where('payment_code', '${attempt.transfer_content}')->first();
      $allocations = OwnerPaymentAllocation::where('purpose_id', $reservation->code)->get();
      $financeException = FinanceException::withoutGlobalScopes()
        ->where('type', 'owner_payment_transfer_after_expiry')
        ->where('reference', 'owner-receipt:' . ($lateReceipt ? $lateReceipt->id : 0))
        ->first();

      echo json_encode([
        'booking_status' => $booking->status,
        'reservation_status' => $reservation->status,
        'receipt_status' => $lateReceipt?->status,
        'receipt_exception' => $lateReceipt?->exception_code,
        'allocation_count' => $allocations->count(),
        'finance_exception_status' => $financeException?->status,
        'finance_exception_severity' => $financeException?->severity,
      ]);
    `
    const lateStateOut = execFileSync('php', ['-r', checkLateStateScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })
    const lateState = JSON.parse(lateStateOut) as {
      booking_status: string
      reservation_status: string
      receipt_status: string
      receipt_exception: string
      allocation_count: number
      finance_exception_status: string
      finance_exception_severity: string
    }

    // Must NOT be resurrected
    expect(lateState.booking_status).toBe('expired')
    expect(lateState.reservation_status).toBe('expired')
    // Receipt marked as exception (transfer_after_expiry)
    expect(lateState.receipt_status).toBe('late')
    expect(lateState.receipt_exception).toBe('transfer_after_expiry')
    // No allocation occurred
    expect(lateState.allocation_count).toBe(0)
    // Open high-severity finance exception recorded for refund obligation
    expect(lateState.finance_exception_status).toBe('open')
    expect(lateState.finance_exception_severity).toBe('high')

    // 8. Verify UI does NOT show paid badge and displays error/expired conflict alert
    await page.reload({ waitUntil: 'domcontentloaded' })
    await expect(page.locator('[data-testid="owner-payment-paid"]')).toHaveCount(0)
    const alertBox = page.locator('.panel-error, [role="alert"], .status-expired').first()
    await expect(alertBox).toBeVisible({ timeout: 10000 })
    await expect(alertBox).toContainText(/thay đổi|xung đột|hết hạn|expired/i)
  })

  test('4.5: Xem trước coupon và điểm tích lũy không trừ trước, giữ nguyên sau F5 và chống tiêu điểm hai lần', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const venue1A = manifest.fixtures?.organizations.org1?.venue1A
    const orgId = manifest.fixtures?.organizations.org1?.id
    if (!venue1A?.routingId || !orgId) {
      throw new Error('E2E fixture missing: venue1A routingId or organizationId')
    }

    // 1. Arrange dedicated court, coupon, and ensure loyalty points balance
    const court = arrangeCourt(manifest, {
      testKey: '4_5_loyalty',
      venueId: venue1A.id,
      price: 100000,
    })
    const courtId = court.court_id
    const customerId = manifest.fixtures?.users.customer?.id
    if (!customerId) {
      throw new Error('E2E fixture missing: customer ID')
    }

    grantLoyaltyPoints(manifest, { userId: customerId, organizationId: orgId, points: 200 })
    const coupon = arrangeCoupon(manifest, {
      testKey: '4_5_cpn',
      discountValue: 20000,
      venueId: venue1A.id,
      organizationId: orgId,
    })

    const backendEnv = {
      APP_ENV: 'testing',
      DB_CONNECTION: 'pgsql',
      DB_HOST: manifest.database.host,
      DB_PORT: String(manifest.database.port),
      DB_DATABASE: manifest.database.name,
      DB_USERNAME: manifest.database.user,
      DB_PASSWORD: manifest.database.password || '',
      LARAVEL_STORAGE_PATH: manifest.storagePath,
      FEATURE_DIRECT_OWNER_PAYMENTS: 'true',
      PILOT_ORGANIZATION_IDS: '*',
    }
    const backendDir = path.resolve(import.meta.dirname, '../../backend')

    const checkPointState = () => {
      const script = `
        require "vendor/autoload.php";
        $app = require_once "bootstrap/app.php";
        $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
        $kernel->bootstrap();

        use App\\Models\\PointLedger;
        use App\\Models\\Coupon;
        use App\\Models\\OrganizationMember;

        OrganizationMember::firstOrCreate(
          ['organization_id' => ${orgId}, 'user_id' => ${customerId}],
          ['role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']
        );

        $points = (int) PointLedger::where('user_id', ${customerId})->where('organization_id', ${orgId})->sum('delta');
        $debitEntries = PointLedger::where('user_id', ${customerId})->where('organization_id', ${orgId})->where('delta', '<', 0)->count();
        $cpn = Coupon::find(${coupon.coupon_id});

        echo json_encode([
          'balance' => $points,
          'debit_entries' => $debitEntries,
          'coupon_usage' => $cpn ? $cpn->usage_count : 0,
        ]);
      `
      const out = execFileSync('php', ['-r', script], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      })
      return JSON.parse(out) as { balance: number; debit_entries: number; coupon_usage: number }
    }

    const initialState = checkPointState()
    expect(initialState.balance).toBeGreaterThanOrEqual(200)
    const initialDebitEntries = initialState.debit_entries

    // 2. Authenticate customer
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })
    // 3. Coupon and Member Preview verification via API (no usage, no consumption)
    const couponPreviewJson = (await withE2EApi(
      page,
      async ({ api }, { code, venueId, courtId, orgId }) => {
        return await api.post(
          '/coupons/preview',
          {
            subtotal: 100000,
            code,
            venue_id: venueId,
            court_id: courtId,
          },
          {
            headers: {
              'X-Organization-ID': String(orgId),
            },
          },
        )
      },
      { code: coupon.code, venueId: venue1A.id, courtId, orgId },
    )) as {
      discount: number
      coupon_discount: number
      membership_discount: number
      coupon?: { code: string }
      membership?: { tier_name?: string } | null
    }
    expect(couponPreviewJson.coupon_discount).toBe(20000)
    expect(couponPreviewJson.discount).toBeGreaterThanOrEqual(20000)

    // Authoritative DB check: previewing coupon did NOT increment usage_count
    expect(checkPointState().coupon_usage).toBe(0)
    expect(checkPointState().debit_entries).toBe(initialDebitEntries)

    // 4. Stale/invalid coupon rejection without side effect
    const invalidCouponStatus = await withE2EApi(
      page,
      async ({ api }, { venueId, orgId }) => {
        try {
          await api.post(
            '/coupons/preview',
            {
              subtotal: 100000,
              code: 'INVALID_NONEXISTENT_COUPON',
              venue_id: venueId,
            },
            {
              headers: {
                'X-Organization-ID': String(orgId),
              },
            },
          )
          return 200
        } catch (err: any) {
          return err.status || 422
        }
      },
      { venueId: venue1A.id, orgId },
    )
    expect(invalidCouponStatus).toBe(422)

    // Below minimum spend preview yields 0 coupon discount
    const belowMinSpendJson = (await withE2EApi(
      page,
      async ({ api }, { code, venueId, orgId }) => {
        return await api.post(
          '/coupons/preview',
          {
            subtotal: 10000,
            code,
            venue_id: venueId,
          },
          {
            headers: {
              'X-Organization-ID': String(orgId),
            },
          },
        )
      },
      { code: coupon.code, venueId: venue1A.id, orgId },
    )) as { coupon_discount: number }
    expect(belowMinSpendJson.coupon_discount).toBe(0)
    expect(checkPointState().coupon_usage).toBe(0)

    // 5. Member status check
    const membershipJson = await withE2EApi(
      page,
      async ({ api }, { orgId }) => {
        return await api.get('/membership', {
          headers: {
            'X-Organization-ID': String(orgId),
          },
        })
      },
      { orgId },
    )
    expect(membershipJson).toBeDefined()

    // 6. Shared entry point controls: HomeView quick-booking dock LoyaltyPointSelector
    await page.goto('/', { waitUntil: 'domcontentloaded' })
    const homeSlot = page.locator('.availability-grid .slot:not(.unavailable)').first()
    if (await homeSlot.isVisible({ timeout: 10000 }).catch(() => false)) {
      await homeSlot.click()
      const dockLoyalty = page.locator('.dock-loyalty-container .loyalty-point-selector')
      await expect(dockLoyalty).toBeVisible({ timeout: 15000 })
      await expect(dockLoyalty.locator('.loyalty-balance-badge')).toContainText(
        new RegExp(String(initialState.balance)),
      )
      // Clear home selection to dismiss dock
      await page.locator('.dock-btn-clear').click()
    }

    // 7. Navigate to schedule and select future date
    await page.goto(`/courts/${courtId}/schedule`, { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.schedule-split-layout, .schedule-view-page').first()).toBeVisible({
      timeout: 20000,
    })

    const dateCards = page.locator('.date-carousel .date-card-btn')
    const targetCard = dateCards.nth(1)
    await targetCard.waitFor({ state: 'visible', timeout: 15000 })
    await targetCard.click()
    await expect(targetCard).toHaveClass(/active/, { timeout: 10000 })

    // 8. Select an available slot (100,000 VND)
    const availableSlot = page.locator('.slots-grid .slot-cell-btn.is-available').first()
    await expect(availableSlot).toBeVisible({ timeout: 25000 })
    await availableSlot.click()
    // 5. Verify LoyaltyPointSelector controls and preview without debiting
    const loyaltyBox = page.locator('.loyalty-point-selector')
    await expect(loyaltyBox).toBeVisible({ timeout: 15000 })
    await expect(loyaltyBox.locator('.loyalty-balance-badge')).toContainText(
      new RegExp(String(initialState.balance)),
    )

    const pointsInput = loyaltyBox.locator('.loyalty-input')
    await pointsInput.fill('50')
    await pointsInput.press('Enter')

    // Preview breakdown renders discount and remaining payable
    await expect(loyaltyBox.locator('.loyalty-breakdown')).toBeVisible({ timeout: 10000 })
    await expect(loyaltyBox.locator('.loyalty-breakdown .discount-value')).toContainText(/50\.000/i)
    await expect(loyaltyBox.locator('.loyalty-breakdown .remaining-value')).toContainText(
      /50\.000/i,
    )

    // Test clear action
    const clearBtn = loyaltyBox.locator('.btn-quick.clear')
    await expect(clearBtn).toBeVisible({ timeout: 10000 })
    await clearBtn.click()
    await expect(loyaltyBox.locator('.loyalty-breakdown')).toHaveCount(0)

    // Re-apply 50 points
    await pointsInput.fill('50')
    await pointsInput.press('Enter')
    await expect(loyaltyBox.locator('.loyalty-breakdown')).toBeVisible({ timeout: 10000 })

    // Authoritative check: Preview stage has NOT debited PointLedger or incremented coupon usage
    const previewState = checkPointState()
    expect(previewState.debit_entries).toBe(initialDebitEntries)
    expect(previewState.coupon_usage).toBe(0)

    // 6. Proceed to checkout and verify snapshot persistence
    const checkoutBtn = page.locator('.schedule-summary-card .btn-checkout')
    await expect(checkoutBtn).toBeEnabled({ timeout: 10000 })
    await checkoutBtn.click()

    await expect(page).toHaveURL(/\/payment\/BK[A-Z0-9_-]+/i, { timeout: 20000 })
    const bookingCode = page.url().split('/payment/')[1]?.split('?')[0]
    expect(bookingCode).toBeTruthy()
    await expect(page.locator('[data-testid="owner-payment-instructions"]')).toBeVisible({
      timeout: 25000,
    })
    // Payment view reflects discounted payable (50,000 VND instead of 100,000 VND)
    await expect(page.locator('body')).toContainText(/50\.000/i)
    // Authoritative check: Hold creation debited points in PointLedger and recorded loyalty snapshot
    const postHoldStateScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Booking;
      use App\\Models\\PointLedger;

      $booking = Booking::withoutGlobalScope('tenant_isolation')->where('code', '${bookingCode}')->firstOrFail();
      $metadata = $booking->metadata ?: [];
      $loyaltySnapshot = $metadata['loyalty_snapshot'] ?? $booking->policy_snapshot['loyalty_snapshot'] ?? null;
      $debit = PointLedger::where('user_id', ${customerId})->where('organization_id', ${orgId})->where('delta', -50)->first();

      echo json_encode([
        'total_amount' => (int) $booking->total_amount,
        'loyalty_discount_amount' => (int) $booking->loyalty_discount_amount,
        'loyalty_points_redeemed' => (int) $booking->loyalty_points_redeemed,
        'has_loyalty_snapshot' => !empty($loyaltySnapshot),
        'points_spent_in_snapshot' => $loyaltySnapshot['points_spent'] ?? null,
        'has_debit_ledger' => $debit !== null,
      ]);
    `
    const holdOut = execFileSync('php', ['-r', postHoldStateScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })
    const holdState = JSON.parse(holdOut) as {
      total_amount: number
      loyalty_discount_amount: number
      loyalty_points_redeemed: number
      has_loyalty_snapshot: boolean
      points_spent_in_snapshot: number | null
      has_debit_ledger: boolean
    }

    expect(holdState.total_amount).toBe(50000)
    expect(holdState.loyalty_discount_amount).toBe(50000)
    expect(holdState.loyalty_points_redeemed).toBe(50)
    expect(holdState.has_loyalty_snapshot).toBe(true)
    expect(holdState.points_spent_in_snapshot).toBe(50)
    expect(holdState.has_debit_ledger).toBe(true)

    // 7. Verify snapshot persistence after reload
    await page.reload({ waitUntil: 'domcontentloaded' })
    await expect(page.locator('[data-testid="owner-payment-instructions"]')).toBeVisible({
      timeout: 15000,
    })
    await expect(page.locator('body')).toContainText(/50\.000/i)

    // 8. Protection against double consumption on invalid points or stale policy
    const future = getFuturePlayDate(4, 15)
    const invalidBookingScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Domain\\Booking\\DTOs\\CreateBookingData;
      use App\\Domain\\Booking\\Actions\\CreateBooking;
      use Illuminate\\Validation\\ValidationException;

      $caught = false;
      $errorMsg = '';
      try {
        $dto = new CreateBookingData(
          userId: ${customerId},
          courtId: ${courtId},
          slots: ['${future.slotTime}'],
          couponCode: null,
          loyaltyPoints: 999999,
          loyaltyPolicyVersion: 999,
          idempotencyKey: 'inv_test_' . uniqid(),
        );
        app(CreateBooking::class)->execute($dto);
      } catch (ValidationException $e) {
        $caught = true;
        $errorMsg = json_encode($e->errors(), JSON_UNESCAPED_UNICODE);
      } catch (\\Throwable $t) {
        $caught = true;
        $errorMsg = $t->getMessage();
      }

      echo json_encode(['caught' => $caught, 'error' => $errorMsg]);
    `
    const invOut = execFileSync('php', ['-r', invalidBookingScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })
    const invResult = JSON.parse(invOut) as { caught: boolean; error: string }
    expect(invResult.caught).toBe(true)
    expect(invResult.error).toMatch(
      /loyalty_policy_version|loyalty_points|Chính sách đổi điểm|Số dư điểm|thay đổi|không đủ/i,
    )
    // Authoritative check: Debit entries count has NOT increased (no point leakage or double consumption)
    const afterInvalidState = checkPointState()
    expect(afterInvalidState.debit_entries).toBe(initialDebitEntries + 1)

    // 9. Booking creation with valid coupon applies discount and increments coupon usage
    const futureCoupon = getFuturePlayDate(5, 11)
    const couponBookingScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Domain\\Booking\\DTOs\\CreateBookingData;
      use App\\Domain\\Booking\\Actions\\CreateBooking;
      use App\\Models\\Coupon;

      $dto = new CreateBookingData(
        userId: ${customerId},
        courtId: ${courtId},
        slots: ['${futureCoupon.slotTime}'],
        couponCode: '${coupon.code}',
        loyaltyPoints: null,
        loyaltyPolicyVersion: null,
        idempotencyKey: 'cpn_test_' . uniqid(),
      );
      $bkg = app(CreateBooking::class)->execute($dto);
      $cpn = Coupon::find(${coupon.coupon_id});

      echo json_encode([
        'coupon_discount' => (int) $bkg->discount_amount,
        'coupon_usage' => $cpn ? $cpn->usage_count : 0,
      ]);
    `
    const cpnBookingOut = execFileSync('php', ['-r', couponBookingScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })
    const cpnResult = JSON.parse(cpnBookingOut) as { coupon_discount: number; coupon_usage: number }
    expect(cpnResult.coupon_discount).toBe(20000)
    expect(cpnResult.coupon_usage).toBe(1)
  })
})
