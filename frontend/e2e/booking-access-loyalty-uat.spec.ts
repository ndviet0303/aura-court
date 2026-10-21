import { expect, test } from '@playwright/test'
import { arrangeCourt, grantLoyaltyPoints } from './runner/test-data.ts'
import { getTestManifest, getActorCredentials } from './runner/auth.ts'

test.describe('OpenSpec UAT: Hành trình đặt sân, Điểm tích lũy và Khả dụng @p0', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  test('Journey 1: Điều hướng & chuyển hướng khách vãng lai trên /login và /register', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')

    // 1.1 Unauthenticated access to /login displays login form
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await expect(page.getByRole('heading', { name: /Đăng nhập|Login/i })).toBeVisible({
      timeout: 15000,
    })
    await expect(page.locator('input[type="email"], input[name="email"]')).toBeVisible()
    await expect(page.locator('input[type="password"], input[name="password"]')).toBeVisible()

    // 1.2 Unauthenticated access to /register displays register mode
    await page.goto('/register', { waitUntil: 'domcontentloaded' })
    await expect(
      page.locator('input[name="password_confirmation"], input[id="register-password-confirm"]'),
    ).toBeVisible({ timeout: 15000 })

    // 1.3 Unauthenticated access to protected route /profile redirects to /login?redirect=/profile
    // (the wallet is capability-gated, so it is not the probe here)
    await page.goto('/profile', { waitUntil: 'domcontentloaded' })
    await expect(page).toHaveURL(/.*login.*redirect=.*profile/, { timeout: 15000 })

    // 1.4 Perform login as customer with non-demo credentials
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"].auth-submit-btn').click()

    // 1.5 Successfully redirected to /profile destination
    await expect(page).toHaveURL(/.*profile/, { timeout: 20000 })
    await expect(page.locator('.profile-view-page').first()).toBeVisible({ timeout: 20000 })

    // 1.6 Authenticated visitor visiting /login or /register is automatically redirected away
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await expect(page).not.toHaveURL(/\/login$/, { timeout: 15000 })

    await page.goto('/register', { waitUntil: 'domcontentloaded' })
    await expect(page).not.toHaveURL(/\/register$/, { timeout: 15000 })
  })

  test('Journey 2: Phân quyền quản trị / POS cho chủ sân và thu ngân, chặn truy cập admin nền tảng', async ({
    browser,
  }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const cashier = getActorCredentials(manifest, 'cashier1')

    // 2.1 Owner Journey (Non-demo Owner 1)
    const ownerContext = await browser.newContext()
    const ownerPage = await ownerContext.newPage()
    await ownerPage.addInitScript(() => sessionStorage.setItem('aura_demo_warned', 'true'))

    await ownerPage.goto('/login', { waitUntil: 'domcontentloaded' })
    await ownerPage.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await ownerPage.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await ownerPage.locator('button[type="submit"].auth-submit-btn').click()
    await expect(ownerPage).not.toHaveURL(/.*login/, { timeout: 20000 })

    // Task 3.1: Accessing /admin as owner routes to /admin/manage (current router behavior)
    await ownerPage.goto('/admin', { waitUntil: 'domcontentloaded' })
    await expect(ownerPage).toHaveURL(/.*admin\/manage/, { timeout: 20000 })

    // Task 3.1: Accessing /admin/manage directly as owner is permitted
    await ownerPage.goto('/admin/manage', { waitUntil: 'domcontentloaded' })
    await expect(ownerPage).toHaveURL(/.*admin\/manage/, { timeout: 15000 })

    // Task 3.1: Accessing /admin/venues as owner is permitted
    await ownerPage.goto('/admin/venues', { waitUntil: 'domcontentloaded' })
    await expect(ownerPage).toHaveURL(/.*admin\/venues/, { timeout: 15000 })

    // Accessing POS view as owner is permitted
    await ownerPage.goto('/admin/pos', { waitUntil: 'domcontentloaded' })
    await expect(ownerPage).toHaveURL(/.*admin\/pos/, { timeout: 15000 })
    await expect(ownerPage.locator('.pos-layout-wrapper, .pos-container').first()).toBeVisible({
      timeout: 15000,
    })

    // Task 3.1: True platform-only routes (payouts, kyc) are denied to owner and redirect to home
    await ownerPage.goto('/admin/payouts', { waitUntil: 'domcontentloaded' })
    await expect(ownerPage).not.toHaveURL(/.*admin\/payouts/, { timeout: 15000 })
    await expect(ownerPage).toHaveURL(/https?:\/\/[^/]+\/?$/)

    await ownerPage.goto('/admin/kyc', { waitUntil: 'domcontentloaded' })
    await expect(ownerPage).not.toHaveURL(/.*admin\/kyc/, { timeout: 15000 })
    await expect(ownerPage).toHaveURL(/https?:\/\/[^/]+\/?$/)

    await ownerContext.close()

    // 2.2 Cashier Journey (Non-demo Cashier 1)
    const cashierContext = await browser.newContext()
    const cashierPage = await cashierContext.newPage()
    await cashierPage.addInitScript(() => sessionStorage.setItem('aura_demo_warned', 'true'))

    await cashierPage.goto('/login', { waitUntil: 'domcontentloaded' })
    await cashierPage.locator('input[type="email"], input[name="email"]').fill(cashier.email)
    await cashierPage
      .locator('input[type="password"], input[name="password"]')
      .fill(cashier.password)
    await cashierPage.locator('button[type="submit"].auth-submit-btn').click()
    await expect(cashierPage).not.toHaveURL(/.*login/, { timeout: 20000 })

    // Accessing /admin as cashier routes directly to POS
    await cashierPage.goto('/admin', { waitUntil: 'domcontentloaded' })
    await expect(cashierPage).toHaveURL(/.*admin\/pos/, { timeout: 15000 })

    // Accessing /admin/pos as cashier is permitted
    await cashierPage.goto('/admin/pos', { waitUntil: 'domcontentloaded' })
    await expect(cashierPage).toHaveURL(/.*admin\/pos/, { timeout: 15000 })
    await expect(cashierPage.locator('.pos-layout-wrapper, .pos-container').first()).toBeVisible({
      timeout: 15000,
    })

    // Management routes are denied to cashier (redirect to home)
    await cashierPage.goto('/admin/manage', { waitUntil: 'domcontentloaded' })
    await expect(cashierPage).not.toHaveURL(/.*admin\/manage/, { timeout: 15000 })
    await expect(cashierPage).toHaveURL(/https?:\/\/[^/]+\/?$/)

    await cashierPage.goto('/admin/venues', { waitUntil: 'domcontentloaded' })
    await expect(cashierPage).not.toHaveURL(/.*admin\/venues/, { timeout: 15000 })
    await expect(cashierPage).toHaveURL(/https?:\/\/[^/]+\/?$/)

    await cashierPage.goto('/admin/organization', { waitUntil: 'domcontentloaded' })
    await expect(cashierPage).not.toHaveURL(/.*admin\/organization/, { timeout: 15000 })
    await expect(cashierPage).toHaveURL(/https?:\/\/[^/]+\/?$/)

    await cashierContext.close()
  })

  test('Journey 3: Lịch khả dụng theo sân: chọn sân, hiển thị slot rõ ràng, đổi sân', async ({
    page,
  }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' })

    const availabilitySection = page.locator('#availability, .availability-container').first()
    await expect(availabilitySection).toBeVisible({ timeout: 25000 })

    // Court picker & calendar controls
    const nearbyCourts = availabilitySection.locator('.availability-court-picker, .court-selector')
    if (await nearbyCourts.isVisible()) {
      const courtChips = nearbyCourts.locator('.court-chip, button')
      if ((await courtChips.count()) > 1) {
        const secondChip = courtChips.nth(1)
        await secondChip.click()
        await expect(secondChip).toHaveClass(/active|selected/, { timeout: 15000 })
      }
    }

    // Availability slots are displayed
    const slotList = availabilitySection.locator('.slot, .time-slot')
    await expect(slotList.first()).toBeVisible({ timeout: 25000 })
  })

  test('Journey 4: Trang đặt sân không còn bộ chọn điểm tích lũy, kể cả khi khách có điểm', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const court = arrangeCourt(manifest, { testKey: 'uat_journey_4', price: 100000 })

    // Grant deterministic 500 loyalty points to non-demo customer
    const customerId = customer.id || manifest.fixtures?.users.customer?.id
    if (customerId) {
      grantLoyaltyPoints(manifest, { userId: customerId, points: 500 })
    }
    // 4.1 Login as non-demo customer
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"].auth-submit-btn').click()
    await expect(page).not.toHaveURL(/.*login/, { timeout: 20000 })

    // 4.2 Select an available slot on the court schedule
    await page.goto(`/courts/${court.court_id}/schedule`, { waitUntil: 'domcontentloaded' })
    const targetCard = page.locator('.date-carousel .date-card-btn').nth(1)
    await targetCard.waitFor({ state: 'visible', timeout: 20000 })
    await targetCard.click()
    await expect(targetCard).toHaveClass(/active/, { timeout: 10000 })
    const availableSlot = page.locator('.slots-grid .slot-cell-btn.is-available').first()
    await expect(availableSlot).toBeVisible({ timeout: 25000 })
    await availableSlot.click()

    // 4.3 The quoted summary is ready to book, and offers no point selector
    const summary = page.locator('.schedule-summary-card')
    await expect(summary.locator('.btn-checkout')).toBeEnabled({ timeout: 15000 })
    await expect(page.locator('.loyalty-point-selector')).toHaveCount(0)
  })
})
