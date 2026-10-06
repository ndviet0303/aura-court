import { getCanonicalBackendEnv } from './runner/test-data.ts'
import fs from 'node:fs'
import { execFileSync } from 'node:child_process'
import path from 'node:path'
import { expect, test } from '@playwright/test'
import { getTestManifest, getFuturePlayDate, arrangeCourt } from './runner/test-data.ts'

test.describe('P0: Tìm kiếm sân, Lịch khả dụng & Cách ly tồn kho @p0', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  test('3.4.1: Tìm kiếm cơ sở và điều hướng tới chi tiết sân và bảng lịch', async ({ page }) => {
    // 1. Visit /courts catalog
    await page.goto('/courts', { waitUntil: 'domcontentloaded' })
    await expect(page.locator('h1, h2').filter({ hasText: /sân|court/i }).first()).toBeVisible({ timeout: 20000 })

    // Court cards are rendered
    const detailBtn = page.locator('.btn-detail, a:has-text("Chi tiết"), a:has-text("Detail")').first()
    await expect(detailBtn).toBeVisible({ timeout: 20000 })

    // 2. Click detail button to navigate to detail
    await detailBtn.click()
    await expect(page).toHaveURL(/\/courts\/[^/]+$/, { timeout: 15000 })
    await expect(page.locator('h1, h2, .court-title').first()).toBeVisible({ timeout: 15000 })

    // 3. Navigate to schedule via sticky booking card
    const bookNowBtn = page.locator('.btn-book-now, a[href*="/schedule"]').first()
    await expect(bookNowBtn).toBeVisible({ timeout: 15000 })
    await bookNowBtn.click()
    await expect(page).toHaveURL(/.*\/schedule/, { timeout: 15000 })
    await expect(page.locator('.schedule-split-layout, .schedule-view-page').first()).toBeVisible({ timeout: 20000 })
  })

  test('3.4.2: Chọn slot, đổi ngày và đổi sân hủy bỏ lựa chọn cũ', async ({ page }) => {
    const manifest = getTestManifest()
    const venue1A = manifest.fixtures?.organizations.org1?.venue1A
    const courtId = venue1A?.courts?.[0] || 1
    // 1. Open schedule page
    await page.goto(`/courts/${courtId}/schedule`, { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.schedule-split-layout, .schedule-view-page').first()).toBeVisible({ timeout: 20000 })

    // Select future date on the carousel to get available slots
    const dateCards = page.locator('.date-carousel .date-card-btn')
    const targetDateCard = dateCards.nth(1)
    await targetDateCard.waitFor({ state: 'visible', timeout: 15000 })
    await targetDateCard.click()
    await expect(targetDateCard).toHaveClass(/active/, { timeout: 10000 })

    // 2. Select an available slot on tomorrow
    const slotList = page.locator('.slots-grid .slot-cell-btn.is-available')
    await expect(slotList.first()).toBeVisible({ timeout: 25000 })
    await slotList.first().click()
    // Summary reflects 1 slot selected
    const summaryCard = page.locator('.schedule-summary-card, .summary-col')
    await expect(summaryCard.locator('.badge-count')).toContainText(/1/, { timeout: 10000 })
    // 3. Switch date via next day button or carousel day item
    const dayAfterTomorrow = dateCards.nth(2)
    await dayAfterTomorrow.waitFor({ state: 'visible', timeout: 15000 })
    await dayAfterTomorrow.click()
    await expect(dayAfterTomorrow).toHaveClass(/active/, { timeout: 10000 })

    // Stale selection MUST be discarded on date switch
    await expect(page.locator('.schedule-summary-card .badge-count')).not.toBeVisible({ timeout: 10000 })
    await expect(page.locator('.slots-grid .slot-cell-btn.is-selected')).toHaveCount(0)

    // 4. Select a slot on the new date
    const newSlotList = page.locator('.slots-grid .slot-cell-btn.is-available').first()
    await expect(newSlotList).toBeVisible({ timeout: 25000 })
    await newSlotList.click()
    await expect(summaryCard.locator('.badge-count')).toContainText(/1/, { timeout: 10000 })
    // Switch court via court selector
    const otherCourtBtn = page.locator('.court-selector-container .court-card-btn:not(.active)').first()
    await expect(otherCourtBtn).toBeVisible({ timeout: 10000 })
    const otherCourtName = await otherCourtBtn.locator('.c-name').innerText()
    await otherCourtBtn.click()
    await expect(page.locator('.court-card-btn.active .c-name')).toHaveText(otherCourtName)
    await expect(page.locator('.slots-grid .slot-cell-btn').first()).toBeVisible({ timeout: 20000 })

    // Stale selection MUST be discarded on court switch
    await expect(page.locator('.schedule-summary-card .badge-count')).not.toBeVisible({ timeout: 10000 })
    await expect(page.locator('.slots-grid .slot-cell-btn.is-selected')).toHaveCount(0)
  })

  test('3.4.3: Sân A kín lịch KHÔNG khóa Sân B (cách ly tồn kho độc lập)', async ({ page }) => {
    const manifest = getTestManifest()
    const venue1A = manifest.fixtures?.organizations.org1?.venue1A
    const courtA = arrangeCourt(manifest, { testKey: '3_4_3_courtA', venueId: venue1A?.id })
    const courtB = arrangeCourt(manifest, { testKey: '3_4_3_courtB', venueId: venue1A?.id })
    const court1A1Id = courtA.court_id
    const court1A2Id = courtB.court_id
    expect(court1A1Id).not.toBe(court1A2Id)
    const future = getFuturePlayDate(3, 11) // 3 days ahead, 11:00
    const targetSlotIso = future.slotTime
    // 1. Create a real hold/booking on Court 1-A-1 for 11:00 slot
    const customer = manifest.fixtures?.users.customer
    const customerId = customer?.id
    if (!customerId) {
      throw new Error('E2E fixture missing: customer ID')
    }

    const bookScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Domain\\Booking\\DTOs\\CreateBookingData;
      use App\\Domain\\Booking\\Actions\\CreateBooking;

      $dto = new CreateBookingData(
        userId: ${customerId},
        courtId: ${court1A1Id},
        slots: ['${targetSlotIso}'],
        couponCode: null,
        loyaltyPoints: null,
      );

      $booking = app(CreateBooking::class)->execute($dto);
      echo json_encode(['booking_code' => $booking->code, 'status' => $booking->status]);
    `

    const backendDir = path.resolve(import.meta.dirname, '../../backend')
    const backendEnv = getCanonicalBackendEnv(manifest)
    const phpBin = fs.existsSync('/opt/homebrew/bin/php') ? '/opt/homebrew/bin/php' : 'php'

    const bookOut = execFileSync(phpBin, ['-r', bookScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })
    const bookResult = JSON.parse(bookOut) as { booking_code: string; status: string }
    expect(bookResult.booking_code).toBeTruthy()

    // 2. Query Court 1-A-1 availability: 11:00 slot is booked/unavailable
    const court1Res = await page.request.get(
      `${manifest.backend.url}/api/v1/availability?court_id=${court1A1Id}&date=${future.dateString}`
    )
    expect(court1Res.ok()).toBe(true)
    const court1Slots = (await court1Res.json()) as Array<{ startsAt: string; available: boolean }>
    const court1Slot11 = court1Slots.find((s) => s.startsAt.includes('11:00'))
    expect(court1Slot11?.available).toBe(false)

    // 3. Query Court 1-A-2 availability: 11:00 slot is completely AVAILABLE (independent inventory isolation)
    const court2Res = await page.request.get(
      `${manifest.backend.url}/api/v1/availability?court_id=${court1A2Id}&date=${future.dateString}`
    )
    expect(court2Res.ok()).toBe(true)
    const court2Slots = (await court2Res.json()) as Array<{ startsAt: string; available: boolean }>
    const court2Slot11 = court2Slots.find((s) => s.startsAt.includes('11:00'))
    expect(court2Slot11?.available).toBe(true)

    // 4. Verify in the UI: Court 1-A-2 schedule shows slots available on future date
    await page.goto(`/courts/${court1A2Id}/schedule`, { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.schedule-split-layout, .schedule-view-page').first()).toBeVisible({ timeout: 20000 })

    const dateCards = page.locator('.date-carousel .date-card-btn')
    const futureDateBtn = dateCards.nth(3)
    await futureDateBtn.waitFor({ state: 'visible', timeout: 15000 })
    await futureDateBtn.click()
    await expect(futureDateBtn).toHaveClass(/active/, { timeout: 10000 })

    const slotList = page.locator('.slots-grid .slot-cell-btn.is-available')
    await expect(slotList.first()).toBeVisible({ timeout: 20000 })
  })

  test('3.4.4: Phản hồi API đến muộn ngoài thứ tự không ghi đè dữ liệu hiển thị hiện tại', async ({ page }) => {
    const manifest = getTestManifest()
    const venue1A = manifest.fixtures?.organizations.org1?.venue1A
    const court = arrangeCourt(manifest, { testKey: '3_4_4_late', venueId: venue1A?.id })
    const courtId = court.court_id

    await page.goto(`/courts/${courtId}/schedule`, { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.schedule-split-layout, .schedule-view-page').first()).toBeVisible({ timeout: 20000 })

    const dateCards = page.locator('.date-carousel .date-card-btn')
    const count = await dateCards.count()
    expect(count).toBeGreaterThanOrEqual(3)

    const card1 = dateCards.nth(1)
    const card2 = dateCards.nth(2)

    // Resolve the requested court and finish initial availability before delaying date changes.
    // Otherwise the first intercepted request could be initialization for date2.
    await expect(page.locator('.slots-grid .slot-cell-btn').first()).toBeVisible({ timeout: 20000 })

    const { promise: date1Promise, resolve: resolveDate1Response } = Promise.withResolvers<void>()
    let date1Intercepted = false

    // Intercept availability requests: hold the first request (date 1) until date 2 finishes
    await page.route('**/api/v1/availability*', async (route) => {
      if (!date1Intercepted) {
        date1Intercepted = true
        await date1Promise
        // Fulfill date1 with synthetic single slot tagged with price 999999
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify([
            {
              startsAt: '2026-12-31T06:00:00+07:00',
              endsAt: '2026-12-31T07:00:00+07:00',
              price: 999999,
              available: true,
            },
          ]),
        })
      } else {
        // Normal fulfill for date 2
        await route.continue()
      }
    })

    // Click card1 (starts slow request date1)
    await card1.click()

    await expect.poll(() => date1Intercepted).toBe(true)

    // Immediately click card2 (starts fast request date2)
    await card2.click()
    await expect(card2).toHaveClass(/active/, { timeout: 10000 })

    // Wait for date2 slots to render
    const slotCells = page.locator('.slots-grid .slot-cell-btn')
    await expect(slotCells.first()).toBeVisible({ timeout: 15000 })

    // Snapshot date2's slot count before releasing date1.
    // Real backend date2 returns a full day's slots (>5), while date1's synthetic response has only 1 slot.
    const countBefore = await slotCells.count()
    expect(countBefore).toBeGreaterThan(5)

    // Now release the held date1 response (arrives out-of-order, later than date 2)
    resolveDate1Response()

    // Give time for any rogue state mutation to settle
    await page.waitForTimeout(500)

    // Verify format- and locale-independent:
    // 1. Slot count MUST remain unchanged (still date2's ~16 slots, NOT overwritten by date1's 1 slot)
    const countAfter = await slotCells.count()
    expect(countAfter).toBe(countBefore)

    // 2. Sentinel price from date1 (999999) MUST NOT be present under any locale formatting (999.999 or 999,999)
    const stalePriceSlot = page.locator('.slots-grid .s-price').filter({ hasText: /999[.,]999|999999/ })
    await expect(stalePriceSlot).toHaveCount(0)

    // 3. Current card remains date2
    await expect(card2).toHaveClass(/active/)
  })
})
