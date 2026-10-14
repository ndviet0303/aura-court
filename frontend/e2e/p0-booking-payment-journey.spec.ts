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

    // 7. Verify navigation to the reservation, whose payment modal starts the attempt
    await expect(page).toHaveURL(/\/reservations\/RSV[A-Z0-9]+/, { timeout: 25000 })
    const paymentResponse = await paymentResponsePromise
    const paymentBody = (await paymentResponse.json()) as {
      reservation: { code: string; status: string }
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

    const reservationCode = paymentBody.reservation.code
    const attempt = paymentBody.payment_attempt
    expect(page.url()).toContain(`/reservations/${reservationCode}`)
    expect(paymentBody.reservation.status).toBe('held')
    expect(attempt.transfer_content).toBeTruthy()
    expect(attempt.amount).toBeGreaterThan(0)
    expect(attempt.receiver.account_number).toBeTruthy()

    // 8. Verify UI displays accurate payment instructions
    const instructionsPanel = page.locator('[data-testid="reservation-payment-modal"]')
    await expect(instructionsPanel).toBeVisible({ timeout: 20000 })

    // Status starts pending
    const paymentStatus = page.locator('[data-testid="reservation-payment-status"]')
    await expect(paymentStatus).toHaveClass(/status-pending/, { timeout: 10000 })

    // QR code is rendered with valid URL
    const qrImage = page.locator('[data-testid="reservation-payment-qr"]')
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

    // Status transitions to paid (the modal polls the payment every 5 seconds)
    await expect(paymentStatus).toHaveClass(/status-paid/, { timeout: 15000 })

    // 11. Reload page: the confirmed reservation opens without a payment modal
    await page.reload({ waitUntil: 'domcontentloaded' })
    await expect(page.locator('.status-badge.confirmed')).toBeVisible({ timeout: 15000 })
    await expect(page.locator('[data-testid="reservation-payment-modal"]')).toHaveCount(0)

    // 11b. A paid booking is final: /booking offers resale only, never cancel, refund or reschedule
    await page.goto('/booking', { waitUntil: 'domcontentloaded' })
    const paidCard = page.locator('.user-booking-card').filter({ hasText: `#${reservationCode}` })
    await expect(paidCard).toBeVisible({ timeout: 15000 })
    await expect(paidCard.locator('[data-testid="booking-resale-btn"]')).toHaveAttribute(
      'href',
      '/resale',
    )
    await expect(paidCard.locator('[data-testid="booking-cancel-btn"]')).toHaveCount(0)
    await expect(paidCard.locator('[data-testid="booking-reschedule-btn"]')).toHaveCount(0)
    await expect(paidCard.getByRole('button', { name: /hủy|huỷ|hoàn tiền|đổi lịch/i })).toHaveCount(
      0,
    )

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
    }

    const verifyScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Reservation;
      use App\\Models\\LegacyBookingMapping;
      use App\\Models\\OwnerPaymentReceipt;
      use App\\Models\\OwnerPaymentAllocation;

      $reservation = Reservation::withoutGlobalScope('tenant_isolation')->where('code', '${reservationCode}')->firstOrFail();
      $receipts = OwnerPaymentReceipt::where('payment_code', '${attempt.transfer_content}')->get();
      $allocations = OwnerPaymentAllocation::where('purpose_id', $reservation->code)->get();

      echo json_encode([
        'legacy_mapping_count' => LegacyBookingMapping::withoutGlobalScope('tenant_isolation')->where('reservation_id', $reservation->id)->count(),
        'payment_model' => $reservation->payment_model,
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
      legacy_mapping_count: number
      payment_model: string
      reservation_status: string
      receipt_count: number
      receipt_status: string
      allocation_count: number
    }

    // A native reservation: no V1 booking behind it
    expect(state.legacy_mapping_count).toBe(0)
    expect(state.payment_model).toBe('direct_owner_v1')
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

    await expect(page).toHaveURL(/\/reservations\/RSV[A-Z0-9]+/, { timeout: 25000 })
    const paymentResponse = await paymentResponsePromise
    const paymentBody = (await paymentResponse.json()) as {
      reservation: { code: string; status: string }
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

    const reservationCode = paymentBody.reservation.code
    const attempt = paymentBody.payment_attempt

    // 5. Verify unilateral client refresh without webhook does NOT confirm the reservation
    const refreshBtn = page.locator('[data-testid="refresh-payment-status"]')
    await expect(refreshBtn).toBeVisible({ timeout: 10000 })
    const refreshResponse = page.waitForResponse(
      (res) =>
        res.url().includes(`/reservations/${reservationCode}/payment`) &&
        res.request().method() === 'GET',
    )
    await refreshBtn.click()
    await refreshResponse

    // Paid status MUST NOT be shown
    const paymentStatus = page.locator('[data-testid="reservation-payment-status"]')
    await expect(paymentStatus).toHaveClass(/status-pending/)
    await expect(paymentStatus).not.toHaveClass(/status-paid/)

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
    }

    // 6. Test invalid authentication (wrong Api_Key): rejected with HTTP 401
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

    // Verify DB: reservation still held, exceptions recorded
    const exceptionsScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Reservation;
      use App\\Models\\OwnerPaymentReceipt;

      $reservation = Reservation::withoutGlobalScope('tenant_isolation')->where('code', '${reservationCode}')->firstOrFail();
      $foreignReceipt = OwnerPaymentReceipt::where('payment_code', '${attempt.transfer_content}')->where('exception_code', 'foreign_receiver')->first();
      $partialReceipt = OwnerPaymentReceipt::where('payment_code', '${attempt.transfer_content}')->where('exception_code', 'partial_amount')->first();
      $unmatchedReceipt = OwnerPaymentReceipt::where('payment_code', '${unmatchedCode}')->where('exception_code', 'unmatched_reference')->first();

      echo json_encode([
        'reservation_status' => $reservation->status,
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
      reservation_status: string
      foreign_status: string
      partial_status: string
      unmatched_status: string
    }
    expect(exceptionsState.reservation_status).toBe('held')
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
    await expect(paymentStatus).toHaveClass(/status-paid/, { timeout: 15000 })

    // 11. Verify authoritative state: exactly 1 allocated receipt and 1 allocation for this reservation
    const verifyScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Reservation;
      use App\\Models\\OwnerPaymentReceipt;
      use App\\Models\\OwnerPaymentAllocation;

      $reservation = Reservation::withoutGlobalScope('tenant_isolation')->where('code', '${reservationCode}')->firstOrFail();
      $allocatedReceipts = OwnerPaymentReceipt::where('payment_code', '${attempt.transfer_content}')->where('status', 'allocated')->get();
      $allocations = OwnerPaymentAllocation::where('purpose_id', $reservation->code)->get();

      echo json_encode([
        'reservation_status' => $reservation->status,
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
      reservation_status: string
      allocated_receipt_count: number
      allocation_count: number
    }
    expect(finalState.reservation_status).toBe('confirmed')
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
        .waitForURL(/\/reservations\/RSV[A-Z0-9]+/, { timeout: 15000 })
        .then(() => true)
        .catch(() => false)
      const page2PaymentPromise = page2
        .waitForURL(/\/reservations\/RSV[A-Z0-9]+/, { timeout: 15000 })
        .then(() => true)
        .catch(() => false)

      const [page1Won, page2Won] = await Promise.all([page1PaymentPromise, page2PaymentPromise])

      // Invariant: Exactly one winner (XOR)
      expect(page1Won !== page2Won).toBe(true)

      if (page1Won) {
        // Page 1 is on its reservation with the payment modal open
        await expect(page1.locator('[data-testid="reservation-payment-modal"]')).toBeVisible({
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
        // Page 2 is on its reservation with the payment modal open
        await expect(page2.locator('[data-testid="reservation-payment-modal"]')).toBeVisible({
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

    await expect(page).toHaveURL(/\/reservations\/RSV[A-Z0-9]+/, { timeout: 25000 })
    const paymentResponse = await paymentResponsePromise
    const paymentBody = (await paymentResponse.json()) as {
      reservation: { code: string; status: string }
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

    const reservationCode = paymentBody.reservation.code
    const attempt = paymentBody.payment_attempt

    // 4. Force real expiry sweep via test-only CLI
    const expireResult = expireHolds(manifest, { reservationCode, minutes: 45 })
    expect(expireResult.status).toBe('success')
    expect(expireResult.backdated_reservations).toContain(reservationCode)
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
    }

    // 5. Verify backend released the hold: reservation status is expired
    const checkExpiryScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Reservation;
      use App\\Models\\OwnerPaymentAttempt;

      $reservation = Reservation::withoutGlobalScope('tenant_isolation')->where('code', '${reservationCode}')->firstOrFail();
      $attempt = OwnerPaymentAttempt::where('reference', '${attempt.transfer_content}')->firstOrFail();

      echo json_encode([
        'reservation_status' => $reservation->status,
        'attempt_status' => $attempt->status,
      ]);
    `
    const expiryOut = execFileSync('php', ['-r', checkExpiryScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })
    const expiryState = JSON.parse(expiryOut) as {
      reservation_status: string
      attempt_status: string
    }
    expect(expiryState.reservation_status).toBe('expired')
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

    // 7. Verify authoritative state: late payment does NOT resurrect the reservation
    const checkLateStateScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Reservation;
      use App\\Models\\OwnerPaymentReceipt;
      use App\\Models\\OwnerPaymentAllocation;
      use App\\Models\\FinanceException;

      $reservation = Reservation::withoutGlobalScope('tenant_isolation')->where('code', '${reservationCode}')->firstOrFail();
      $lateReceipt = OwnerPaymentReceipt::where('payment_code', '${attempt.transfer_content}')->first();
      $allocations = OwnerPaymentAllocation::where('purpose_id', $reservation->code)->get();
      $financeException = FinanceException::withoutGlobalScopes()
        ->where('type', 'owner_payment_transfer_after_expiry')
        ->where('reference', 'owner-receipt:' . ($lateReceipt ? $lateReceipt->id : 0))
        ->first();

      echo json_encode([
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
      reservation_status: string
      receipt_status: string
      receipt_exception: string
      allocation_count: number
      finance_exception_status: string
      finance_exception_severity: string
    }

    // Must NOT be resurrected
    expect(lateState.reservation_status).toBe('expired')
    // Receipt marked as exception (transfer_after_expiry)
    expect(lateState.receipt_status).toBe('late')
    expect(lateState.receipt_exception).toBe('transfer_after_expiry')
    // No allocation occurred
    expect(lateState.allocation_count).toBe(0)
    // Open high-severity finance exception: the owner settles the late money outside the platform
    expect(lateState.finance_exception_status).toBe('open')
    expect(lateState.finance_exception_severity).toBe('high')

    // 8. Verify UI shows the reservation as expired and offers no payment
    await page.reload({ waitUntil: 'domcontentloaded' })
    const expiredBadge = page.locator('.status-badge.expired')
    await expect(expiredBadge).toBeVisible({ timeout: 10000 })
    await expect(expiredBadge).toContainText(/hết hạn|expired/i)
    await expect(page.locator('[data-testid="reservation-payment-modal"]')).toHaveCount(0)
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

    await expect(page).toHaveURL(/\/reservations\/RSV[A-Z0-9]+/, { timeout: 20000 })
    const reservationCode = page.url().split('/reservations/')[1]?.split('?')[0]
    expect(reservationCode).toBeTruthy()
    const paymentModal = page.locator('[data-testid="reservation-payment-modal"]')
    await expect(paymentModal.locator('[data-testid="reservation-payment-qr"]')).toBeVisible({
      timeout: 25000,
    })
    // Payment modal asks for the discounted payable (50,000 VND instead of 100,000 VND)
    await expect(paymentModal.locator('.payment-details')).toContainText(/50\.000/i)
    // Authoritative check: checkout debited points in PointLedger and recorded the loyalty snapshot
    const postHoldStateScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\PointLedger;
      use App\\Models\\Reservation;

      $reservation = Reservation::withoutGlobalScope('tenant_isolation')->where('code', '${reservationCode}')->firstOrFail();
      $metadata = $reservation->metadata ?: [];
      $loyaltySnapshot = $metadata['loyalty_snapshot'] ?? null;
      $debit = PointLedger::where('user_id', ${customerId})->where('organization_id', ${orgId})
        ->where('source_type', 'reservation')->where('source_id', $reservation->id)->where('delta', -50)->first();

      echo json_encode([
        'status' => $reservation->status,
        'total_amount' => (int) $reservation->total_amount,
        'loyalty_discount_amount' => (int) ($metadata['loyalty_discount_amount'] ?? 0),
        'loyalty_points_redeemed' => (int) ($metadata['loyalty_points_redeemed'] ?? 0),
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
      status: string
      total_amount: number
      loyalty_discount_amount: number
      loyalty_points_redeemed: number
      has_loyalty_snapshot: boolean
      points_spent_in_snapshot: number | null
      has_debit_ledger: boolean
    }

    expect(holdState.status).toBe('held')
    expect(holdState.total_amount).toBe(50000)
    expect(holdState.loyalty_discount_amount).toBe(50000)
    expect(holdState.loyalty_points_redeemed).toBe(50)
    expect(holdState.has_loyalty_snapshot).toBe(true)
    expect(holdState.points_spent_in_snapshot).toBe(50)
    expect(holdState.has_debit_ledger).toBe(true)

    // 7. Verify snapshot persistence after reload: the held reservation reopens its payment
    await page.reload({ waitUntil: 'domcontentloaded' })
    await expect(paymentModal.locator('[data-testid="reservation-payment-qr"]')).toBeVisible({
      timeout: 15000,
    })
    await expect(paymentModal.locator('.payment-details')).toContainText(/50\.000/i)

    // 8. Protection against double consumption on invalid points or stale policy
    const future = getFuturePlayDate(4, 15)
    const invalidBookingScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\User;
      use App\\Services\\Booking\\OrdinaryCheckoutService;
      use Illuminate\\Validation\\ValidationException;

      $caught = false;
      $errorMsg = '';
      try {
        app(OrdinaryCheckoutService::class)->checkout(User::findOrFail(${customerId}), ${orgId}, [
          'court_id' => ${courtId},
          'slots' => ['${future.slotTime}'],
          'loyalty_points' => 999999,
          'loyalty_policy_version' => 999,
        ]);
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

    // 9. Reservation checkout with valid coupon applies discount and increments coupon usage
    const futureCoupon = getFuturePlayDate(5, 11)
    const couponBookingScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Coupon;
      use App\\Models\\User;
      use App\\Services\\Booking\\OrdinaryCheckoutService;

      $reservation = app(OrdinaryCheckoutService::class)->checkout(User::findOrFail(${customerId}), ${orgId}, [
        'court_id' => ${courtId},
        'slots' => ['${futureCoupon.slotTime}'],
        'coupon_code' => '${coupon.code}',
      ]);
      $cpn = Coupon::find(${coupon.coupon_id});

      echo json_encode([
        'coupon_discount' => (int) data_get($reservation->metadata, 'benefit_snapshot.coupon_discount_amount', 0),
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

  test('4.5.2: Nhập mã giảm giá ở lịch sân -> checkout reservation giữ lượt coupon và QR đúng số tiền đã giảm', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const venue1A = manifest.fixtures?.organizations.org1?.venue1A
    const orgId = manifest.fixtures?.organizations.org1?.id
    if (!venue1A || !orgId) {
      throw new Error('E2E fixture missing: venue1A or organizationId')
    }

    const court = arrangeCourt(manifest, {
      testKey: '4_5_2_coupon',
      venueId: venue1A.id,
      price: 100000,
    })
    const coupon = arrangeCoupon(manifest, {
      testKey: '4_5_2_cpn',
      discountValue: 20000,
      venueId: venue1A.id,
      organizationId: orgId,
    })
    const backendDir = path.resolve(import.meta.dirname, '../../backend')

    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto(`/courts/${court.court_id}/schedule`, { waitUntil: 'domcontentloaded' })
    const targetCard = page.locator('.date-carousel .date-card-btn').nth(1)
    await targetCard.waitFor({ state: 'visible', timeout: 20000 })
    await targetCard.click()
    await expect(targetCard).toHaveClass(/active/, { timeout: 10000 })
    const availableSlot = page.locator('.slots-grid .slot-cell-btn.is-available').first()
    await expect(availableSlot).toBeVisible({ timeout: 25000 })
    await availableSlot.click()

    // The quote prices the selection with the code; nothing is reserved yet.
    const summary = page.locator('.schedule-summary-card')
    await summary.locator('.coupon-text-input').fill(coupon.code)
    await summary.locator('.btn-apply-coupon').click()
    await expect(summary.locator('.coupon-discount')).toContainText(/20\.000/, { timeout: 15000 })
    await expect(summary.locator('.total-num')).toContainText(/80\.000/)

    await summary.locator('.btn-checkout').click()
    await expect(page).toHaveURL(/\/reservations\/RSV[A-Z0-9]+/, { timeout: 20000 })
    const reservationCode = page.url().split('/reservations/')[1]?.split('?')[0]
    const paymentModal = page.locator('[data-testid="reservation-payment-modal"]')
    await expect(paymentModal.locator('[data-testid="reservation-payment-qr"]')).toBeVisible({
      timeout: 25000,
    })
    await expect(paymentModal.locator('.payment-details')).toContainText(/80\.000/)

    const stateScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();

      use App\\Models\\Coupon;
      use App\\Models\\Reservation;

      $reservation = Reservation::withoutGlobalScope('tenant_isolation')->where('code', '${reservationCode}')->firstOrFail();
      echo json_encode([
        'status' => $reservation->status,
        'total_amount' => (int) $reservation->total_amount,
        'coupon_discount' => (int) data_get($reservation->metadata, 'benefit_snapshot.coupon_discount_amount', 0),
        'coupon_usage_id' => data_get($reservation->metadata, 'coupon_usage.coupon_id'),
        'usage_count' => (int) Coupon::findOrFail(${coupon.coupon_id})->usage_count,
      ]);
    `
    const state = JSON.parse(
      execFileSync('php', ['-r', stateScript], {
        cwd: backendDir,
        env: getCanonicalBackendEnv(manifest),
        encoding: 'utf-8',
      }),
    ) as {
      status: string
      total_amount: number
      coupon_discount: number
      coupon_usage_id: number | null
      usage_count: number
    }
    expect(state.status).toBe('held')
    expect(state.total_amount).toBe(80000)
    expect(state.coupon_discount).toBe(20000)
    expect(state.coupon_usage_id).toBe(coupon.coupon_id)
    expect(state.usage_count).toBe(1)
  })

  test('4.5.1: Điểm tích lũy phủ toàn bộ giá -> đặt sân xác nhận ngay, không mở thanh toán (zero-payable)', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const venue1A = manifest.fixtures?.organizations.org1?.venue1A
    const orgId = manifest.fixtures?.organizations.org1?.id
    const customerId = manifest.fixtures?.users.customer?.id
    if (!venue1A || !orgId || !customerId) {
      throw new Error('E2E fixture missing: venue1A, organizationId or customer ID')
    }

    // 1. A 100,000 VND court and 100 points (1 point = 1,000 VND) cover the whole price
    const court = arrangeCourt(manifest, {
      testKey: '4_5_zero',
      venueId: venue1A.id,
      price: 100000,
    })
    grantLoyaltyPoints(manifest, { userId: customerId, organizationId: orgId, points: 100 })

    // 2. Authenticate customer and pick a slot
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto(`/courts/${court.court_id}/schedule`, { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.schedule-split-layout, .schedule-view-page').first()).toBeVisible({
      timeout: 20000,
    })
    const targetCard = page.locator('.date-carousel .date-card-btn').nth(2)
    await targetCard.waitFor({ state: 'visible', timeout: 15000 })
    await targetCard.click()
    await expect(targetCard).toHaveClass(/active/, { timeout: 10000 })
    const availableSlot = page.locator('.slots-grid .slot-cell-btn.is-available').first()
    await expect(availableSlot).toBeVisible({ timeout: 25000 })
    await availableSlot.click()

    // 3. Redeem 100 points: the preview shows the full 100,000 VND discount
    const loyaltyBox = page.locator('.loyalty-point-selector')
    await expect(loyaltyBox).toBeVisible({ timeout: 15000 })
    const pointsInput = loyaltyBox.locator('.loyalty-input')
    await pointsInput.fill('100')
    await pointsInput.press('Enter')
    await expect(loyaltyBox.locator('.loyalty-breakdown .discount-value')).toContainText(
      /100\.000/i,
      { timeout: 10000 },
    )

    // 4. Checkout confirms at once: the reservation opens confirmed, with no payment modal
    const checkoutBtn = page.locator('.schedule-summary-card .btn-checkout')
    await expect(checkoutBtn).toBeEnabled({ timeout: 10000 })
    await checkoutBtn.click()
    await expect(page).toHaveURL(/\/reservations\/RSV[A-Z0-9]+/, { timeout: 20000 })
    const reservationCode = page.url().split('/reservations/')[1]?.split('?')[0]
    await expect(page.locator('.status-badge.confirmed')).toBeVisible({ timeout: 15000 })
    await expect(page.locator('[data-testid="reservation-payment-modal"]')).toHaveCount(0)

    // 5. Authoritative check: confirmed, nothing payable, zero_payable recorded, no payment attempt
    const state = JSON.parse(
      execFileSync(
        'php',
        [
          '-r',
          `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\OwnerPaymentAttempt;
      use App\\Models\\PointLedger;
      use App\\Models\\Reservation;

      $reservation = Reservation::withoutGlobalScope('tenant_isolation')->where('code', '${reservationCode}')->firstOrFail();
      echo json_encode([
        'status' => $reservation->status,
        'payment_model' => $reservation->payment_model,
        'total_amount' => (int) $reservation->total_amount,
        'zero_payable' => $reservation->metadata['zero_payable'] ?? null,
        'loyalty_points_redeemed' => (int) ($reservation->metadata['loyalty_points_redeemed'] ?? 0),
        'attempt_count' => OwnerPaymentAttempt::where('purpose', 'booking')->where('purpose_id', $reservation->code)->count(),
        'debit_count' => PointLedger::where('source_type', 'reservation')->where('source_id', $reservation->id)->where('delta', -100)->count(),
      ]);
    `,
        ],
        {
          cwd: path.resolve(import.meta.dirname, '../../backend'),
          env: getCanonicalBackendEnv(manifest),
          encoding: 'utf-8',
        },
      ),
    ) as {
      status: string
      payment_model: string
      total_amount: number
      zero_payable: boolean | null
      loyalty_points_redeemed: number
      attempt_count: number
      debit_count: number
    }

    expect(state.status).toBe('confirmed')
    expect(state.payment_model).toBe('direct_owner_v1')
    expect(state.total_amount).toBe(0)
    expect(state.zero_payable).toBe(true)
    expect(state.loyalty_points_redeemed).toBe(100)
    expect(state.attempt_count).toBe(0)
    expect(state.debit_count).toBe(1)
  })
})
