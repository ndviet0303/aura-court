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
  arrangeCourt,
  executeGuardedBackendPhp,
  expireHolds,
  grantLoyaltyPoints,
} from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'

function getCustomerWalletBalance(manifest: ReturnType<typeof getTestManifest>): number {
  const orgId = manifest.fixtures?.organizations.org1.id
  const userId = manifest.fixtures?.users.customer.id
  if (!orgId || !userId) throw new Error('Wallet balance assertion requires current test actors.')
  return Number(
    executeGuardedBackendPhp(
      manifest,
      `
    echo (int) (\\App\\Models\\WalletAccount::where('organization_id', ${orgId})->where('user_id', ${userId})->sum('balance'));
  `,
    ),
  )
}

test.describe('P0: Hủy đặt sân, Nghĩa vụ hoàn tiền & Đổi lịch chơi @p0', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  test('4.6: Yêu cầu hủy và báo giá hoàn tiền -> Chủ sân báo chuyển khoản -> Người nhận xác nhận không sinh credit ví ảo', async ({
    page,
    browser,
  }) => {
    test.setTimeout(90000)
    const manifest = getTestManifest()
    const initialWalletBalance = getCustomerWalletBalance(manifest)
    const customer = getActorCredentials(manifest, 'customer')
    const owner = getActorCredentials(manifest, 'owner1')
    const venue1A = manifest.fixtures?.organizations.org1?.venue1A
    const orgId = manifest.fixtures?.organizations.org1?.id
    if (!venue1A?.routingId || !orgId) {
      throw new Error('E2E fixture missing: venue1A routingId or organizationId')
    }

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

    // 1. Arrange dedicated court for refund test
    const court = arrangeCourt(manifest, {
      testKey: '4_6_refund',
      venueId: venue1A.id,
      price: 100000,
    })
    const courtId = court.court_id

    // 2. Authenticate customer
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 3. Navigate to schedule and book an available slot
    await page.goto(`/courts/${courtId}/schedule`, { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.schedule-split-layout, .schedule-view-page').first()).toBeVisible({
      timeout: 20000,
    })

    const dateCards = page.locator('.date-carousel .date-card-btn')
    const targetCard = dateCards.nth(4)
    await targetCard.waitFor({ state: 'visible', timeout: 15000 })
    await targetCard.click()
    await expect(targetCard).toHaveClass(/active/, { timeout: 10000 })

    const availableSlots = page.locator('.slots-grid .slot-cell-btn.is-available')
    await expect(availableSlots.first()).toBeVisible({ timeout: 25000 })
    expect(await availableSlots.count()).toBeGreaterThanOrEqual(2)
    await availableSlots.nth(0).click()
    await availableSlots.nth(1).click()
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
        }
      }
    }
    const reservationCode = paymentBody.reservation.code
    const attempt = paymentBody.payment_attempt

    // 4. Confirm the reservation via synthetic verified webhook
    const sendWebhookRes = await sendSyntheticPaymentWebhook(manifest, {
      routingId: venue1A.routingId,
      secret: `whsec_e2e_1a_${manifest.runId}`,
      accountNumber: attempt.receiver.account_number,
      amount: attempt.amount,
      code: attempt.transfer_content,
      transactionDate: formatVietnamDate(new Date()),
    })
    expect(sendWebhookRes.statusCode).toBe(200)

    // Authoritative check: the reservation is confirmed; get its slot IDs
    const getBookingDetailsScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Reservation;
      use App\\Models\\WalletAccount;

      $reservation = Reservation::withoutGlobalScope('tenant_isolation')->where('code', '${reservationCode}')->firstOrFail();
      $slots = $reservation->slots()->withoutGlobalScope('tenant_isolation')->orderBy('id')->get();
      $slot1 = $slots->first();
      $slot2 = $slots->count() > 1 ? $slots->get(1) : $slot1;
      $wallet = WalletAccount::where('user_id', $reservation->user_id)->where('organization_id', ${orgId})->first();

      echo json_encode([
        'reservation_id' => $reservation->id,
        'reservation_code' => $reservation->code,
        'reservation_status' => $reservation->status,
        'slot_id' => $slot1->id,
        'slot_2_id' => $slot2->id,
        'total_amount' => (int) $reservation->total_amount,
        'wallet_balance' => $wallet ? (int) $wallet->balance : 0,
      ]);
    `
    const detailsOut = execFileSync('php', ['-r', getBookingDetailsScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })
    const details = JSON.parse(detailsOut) as {
      reservation_id: number
      reservation_code: string
      reservation_status: string
      slot_id: number
      slot_2_id: number
      total_amount: number
      wallet_balance: number
    }

    expect(details.reservation_status).toBe('confirmed')
    expect(details.slot_2_id).not.toBe(details.slot_id)
    expect(details.wallet_balance).toBe(initialWalletBalance) // Direct-owner checkout preserves existing wallet balance

    // 5. Customer opens the cancel modal on /booking and quotes a partial refund of slot 1
    await page.goto('/booking', { waitUntil: 'domcontentloaded' })
    const card = page.locator('.user-booking-card').filter({ hasText: `#${reservationCode}` })
    await expect(card).toBeVisible({ timeout: 20000 })
    await card.locator('[data-testid="booking-cancel-btn"]').click()
    const cancelModal = page.locator('[data-testid="cancel-modal"]')
    await expect(cancelModal).toBeVisible({ timeout: 10000 })
    await cancelModal.locator('[data-testid="cancel-scope-partial"]').check()
    await cancelModal.locator(`[data-testid="cancel-slot-${details.slot_id}"]`).check()

    const quoteResponsePromise = page.waitForResponse(
      (res) =>
        res.url().includes(`/api/reservations/${reservationCode}/refund-quote`) &&
        res.request().method() === 'GET',
    )
    await cancelModal.locator('[data-testid="cancel-quote-btn"]').click()
    const quoteResponse = await quoteResponsePromise
    expect(quoteResponse.status()).toBe(200)
    const quoteJson = (await quoteResponse.json()) as {
      data: {
        reservation_code: string
        calculation: {
          gross_amount: number
          fee_amount: number
          refund_amount: number
          currency: string
        }
      }
    }
    await expect(cancelModal.locator('[data-testid="cancel-quote"]')).toBeVisible()

    expect(quoteJson.data).toBeDefined()
    expect(quoteJson.data.calculation.gross_amount).toBe(100000)
    expect(quoteJson.data.calculation.refund_amount).toBeLessThanOrEqual(100000)
    expect(quoteJson.data.calculation.refund_amount).toBeGreaterThan(0)
    expect(quoteJson.data.calculation.fee_amount).toBe(
      quoteJson.data.calculation.gross_amount - quoteJson.data.calculation.refund_amount,
    )

    // 6. Customer submits the refund request from the modal
    await cancelModal
      .locator('[data-testid="cancel-reason"]')
      .fill('Khách hàng yêu cầu hủy lịch bận đột xuất')
    await cancelModal.locator('[data-testid="cancel-recipient-bank"]').fill('MBBank')
    await cancelModal.locator('[data-testid="cancel-recipient-name"]').fill('Customer Test')
    await cancelModal.locator('[data-testid="cancel-recipient-number"]').fill('0987654321')
    const refundResponsePromise = page.waitForResponse(
      (res) =>
        res.url().includes(`/api/reservations/${reservationCode}/refund-requests`) &&
        res.request().method() === 'POST',
    )
    await cancelModal.locator('[data-testid="cancel-submit-btn"]').click()
    const refundResponse = await refundResponsePromise
    expect(refundResponse.status()).toBe(201)
    const refundRequestJson = (await refundResponse.json()) as {
      data: {
        id: number
        status: string
        refund_amount: number
        reference_code: string
      }
    }
    await expect(cancelModal.locator('[data-testid="cancel-result-status"]')).toBeVisible()

    expect(refundRequestJson.data).toBeDefined()
    expect(refundRequestJson.data.status).toBe('requested')
    const refundId = refundRequestJson.data.id

    // Authoritative check: Platform wallet was NOT credited (no fake wallet refund)
    const checkWalletScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\WalletAccount;
      use App\\Models\\OwnerRefundObligation;

      $wallet = WalletAccount::where('user_id', ${manifest.fixtures?.users.customer?.id})->where('organization_id', ${orgId})->first();
      $obligation = OwnerRefundObligation::findOrFail(${refundId});

      echo json_encode([
        'wallet_balance' => $wallet ? (int) $wallet->balance : 0,
        'obligation_status' => $obligation->status,
      ]);
    `
    const postReqWallet = JSON.parse(
      execFileSync('php', ['-r', checkWalletScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { wallet_balance: number; obligation_status: string }
    expect(postReqWallet.wallet_balance).toBe(initialWalletBalance)
    expect(postReqWallet.obligation_status).toBe('requested')

    // 7. Owner approves the refund obligation
    const ownerContext = await browser.newContext()
    const ownerPage = await ownerContext.newPage()
    try {
      // Authenticate owner via app authApi
      await ownerPage.goto('/login', { waitUntil: 'domcontentloaded' })
      await withE2EApi(
        ownerPage,
        async ({ authApi }, { email, password }) => {
          await authApi.login({ email, password })
        },
        { email: owner.email, password: owner.password },
      )

      // Owner approves request via API
      const approveJson = (await withE2EApi(
        ownerPage,
        async ({ api }, { id, organizationId }) => {
          try {
            return await api.post(
              `/refund-requests/${id}/approve`,
              {},
              {
                headers: {
                  'X-Organization-ID': String(organizationId),
                },
              },
            )
          } catch (err: any) {
            throw new Error(
              `Owner approve failed: ${err.status} ${JSON.stringify(err.response?.data || err.data || err.message)}`,
            )
          }
        },
        { id: refundId, organizationId: orgId },
      )) as { data: { status: string } }
      expect(approveJson.data.status).toBe('approved')

      // 8. Owner reports bank transfer execution
      const reportJson = (await withE2EApi(
        ownerPage,
        async ({ api }, { id, organizationId }) => {
          return await api.post(
            `/refund-requests/${id}/report-execution`,
            {
              transfer_reference: 'MB-REF-46890123',
              evidence: 'Chuyển khoản ngân hàng MBBank số giao dịch 46890123',
            },
            {
              headers: {
                'X-Organization-ID': String(organizationId),
              },
            },
          )
        },
        { id: refundId, organizationId: orgId },
      )) as { data: { status: string; execution_reference: string } }
      expect(reportJson.data.status).toBe('owner_reported')
      expect(reportJson.data.execution_reference).toBe('MB-REF-46890123')
    } finally {
      await ownerContext.close()
    }

    // 9. Customer acknowledges receipt of the refund
    const ackRes = (await withE2EApi(
      page,
      async ({ api }, { id }) => {
        return await api.post(`/refund-requests/${id}/acknowledge`, {
          accepted: true,
          note: 'Đã nhận đủ tiền hoàn qua tài khoản MBBank',
        })
      },
      { id: refundId },
    )) as { data: { status: string } }
    expect(ackRes.data.status).toBe('acknowledged')

    // 10. Final Authoritative Verification
    const finalCheckScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\OwnerRefundObligation;
      use App\\Models\\WalletAccount;
      use App\\Models\\ReservationSlot;

      $obligation = OwnerRefundObligation::findOrFail(${refundId});
      $wallet = WalletAccount::where('user_id', ${manifest.fixtures?.users.customer?.id})->where('organization_id', ${orgId})->first();
      $slot1 = ReservationSlot::findOrFail(${details.slot_id});
      $slot2 = ReservationSlot::findOrFail(${details.slot_2_id});

      echo json_encode([
        'final_status' => $obligation->status,
        'wallet_balance' => $wallet ? (int) $wallet->balance : 0,
        'slot1_status' => $slot1->status,
        'slot2_status' => $slot2->status,
        'has_execution_reference' => !empty($obligation->execution_reference),
        'execution_reference' => $obligation->execution_reference,
      ]);
    `
    const finalState = JSON.parse(
      execFileSync('php', ['-r', finalCheckScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      final_status: string
      wallet_balance: number
      slot1_status: string
      slot2_status: string
      has_execution_reference: boolean
      execution_reference: string
    }

    expect(finalState.final_status).toBe('acknowledged')
    expect(finalState.wallet_balance).toBe(initialWalletBalance) // Platform wallet was NEVER credited
    expect(finalState.slot1_status).toBe('cancelled') // Partially cancelled slot was released
    expect(finalState.slot2_status).toBe('confirmed') // Remaining slot remains confirmed and active!
    expect(finalState.has_execution_reference).toBe(true)
    expect(finalState.execution_reference).toBe('MB-REF-46890123')
  })

  test('4.7: Đổi lịch sang giá cao hơn thu chênh lệch; timeout giữ chỗ cũ; đổi sang giá thấp tạo nghĩa vụ hoàn tiền', async ({
    page,
  }) => {
    test.setTimeout(120000)
    const manifest = getTestManifest()
    const initialWalletBalance = getCustomerWalletBalance(manifest)
    const customer = getActorCredentials(manifest, 'customer')
    const venue1A = manifest.fixtures?.organizations.org1?.venue1A
    const orgId = manifest.fixtures?.organizations.org1?.id
    if (!venue1A?.routingId || !orgId) {
      throw new Error('E2E fixture missing: venue1A routingId or organizationId')
    }

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

    // Warm the real catalog before adding fixtures: the scenario must work with an existing cache.
    expect((await page.request.get(`${manifest.backend.url}/api/courts`)).ok()).toBe(true)
    const courtCheap = arrangeCourt(manifest, {
      testKey: '4_7_cheap',
      venueId: venue1A.id,
      price: 100000,
    })
    const courtExpensive = arrangeCourt(manifest, {
      testKey: '4_7_expensive',
      venueId: venue1A.id,
      price: 180000,
    })

    // CLI fixtures have isolated cache storage. Publish only these new courts through the owner API
    // so the deployed catalog invalidation runs before the customer's reschedule journey.
    const owner = getActorCredentials(manifest, 'owner1')
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await withE2EApi(page, async ({ authApi, api }) => {
      await authApi.login({ email: owner.email, password: owner.password })
      for (const court of [courtCheap, courtExpensive]) {
        expect(court.organization_id).toBe(orgId)
        const published = await api.put<{ id: number; is_active: boolean }>(
          `/admin/courts/${court.court_id}`,
          { is_active: true },
        )
        expect(published.id).toBe(court.court_id)
        expect(published.is_active).toBe(true)
      }
      const catalog = await api.get<Array<{ id: number }>>('/courts')
      expect(catalog.map((court) => court.id)).toEqual(
        expect.arrayContaining([courtCheap.court_id, courtExpensive.court_id]),
      )
    })
    await withE2EApi(page, async ({ authApi }) => {
      await authApi.logout()
    })

    // 1. Authenticate customer
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Book an initial slot on Court Cheap (100,000 VND)
    await page.goto(`/courts/${courtCheap.court_id}/schedule`, { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.schedule-split-layout, .schedule-view-page').first()).toBeVisible({
      timeout: 20000,
    })

    const dateCards = page.locator('.date-carousel .date-card-btn')
    const targetCard = dateCards.nth(4)
    await targetCard.waitFor({ state: 'visible', timeout: 15000 })
    await targetCard.click()
    await expect(targetCard).toHaveClass(/active/, { timeout: 10000 })

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

    const paymentResponse = await paymentResponsePromise
    const paymentBody = (await paymentResponse.json()) as {
      payment?: { id: number; amount: number; transfer_content: string }
      payment_attempt?: {
        id: number
        amount: number
        transfer_content: string
        receiver?: { account_number: string }
      }
    }
    const attempt = paymentBody.payment_attempt || paymentBody.payment
    expect(attempt).toBeDefined()
    expect(attempt?.transfer_content).toBeTruthy()

    await expect(page).toHaveURL(/\/reservations\/RSV[A-Z0-9]+/, { timeout: 25000 })
    const initialBookingCode = page.url().split('/reservations/')[1]?.split('?')[0]
    expect(initialBookingCode).toBeTruthy()

    // Confirm initial booking via webhook
    const receiverAccountNumber =
      paymentBody.payment_attempt?.receiver?.account_number || '0901000005'
    const initialWebhookRes = await sendSyntheticPaymentWebhook(manifest, {
      routingId: venue1A.routingId,
      secret: 'whsec_e2e_1a_' + manifest.runId,
      accountNumber: receiverAccountNumber,
      amount: attempt!.amount,
      code: attempt!.transfer_content,
      transactionDate: formatVietnamDate(new Date()),
    })
    expect(initialWebhookRes.statusCode).toBe(200)

    // The reservation payment modal polls until the webhook lands
    await expect(page.locator('[data-testid="reservation-payment-status"]')).toHaveClass(
      /status-paid/,
      { timeout: 15000 },
    )
    // Navigate to /bookings
    await page.goto('/bookings', { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.user-booking-card', { hasText: initialBookingCode })).toBeVisible({
      timeout: 20000,
    })
    const bookingCard = page.locator('.user-booking-card', { hasText: initialBookingCode })

    // =========================================================================
    // PART A: Reschedule to higher price (100k -> 180k) with difference payment
    // =========================================================================
    const rescheduleBtn = bookingCard.locator('[data-testid="booking-reschedule-btn"]')
    await expect(rescheduleBtn).toBeVisible({ timeout: 10000 })
    await rescheduleBtn.click()

    const rescheduleModal = page.locator('[data-testid="reschedule-modal"]')
    // Select Court Expensive
    await expect(
      rescheduleModal.locator(
        `[data-testid="reschedule-court"] option[value="${courtExpensive.court_id}"]`,
      ),
    ).toBeAttached({ timeout: 15000 })
    await rescheduleModal
      .locator('[data-testid="reschedule-court"]')
      .selectOption(String(courtExpensive.court_id))
    // Set future date and time
    const futureDate = getFuturePlayDate(5).dateString
    await rescheduleModal.locator('[data-testid="reschedule-date"]').fill(futureDate)
    await rescheduleModal.locator('[data-testid="reschedule-time"]').fill('10:00')

    // Click preview
    await rescheduleModal.locator('[data-testid="reschedule-preview-btn"]').click()
    await expect(rescheduleModal.locator('[data-testid="reschedule-preview"]')).toBeVisible({
      timeout: 15000,
    })

    // Verify preview case extra
    await expect(rescheduleModal.locator('[data-testid="reschedule-case-extra"]')).toBeVisible()
    await expect(
      rescheduleModal.locator('[data-testid="reschedule-case-refund"]'),
    ).not.toBeVisible()
    const extraText = await rescheduleModal
      .locator('[data-testid="reschedule-extra-amount"]')
      .textContent()
    expect(extraText).toContain('80')

    // Submit reschedule request
    await rescheduleModal.locator('[data-testid="reschedule-submit-btn"]').click()

    // Assert result is held with payment attempt
    await expect(rescheduleModal.locator('[data-testid="reschedule-result"]')).toBeVisible({
      timeout: 20000,
    })
    await expect(rescheduleModal.locator('[data-testid="reschedule-result-held"]')).toBeVisible()
    const replacementCodeText = await rescheduleModal
      .locator('[data-testid="reschedule-result"] strong.font-mono')
      .textContent()
    const replacementCode1 = replacementCodeText?.trim() || ''
    expect(replacementCode1).toBeTruthy()

    // Verify DB state before payment:
    // Original reservation is STILL confirmed!
    // Replacement is held with pending booking_change payment attempt for 80,000 VND
    const preCheckScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Reservation;
      use App\\Models\\OwnerPaymentAttempt;

      $findReservation = function(string $code) {
        $res = Reservation::withoutGlobalScope('tenant_isolation')->where('code', $code)->first();
        if ($res) return $res;
        $legacy = \\App\\Models\\LegacyBookingMapping::withoutGlobalScope('tenant_isolation')->where('legacy_code', $code)->first();
        if ($legacy) return $legacy->reservation;
        $booking = \\App\\Models\\Booking::where('code', $code)->first();
        if ($booking) {
          $legacy = \\App\\Models\\LegacyBookingMapping::withoutGlobalScope('tenant_isolation')->where('legacy_booking_id', $booking->id)->first();
          if ($legacy) return $legacy->reservation;
        }
        throw new \\Exception("Reservation not found for code: " . $code);
      };

      $original = $findReservation('${initialBookingCode}');
      $replacement = $findReservation('${replacementCode1}');
      $attempt = OwnerPaymentAttempt::where('purpose', 'booking_change')->where('purpose_id', $replacement->code)->latest('id')->firstOrFail();

      echo json_encode([
        'original_status' => $original->status,
        'replacement_status' => $replacement->status,
        'attempt_status' => $attempt->status,
        'attempt_amount' => (int) $attempt->amount,
        'attempt_reference' => $attempt->reference,
        'account_number' => $attempt->receivingConnection?->accountNumber(),
      ]);
    `
    const preState = JSON.parse(
      execFileSync('php', ['-r', preCheckScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      original_status: string
      replacement_status: string
      attempt_status: string
      attempt_amount: number
      attempt_reference: string
      account_number: string
    }

    expect(preState.original_status).toBe('confirmed')
    expect(preState.replacement_status).toBe('held')
    expect(preState.attempt_status).toBe('pending')
    expect(preState.attempt_amount).toBe(80000)

    // Pay the difference via webhook
    const diffWebhookRes = await sendSyntheticPaymentWebhook(manifest, {
      routingId: venue1A.routingId,
      secret: 'whsec_e2e_1a_' + manifest.runId,
      accountNumber: preState.account_number || '0901000005',
      amount: preState.attempt_amount,
      code: preState.attempt_reference,
      transactionDate: formatVietnamDate(new Date()),
    })

    expect(diffWebhookRes.statusCode).toBe(200)

    // Close modal and reload /bookings
    await rescheduleModal.locator('.btn-ghost, .modal-close-btn').first().click()
    await page.goto('/bookings', { waitUntil: 'domcontentloaded' })

    // Verify UI and DB:
    // Original booking is cancelled / rescheduled_to replacementCode1
    // Replacement booking is confirmed!
    const postCheckScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Reservation;

      $findReservation = function(string $code) {
        $res = Reservation::withoutGlobalScope('tenant_isolation')->where('code', $code)->first();
        if ($res) return $res;
        $legacy = \\App\\Models\\LegacyBookingMapping::withoutGlobalScope('tenant_isolation')->where('legacy_code', $code)->first();
        if ($legacy) return $legacy->reservation;
        $booking = \\App\\Models\\Booking::where('code', $code)->first();
        if ($booking) {
          $legacy = \\App\\Models\\LegacyBookingMapping::withoutGlobalScope('tenant_isolation')->where('legacy_booking_id', $booking->id)->first();
          if ($legacy) return $legacy->reservation;
        }
        throw new \\Exception("Reservation not found for code: " . $code);
      };

      $original = $findReservation('${initialBookingCode}');
      $replacement = $findReservation('${replacementCode1}');
      $receipt = \\App\\Models\\OwnerPaymentReceipt::latest('id')->first();
      $attempt = \\App\\Models\\OwnerPaymentAttempt::where('purpose', 'booking_change')->where('purpose_id', $replacement->code)->latest('id')->first();

      echo json_encode([
        'original_status' => $original->status,
        'original_slot_status' => $original->slots()->first()?->status,
        'rescheduled_to' => data_get($original->metadata, 'rescheduled_to'),
        'replacement_status' => $replacement->status,
        'replacement_slot_status' => $replacement->slots()->first()?->status,
        'receipt_status' => $receipt?->status,
        'receipt_exception' => $receipt?->exception_code,
        'receipt_code' => $receipt?->payment_code,
        'attempt_status' => $attempt?->status,
      ]);
    `
    const postState = JSON.parse(
      execFileSync('php', ['-r', postCheckScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      original_status: string
      original_slot_status: string
      rescheduled_to: string
      replacement_status: string
      replacement_slot_status: string
    }

    expect(postState.original_status).toBe('cancelled')
    expect(postState.original_slot_status).toBe('cancelled')
    expect(postState.rescheduled_to).toBe(replacementCode1)
    expect(postState.replacement_status).toBe('confirmed')
    expect(postState.replacement_slot_status).toBe('confirmed')

    // =========================================================================
    // PART B: Timeout preserves original booking
    // =========================================================================
    // Book a slot on Court Cheap
    await page.goto(`/courts/${courtCheap.court_id}/schedule`, { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.schedule-split-layout, .schedule-view-page').first()).toBeVisible({
      timeout: 20000,
    })
    const timeoutDateCards = page.locator('.date-carousel .date-card-btn')
    const timeoutTargetCard = timeoutDateCards.nth(5)
    await timeoutTargetCard.waitFor({ state: 'visible', timeout: 15000 })
    await timeoutTargetCard.click()
    await expect(timeoutTargetCard).toHaveClass(/active/, { timeout: 10000 })

    const timeoutSlot = page.locator('.slots-grid .slot-cell-btn.is-available').first()
    await expect(timeoutSlot).toBeVisible({ timeout: 25000 })
    await timeoutSlot.click()

    const timeoutCheckoutBtn = page.locator('.schedule-summary-card .btn-checkout')
    await expect(timeoutCheckoutBtn).toBeEnabled({ timeout: 10000 })

    const timeoutPaymentResponsePromise = page.waitForResponse(
      (res) =>
        res.url().includes('/payment') &&
        res.request().method() === 'POST' &&
        (res.status() === 200 || res.status() === 201),
      { timeout: 25000 },
    )
    await timeoutCheckoutBtn.click()

    const timeoutPaymentResponse = await timeoutPaymentResponsePromise
    const timeoutPaymentBody = (await timeoutPaymentResponse.json()) as {
      payment?: { id: number; amount: number; transfer_content: string }
      payment_attempt?: {
        id: number
        amount: number
        transfer_content: string
        receiver?: { account_number: string }
      }
    }
    const timeoutAttempt = timeoutPaymentBody.payment_attempt || timeoutPaymentBody.payment
    expect(timeoutAttempt).toBeDefined()

    await expect(page).toHaveURL(/\/reservations\/RSV[A-Z0-9]+/, { timeout: 25000 })
    const timeoutInitialBookingCode = page.url().split('/reservations/')[1]?.split('?')[0]
    expect(timeoutInitialBookingCode).toBeTruthy()

    // Confirm booking via webhook
    const timeoutReceiverAccountNumber =
      timeoutPaymentBody.payment_attempt?.receiver?.account_number || '0901000005'
    await sendSyntheticPaymentWebhook(manifest, {
      routingId: venue1A.routingId,
      secret: 'whsec_e2e_1a_' + manifest.runId,
      accountNumber: timeoutReceiverAccountNumber,
      amount: timeoutAttempt!.amount,
      code: timeoutAttempt!.transfer_content,
      transactionDate: formatVietnamDate(new Date()),
    })

    // The reservation payment modal polls until the webhook lands
    await expect(page.locator('[data-testid="reservation-payment-status"]')).toHaveClass(
      /status-paid/,
      { timeout: 15000 },
    )

    // Go to /bookings, find the booking card, click reschedule
    await page.goto('/bookings', { waitUntil: 'domcontentloaded' })
    await expect(
      page.locator('.user-booking-card', { hasText: timeoutInitialBookingCode }),
    ).toBeVisible({ timeout: 20000 })
    const timeoutBookingCard = page.locator('.user-booking-card', {
      hasText: timeoutInitialBookingCode,
    })
    await timeoutBookingCard.locator('[data-testid="booking-reschedule-btn"]').click()

    await expect(rescheduleModal).toBeVisible({ timeout: 10000 })
    await expect(
      rescheduleModal.locator(
        `[data-testid="reschedule-court"] option[value="${courtExpensive.court_id}"]`,
      ),
    ).toBeAttached({ timeout: 15000 })
    await rescheduleModal
      .locator('[data-testid="reschedule-court"]')
      .selectOption(String(courtExpensive.court_id))
    const timeoutReschedDate = getFuturePlayDate(6).dateString
    await rescheduleModal.locator('[data-testid="reschedule-date"]').fill(timeoutReschedDate)
    await rescheduleModal.locator('[data-testid="reschedule-time"]').fill('11:00')
    await rescheduleModal.locator('[data-testid="reschedule-preview-btn"]').click()
    await expect(rescheduleModal.locator('[data-testid="reschedule-preview"]')).toBeVisible({
      timeout: 15000,
    })

    await rescheduleModal.locator('[data-testid="reschedule-submit-btn"]').click()
    await expect(rescheduleModal.locator('[data-testid="reschedule-result"]')).toBeVisible({
      timeout: 20000,
    })
    const timeoutReplacementCode =
      (
        await rescheduleModal
          .locator('[data-testid="reschedule-result"] strong.font-mono')
          .textContent()
      )?.trim() || ''
    expect(timeoutReplacementCode).toBeTruthy()

    // Do NOT pay! Fast-forward hold expiry using real CLI e2e:expire-holds
    const expireResult = expireHolds(manifest, {
      reservationCode: timeoutReplacementCode,
      minutes: 30,
    })
    expect(expireResult.status).toBe('success')

    // Authoritative DB check:
    // Timeout replacement reservation is expired!
    // Original timeoutInitialBookingCode is STILL confirmed!
    const timeoutCheckScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Reservation;
      use App\\Models\\LegacyBookingMapping;
      use App\\Models\\Booking;

      $findReservation = function(string $code) {
        $res = Reservation::withoutGlobalScope('tenant_isolation')->where('code', $code)->first();
        if ($res) return $res;
        $legacy = LegacyBookingMapping::withoutGlobalScope('tenant_isolation')->where('legacy_code', $code)->first();
        if ($legacy) return $legacy->reservation;
        $booking = Booking::where('code', $code)->first();
        if ($booking) {
          $legacy = LegacyBookingMapping::withoutGlobalScope('tenant_isolation')->where('legacy_booking_id', $booking->id)->first();
          if ($legacy) return $legacy->reservation;
        }
        throw new \\Exception("Reservation not found for code: " . $code);
      };

      $confirmedBooking = $findReservation('${timeoutInitialBookingCode}');
      $timedOutBooking = $findReservation('${timeoutReplacementCode}');

      echo json_encode([
        'confirmed_booking_status' => $confirmedBooking->status,
        'confirmed_slot_status' => $confirmedBooking->slots()->first()?->status,
        'timed_out_status' => $timedOutBooking->status,
        'timed_out_slot_status' => $timedOutBooking->slots()->first()?->status,
      ]);
    `
    const timeoutState = JSON.parse(
      execFileSync('php', ['-r', timeoutCheckScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      confirmed_booking_status: string
      confirmed_slot_status: string
      timed_out_status: string
      timed_out_slot_status: string
    }

    expect(timeoutState.confirmed_booking_status).toBe('confirmed')
    expect(timeoutState.confirmed_slot_status).toBe('confirmed')
    expect(timeoutState.timed_out_status).toBe('expired')
    expect(timeoutState.timed_out_slot_status).toBe('expired')

    // Close modal and reload page: booking remains confirmed
    await rescheduleModal.locator('.btn-ghost, .modal-close-btn').first().click()
    await page.goto('/bookings', { waitUntil: 'domcontentloaded' })
    await expect(
      page.locator('.user-booking-card', { hasText: timeoutInitialBookingCode }),
    ).toBeVisible({ timeout: 15000 })
    await expect(
      page
        .locator('.user-booking-card', { hasText: timeoutInitialBookingCode })
        .locator('.status-chip'),
    ).toContainText(/(Đã xác nhận|Confirmed)/i)
    // =========================================================================
    // PART C: Cheaper reschedule (180k -> 100k) creates refund obligation
    // =========================================================================
    // Book a fresh 180,000 VND booking on Court Expensive
    await page.goto(`/courts/${courtExpensive.court_id}/schedule`, {
      waitUntil: 'domcontentloaded',
    })
    await expect(page.locator('.schedule-split-layout, .schedule-view-page').first()).toBeVisible({
      timeout: 20000,
    })

    const expDateCards = page.locator('.date-carousel .date-card-btn')
    const expTargetCard = expDateCards.nth(4)
    await expTargetCard.waitFor({ state: 'visible', timeout: 15000 })
    await expTargetCard.click()
    await expect(expTargetCard).toHaveClass(/active/, { timeout: 10000 })

    const expAvailableSlot = page.locator('.slots-grid .slot-cell-btn.is-available').first()
    await expect(expAvailableSlot).toBeVisible({ timeout: 25000 })
    await expAvailableSlot.click()

    const expCheckoutBtn = page.locator('.schedule-summary-card .btn-checkout')
    await expect(expCheckoutBtn).toBeEnabled({ timeout: 10000 })

    const expPaymentResponsePromise = page.waitForResponse(
      (res) =>
        res.url().includes('/payment') &&
        res.request().method() === 'POST' &&
        (res.status() === 200 || res.status() === 201),
      { timeout: 25000 },
    )
    await expCheckoutBtn.click()

    const expPaymentResponse = await expPaymentResponsePromise
    const expPaymentBody = (await expPaymentResponse.json()) as {
      payment?: { id: number; amount: number; transfer_content: string }
      payment_attempt?: {
        id: number
        amount: number
        transfer_content: string
        receiver?: { account_number: string }
      }
    }
    const expAttempt = expPaymentBody.payment_attempt || expPaymentBody.payment
    expect(expAttempt).toBeDefined()

    await expect(page).toHaveURL(/\/reservations\/RSV[A-Z0-9]+/, { timeout: 25000 })
    const expBookingCode = page.url().split('/reservations/')[1]?.split('?')[0]
    expect(expBookingCode).toBeTruthy()

    // Confirm initial 180k booking via webhook
    const expReceiverAccountNumber =
      expPaymentBody.payment_attempt?.receiver?.account_number || '0901000005'
    const expWebhookRes = await sendSyntheticPaymentWebhook(manifest, {
      routingId: venue1A.routingId,
      secret: 'whsec_e2e_1a_' + manifest.runId,
      accountNumber: expReceiverAccountNumber,
      amount: expAttempt!.amount,
      code: expAttempt!.transfer_content,
      transactionDate: formatVietnamDate(new Date()),
    })
    expect(expWebhookRes.statusCode).toBe(200)

    // The reservation payment modal polls until the webhook lands
    await expect(page.locator('[data-testid="reservation-payment-status"]')).toHaveClass(
      /status-paid/,
      { timeout: 15000 },
    )

    // Navigate to /bookings and find the 180,000 VND booking
    await page.goto('/bookings', { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.user-booking-card', { hasText: expBookingCode })).toBeVisible({
      timeout: 20000,
    })
    const expBookingCard = page.locator('.user-booking-card', { hasText: expBookingCode })

    await expBookingCard.locator('[data-testid="booking-reschedule-btn"]').click()
    await expect(rescheduleModal).toBeVisible({ timeout: 10000 })

    // Select Court Cheap (100,000 VND)
    await expect(
      rescheduleModal.locator(
        `[data-testid="reschedule-court"] option[value="${courtCheap.court_id}"]`,
      ),
    ).toBeAttached({ timeout: 15000 })
    await rescheduleModal
      .locator('[data-testid="reschedule-court"]')
      .selectOption(String(courtCheap.court_id))
    const cheapDate = getFuturePlayDate(7).dateString
    await rescheduleModal.locator('[data-testid="reschedule-date"]').fill(cheapDate)
    await rescheduleModal.locator('[data-testid="reschedule-time"]').fill('14:00')

    await rescheduleModal.locator('[data-testid="reschedule-preview-btn"]').click()
    await expect(rescheduleModal.locator('[data-testid="reschedule-preview"]')).toBeVisible({
      timeout: 15000,
    })

    // Verify refund case preview (180,000 VND -> 100,000 VND = 80,000 VND refund)
    await expect(rescheduleModal.locator('[data-testid="reschedule-case-refund"]')).toBeVisible()
    await expect(rescheduleModal.locator('[data-testid="reschedule-case-extra"]')).not.toBeVisible()
    const refundDiffText = await rescheduleModal
      .locator('[data-testid="reschedule-refund-amount"]')
      .textContent()
    expect(refundDiffText).toContain('80')

    // Fill recipient details
    await rescheduleModal.locator('[data-testid="reschedule-recipient-bank"]').fill('MBBANK')
    await rescheduleModal.locator('[data-testid="reschedule-recipient-name"]').fill('NGUYEN VAN A')
    await rescheduleModal.locator('[data-testid="reschedule-recipient-number"]').fill('0901000001')

    // Submit reschedule request
    await rescheduleModal.locator('[data-testid="reschedule-submit-btn"]').click()

    // Assert swapped immediately and refund created
    await expect(rescheduleModal.locator('[data-testid="reschedule-result"]')).toBeVisible({
      timeout: 20000,
    })
    await expect(rescheduleModal.locator('[data-testid="reschedule-result-swapped"]')).toBeVisible()
    await expect(rescheduleModal.locator('[data-testid="reschedule-result-refund"]')).toBeVisible()
    const cheaperReplacementCode =
      (
        await rescheduleModal
          .locator('[data-testid="reschedule-result"] strong.font-mono')
          .textContent()
      )?.trim() || ''
    expect(cheaperReplacementCode).toBeTruthy()

    // Authoritative DB check:
    // Cheaper replacement is confirmed!
    // Old expBooking is cancelled!
    // OwnerRefundObligation exists for 80,000 VND with recipient details!
    // Direct-owner refund preserves the existing wallet balance.
    const refundCheckScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Reservation;
      use App\\Models\\LegacyBookingMapping;
      use App\\Models\\Booking;
      use App\\Models\\OwnerRefundObligation;
      use App\\Models\\WalletAccount;

      $findReservation = function(string $code) {
        $res = Reservation::withoutGlobalScope('tenant_isolation')->where('code', $code)->first();
        if ($res) return $res;
        $legacy = \\App\\Models\\LegacyBookingMapping::withoutGlobalScope('tenant_isolation')->where('legacy_code', $code)->first();
        if ($legacy) return $legacy->reservation;
        $booking = \\App\\Models\\Booking::where('code', $code)->first();
        if ($booking) {
          $legacy = \\App\\Models\\LegacyBookingMapping::withoutGlobalScope('tenant_isolation')->where('legacy_booking_id', $booking->id)->first();
          if ($legacy) return $legacy->reservation;
        }
        throw new \\Exception("Reservation not found for code: " . $code);
      };

      $oldBooking = $findReservation('${expBookingCode}');
      $newBooking = $findReservation('${cheaperReplacementCode}');
      $obligation = OwnerRefundObligation::withoutGlobalScope('tenant_isolation')->where('reservation_id', $oldBooking->id)->latest('id')->firstOrFail();
      $wallet = WalletAccount::where('user_id', $oldBooking->user_id)->where('organization_id', ${orgId})->first();
      $recipient = $obligation->recipient();

      echo json_encode([
        'old_status' => $oldBooking->status,
        'old_slot_status' => $oldBooking->slots()->first()?->status,
        'new_status' => $newBooking->status,
        'new_slot_status' => $newBooking->slots()->first()?->status,
        'obligation_amount' => (int) $obligation->amount,
        'obligation_status' => $obligation->status,
        'obligation_bank' => $recipient['bank_code'] ?? null,
        'obligation_account' => $recipient['account_number'] ?? null,
        'wallet_balance' => $wallet ? (int) $wallet->balance : 0,
      ]);
    `
    const refundState = JSON.parse(
      execFileSync('php', ['-r', refundCheckScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      old_status: string
      old_slot_status: string
      new_status: string
      new_slot_status: string
      obligation_amount: number
      obligation_status: string
      obligation_bank: string
      obligation_account: string
      wallet_balance: number
    }

    expect(refundState.old_status).toBe('cancelled')
    expect(refundState.old_slot_status).toBe('cancelled')
    expect(refundState.new_status).toBe('confirmed')
    expect(refundState.new_slot_status).toBe('confirmed')
    expect(refundState.obligation_amount).toBe(80000)
    expect(refundState.obligation_status).toBe('requested')
    expect(refundState.obligation_bank).toBe('MBBANK')
    expect(refundState.obligation_account).toBe('0901000001')
    expect(refundState.wallet_balance).toBe(initialWalletBalance) // No fake wallet credit!

    // Close modal and reload page: original booking card flipped to cancelled
    await rescheduleModal.locator('.btn-ghost, .modal-close-btn').first().click()
    await page.goto('/bookings', { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.user-booking-card', { hasText: expBookingCode })).toBeVisible({
      timeout: 15000,
    })
    await expect(
      page.locator('.user-booking-card', { hasText: expBookingCode }).locator('.status-chip'),
    ).toContainText(/(Đã hủy|Cancelled)/i)
  })

  test('4.8: Thử lại idempotent khi mất kết nối trả kết quả cũ không tạo đặt sân, biên nhận hay quyền lợi trùng', async ({
    page,
  }) => {
    test.setTimeout(120000)
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const venue1A = manifest.fixtures?.organizations.org1?.venue1A
    const orgId = manifest.fixtures?.organizations.org1?.id
    if (!venue1A?.routingId || !orgId) {
      throw new Error('E2E fixture missing: venue1A routingId or organizationId')
    }

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

    // Arrange dedicated court and initial loyalty points for the customer
    const court = arrangeCourt(manifest, {
      testKey: '4_8_idemp',
      venueId: venue1A.id,
      price: 100000,
    })
    grantLoyaltyPoints(manifest, {
      userId: manifest.fixtures?.users.customer.id,
      organizationId: orgId,
      points: 200,
    })

    // Authenticate customer via UI
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 1. COMMITTED CHECKOUT RETRY (Reservation checkout with loyalty points)
    // Send checkout request via API with a unique Idempotency-Key
    const checkoutKey = `idemp_checkout_${Date.now()}`
    const futureSlot = getFuturePlayDate(4, 15, 0)

    const firstCheckoutRes = (await withE2EApi(
      page,
      async ({ api }, { courtId, slotTime, key }) => {
        return await api.post(
          '/reservations/checkout',
          {
            court_id: courtId,
            slots: [slotTime],
            loyalty_points: 50,
          },
          {
            headers: {
              'Idempotency-Key': key,
            },
          },
        )
      },
      { courtId: court.court_id, slotTime: futureSlot.slotTime, key: checkoutKey },
    )) as {
      reservation: { id: number; code: string; status: string }
    }

    expect(firstCheckoutRes.reservation).toBeDefined()
    const firstReservationId = firstCheckoutRes.reservation.id
    const firstReservationCode = firstCheckoutRes.reservation.code
    expect(firstReservationCode).toBeTruthy()

    // Client lost response or experienced timeout, retries with the EXACT same key & payload
    const retryCheckoutRes = (await withE2EApi(
      page,
      async ({ api }, { courtId, slotTime, key }) => {
        return await api.post(
          '/reservations/checkout',
          {
            court_id: courtId,
            slots: [slotTime],
            loyalty_points: 50,
          },
          {
            headers: {
              'Idempotency-Key': key,
            },
          },
        )
      },
      { courtId: court.court_id, slotTime: futureSlot.slotTime, key: checkoutKey },
    )) as {
      reservation: { id: number; code: string; status: string }
    }

    // Verify retry returns exact same reservation without error or second creation
    expect(retryCheckoutRes.reservation.id).toBe(firstReservationId)
    expect(retryCheckoutRes.reservation.code).toBe(firstReservationCode)

    // Authoritative DB check: the dedicated court holds exactly 1 reservation, points debited ONCE (not twice)
    const checkoutIdempCheckScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\IdempotencyRecord;
      use App\\Models\\PointLedger;
      use App\\Models\\Reservation;

      $reservation = Reservation::withoutGlobalScope('tenant_isolation')->where('code', '${firstReservationCode}')->firstOrFail();
      $reservationCount = Reservation::withoutGlobalScope('tenant_isolation')->where('court_id', ${court.court_id})->count();
      $idempCount = IdempotencyRecord::where('key', '${checkoutKey}')->count();
      $debitPoints = PointLedger::where('user_id', ${manifest.fixtures?.users.customer.id})
        ->where('source_type', 'reservation')
        ->where('source_id', $reservation->id)
        ->where('delta', '<', 0)
        ->sum('delta');

      echo json_encode([
        'reservation_count' => $reservationCount,
        'idemp_record_count' => $idempCount,
        'points_debited' => abs((int) $debitPoints),
      ]);
    `
    const checkoutIdempState = JSON.parse(
      execFileSync('php', ['-r', checkoutIdempCheckScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      reservation_count: number
      idemp_record_count: number
      points_debited: number
    }

    expect(checkoutIdempState.reservation_count).toBe(1)
    expect(checkoutIdempState.idemp_record_count).toBe(1)
    expect(checkoutIdempState.points_debited).toBe(50) // debited ONCE (no double benefit consumption)

    // Confirm the reservation with a payment webhook so it becomes confirmed for subsequent operations
    const paymentCheckScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\OwnerPaymentAttempt;

      $attempt = OwnerPaymentAttempt::where('purpose', 'booking')->where('purpose_id', '${firstReservationCode}')->latest('id')->firstOrFail();

      echo json_encode([
        'attempt_amount' => (int) $attempt->amount,
        'attempt_reference' => $attempt->reference,
        'account_number' => $attempt->receivingConnection?->accountNumber(),
      ]);
    `
    const paymentInfo = JSON.parse(
      execFileSync('php', ['-r', paymentCheckScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      attempt_amount: number
      attempt_reference: string
      account_number: string
    }

    const webhookTransactionId = `TXN-IDEMP-${Date.now()}`
    const webhookTransactionDate = formatVietnamDate(new Date(Date.now() + 5000))

    const initialWebhookRes = await sendSyntheticPaymentWebhook(manifest, {
      routingId: venue1A.routingId,
      secret: 'whsec_e2e_1a_' + manifest.runId,
      accountNumber: paymentInfo.account_number || '0901000005',
      amount: paymentInfo.attempt_amount,
      code: paymentInfo.attempt_reference,
      transactionId: webhookTransactionId,
      transactionDate: webhookTransactionDate,
    })
    expect(initialWebhookRes.statusCode).toBe(200)

    // 2. COMMITTED WEBHOOK RETRY (Bank webhook redelivery)
    // Redeliver the exact same webhook payload (same signature, same timestamp, same body)
    const duplicateWebhookRes = await sendSyntheticPaymentWebhook(manifest, {
      routingId: venue1A.routingId,
      secret: 'whsec_e2e_1a_' + manifest.runId,
      accountNumber: paymentInfo.account_number || '0901000005',
      amount: paymentInfo.attempt_amount,
      code: paymentInfo.attempt_reference,
      transactionId: webhookTransactionId,
      transactionDate: webhookTransactionDate,
    })
    expect(duplicateWebhookRes.statusCode).toBe(200)

    // Authoritative DB check: exactly 1 payment allocation created, no second receipt allocated
    const webhookIdempCheckScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\OwnerPaymentAttempt;
      use App\\Models\\OwnerPaymentAllocation;

      $attempt = OwnerPaymentAttempt::where('reference', '${paymentInfo.attempt_reference}')->firstOrFail();
      $allocations = OwnerPaymentAllocation::where('payment_attempt_id', $attempt->id)->count();

      echo json_encode([
        'attempt_status' => $attempt->status,
        'allocation_count' => $allocations,
      ]);
    `
    const webhookIdempState = JSON.parse(
      execFileSync('php', ['-r', webhookIdempCheckScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      attempt_status: string
      allocation_count: number
    }
    expect(webhookIdempState.attempt_status).toBe('paid')
    expect(webhookIdempState.allocation_count).toBe(1) // No duplicate allocation!

    // 3. COMMITTED RESCHEDULE RETRY (Reschedule request retry with same key)
    const reschedCourt = arrangeCourt(manifest, {
      testKey: '4_8_resched',
      venueId: venue1A.id,
      price: 100000,
    })
    const reschedKey = `idemp_resched_${Date.now()}`
    const reschedSlot = getFuturePlayDate(5, 16, 0)

    const firstReschedRes = (await withE2EApi(
      page,
      async ({ api }, { reservationCode, courtId, slotTime, key }) => {
        return await api.post(`/reservations/${reservationCode}/reschedule-requests`, {
          court_id: courtId,
          slots: [slotTime],
          idempotency_key: key,
        })
      },
      {
        reservationCode: firstReservationCode,
        courtId: reschedCourt.court_id,
        slotTime: reschedSlot.slotTime,
        key: reschedKey,
      },
    )) as {
      data: {
        status: string
        replacement: { code: string; status: string }
      }
    }

    expect(firstReschedRes.data.replacement.code).toBeTruthy()
    const firstReplacementCode = firstReschedRes.data.replacement.code

    // Client retries the exact same reschedule request (simulating network timeout)
    const retryReschedRes = (await withE2EApi(
      page,
      async ({ api }, { reservationCode, courtId, slotTime, key }) => {
        return await api.post(`/reservations/${reservationCode}/reschedule-requests`, {
          court_id: courtId,
          slots: [slotTime],
          idempotency_key: key,
        })
      },
      {
        reservationCode: firstReservationCode,
        courtId: reschedCourt.court_id,
        slotTime: reschedSlot.slotTime,
        key: reschedKey,
      },
    )) as {
      data: {
        status: string
        replacement: { code: string; status: string }
      }
    }

    // Verify retry returns exact same replacement without creating another replacement
    expect(retryReschedRes.data.replacement.code).toBe(firstReplacementCode)

    // DB check: exactly 1 replacement reservation exists for this idempotency key
    const reschedIdempCheckScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Reservation;

      $count = Reservation::withoutGlobalScope('tenant_isolation')
        ->where('metadata->reschedule_key', '${reschedKey}')
        ->count();

      echo json_encode(['replacement_count' => $count]);
    `
    const reschedIdempState = JSON.parse(
      execFileSync('php', ['-r', reschedIdempCheckScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { replacement_count: number }

    expect(reschedIdempState.replacement_count).toBe(1) // No duplicate replacement reservation!

    // 4. COMMITTED REFUND REQUEST RETRY (Refund request retry with same key)
    // Book a slot to perform refund request
    const refundCourt = arrangeCourt(manifest, {
      testKey: '4_8_refund',
      venueId: venue1A.id,
      price: 100000,
    })
    const refundSlot = getFuturePlayDate(6, 17, 0)
    const refundCheckoutKey = `idemp_ref_chk_${Date.now()}`

    const refundCheckoutRes = (await withE2EApi(
      page,
      async ({ api }, { courtId, slotTime, key }) => {
        return await api.post(
          '/reservations/checkout',
          {
            court_id: courtId,
            slots: [slotTime],
          },
          {
            headers: { 'Idempotency-Key': key },
          },
        )
      },
      { courtId: refundCourt.court_id, slotTime: refundSlot.slotTime, key: refundCheckoutKey },
    )) as {
      reservation: { code: string }
    }
    const refundReservationCode = refundCheckoutRes.reservation.code

    // Confirm reservation
    const refundPayInfo = JSON.parse(
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
      use App\\Models\\Reservation;

      $reservation = Reservation::withoutGlobalScope('tenant_isolation')->where('code', '${refundReservationCode}')->firstOrFail();
      $attempt = OwnerPaymentAttempt::where('purpose', 'booking')->where('purpose_id', $reservation->code)->latest('id')->firstOrFail();
      echo json_encode([
        'amount' => (int) $attempt->amount,
        'reference' => $attempt->reference,
        'account_number' => $attempt->receivingConnection?->accountNumber(),
        'slot_id' => $reservation->slots()->first()?->id,
      ]);
    `,
        ],
        {
          cwd: backendDir,
          env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
          encoding: 'utf-8',
        },
      ),
    ) as {
      amount: number
      reference: string
      account_number: string
      slot_id: number
    }

    await sendSyntheticPaymentWebhook(manifest, {
      routingId: venue1A.routingId,
      secret: 'whsec_e2e_1a_' + manifest.runId,
      accountNumber: refundPayInfo.account_number || '0901000005',
      amount: refundPayInfo.amount,
      code: refundPayInfo.reference,
      transactionDate: formatVietnamDate(new Date()),
    })

    // Now customer requests refund with idempotency key
    const refundReqKey = `idemp_refund_${Date.now()}`
    const firstRefundReq = (await withE2EApi(
      page,
      async ({ api }, { reservationCode, slotId, key }) => {
        try {
          return await api.post(`/reservations/${reservationCode}/refund-requests`, {
            slot_ids: [slotId],
            reason: 'Thay đổi kế hoạch đột xuất',
            idempotency_key: key,
            recipient: {
              bank_code: 'MBBANK',
              account_name: 'NGUYEN VAN A',
              account_number: '0901000001',
            },
          })
        } catch (err: any) {
          throw new Error(
            `Refund request failed: ${err.status} ${JSON.stringify(err.response?.data || err.data || err.message)}`,
          )
        }
      },
      {
        reservationCode: refundReservationCode,
        slotId: refundPayInfo.slot_id,
        key: refundReqKey,
      },
    )) as {
      data: {
        id: number
        reference_code: string
        status: string
        amount: number
      }
    }

    expect(firstRefundReq.data.id).toBeDefined()
    const firstRefundId = firstRefundReq.data.id
    const firstRefundRef = firstRefundReq.data.reference_code

    // Client retries the exact same refund request with the same idempotency key
    const retryRefundReq = (await withE2EApi(
      page,
      async ({ api }, { reservationCode, slotId, key }) => {
        return await api.post(`/reservations/${reservationCode}/refund-requests`, {
          slot_ids: [slotId],
          reason: 'Thay đổi kế hoạch đột xuất',
          idempotency_key: key,
          recipient: {
            bank_code: 'MBBANK',
            account_name: 'NGUYEN VAN A',
            account_number: '0901000001',
          },
        })
      },
      {
        reservationCode: refundReservationCode,
        slotId: refundPayInfo.slot_id,
        key: refundReqKey,
      },
    )) as {
      data: {
        id: number
        reference_code: string
        status: string
        amount: number
      }
    }

    // Verify retry returns exact same refund obligation
    expect(retryRefundReq.data.id).toBe(firstRefundId)
    expect(retryRefundReq.data.reference_code).toBe(firstRefundRef)

    // DB check: exactly 1 refund obligation exists for this idempotency key
    const refundIdempCheckScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\OwnerRefundObligation;

      $count = OwnerRefundObligation::withoutGlobalScope('tenant_isolation')
        ->where('idempotency_key', '${refundReqKey}')
        ->count();

      echo json_encode(['obligation_count' => $count]);
    `
    const refundIdempState = JSON.parse(
      execFileSync('php', ['-r', refundIdempCheckScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { obligation_count: number }

    expect(refundIdempState.obligation_count).toBe(1) // No duplicate refund obligation!
  })
})
