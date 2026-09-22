import { expect, test } from '@playwright/test'

test.describe('OpenSpec UAT: Booking Access, Loyalty, and Availability Journeys', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  test('Journey 1: Guest navigation & redirect on /login and /register', async ({ page }) => {
    // 1.1 Unauthenticated access to /login displays login form
    await page.goto('/login')
    await expect(page.getByRole('heading', { name: /Đăng nhập|Login/i })).toBeVisible({ timeout: 15000 })
    await expect(page.locator('input[type="email"], input[name="email"]')).toBeVisible()
    await expect(page.locator('input[type="password"], input[name="password"]')).toBeVisible()

    // 1.2 Unauthenticated access to /register displays register mode
    await page.goto('/register')
    await expect(page.locator('input[name="password_confirmation"], input[id="register-password-confirm"]')).toBeVisible({ timeout: 15000 })

    // 1.3 Unauthenticated access to protected route /wallet redirects to /login?redirect=/wallet
    await page.goto('/wallet')
    await expect(page).toHaveURL(/.*login.*redirect=.*wallet/, { timeout: 15000 })

    // 1.4 Perform login as customer
    await page.locator('input[type="email"], input[name="email"]').fill('customer@demo.auracourt.vn')
    await page.locator('input[type="password"], input[name="password"]').fill('Demo12345!')
    await page.locator('button[type="submit"].auth-submit-btn').click()

    // 1.5 Successfully redirected to /wallet destination
    await expect(page).toHaveURL(/.*wallet/, { timeout: 20000 })
    await expect(page.locator('.wallet-view-page').first()).toBeVisible({ timeout: 20000 })

    // 1.6 Authenticated visitor visiting /login or /register is automatically redirected away
    await page.goto('/login')
    await expect(page).not.toHaveURL(/\/login$/, { timeout: 15000 })

    await page.goto('/register')
    await expect(page).not.toHaveURL(/\/register$/, { timeout: 15000 })

    await page.screenshot({ path: 'test-results/uat-screenshots/journey1_guest_auth.png' })
  })

  test('Journey 2: Scoped admin / POS access for owner and cashier, denial on platform admin routes', async ({ browser }) => {
    // 2.1 Owner Journey (Org ID 6 - Aura Sports Marketplace Club)
    const ownerContext = await browser.newContext()
    const ownerPage = await ownerContext.newPage()
    await ownerPage.addInitScript(() => sessionStorage.setItem('aura_demo_warned', 'true'))

    await ownerPage.goto('/login')
    await ownerPage.locator('input[type="email"], input[name="email"]').fill('owner@demo.auracourt.vn')
    await ownerPage.locator('input[type="password"], input[name="password"]').fill('Demo12345!')
    await ownerPage.locator('button[type="submit"].auth-submit-btn').click()
    await expect(ownerPage).not.toHaveURL(/.*login/, { timeout: 20000 })

    // Accessing /admin as owner routes to /admin/organization
    await ownerPage.goto('/admin')
    await expect(ownerPage).toHaveURL(/.*admin\/organization/, { timeout: 20000 })
    await expect(ownerPage.locator('.organization-owner-view, .org-hero-card').first()).toBeVisible({ timeout: 20000 })

    // Accessing POS view as owner is permitted
    await ownerPage.goto('/admin/pos')
    await expect(ownerPage).toHaveURL(/.*admin\/pos/, { timeout: 15000 })
    await expect(ownerPage.locator('.pos-layout-wrapper').first()).toBeVisible({ timeout: 15000 })
    await ownerPage.screenshot({ path: 'test-results/uat-screenshots/journey2_owner_pos.png' })

    // Platform-only routes are denied and redirect away (e.g. to /)
    await ownerPage.goto('/admin/manage')
    await expect(ownerPage).not.toHaveURL(/.*admin\/manage/, { timeout: 15000 })
    await expect(ownerPage).toHaveURL(/http:\/\/[^/]+\/?$/)

    await ownerPage.goto('/admin/venues')
    await expect(ownerPage).not.toHaveURL(/.*admin\/venues/, { timeout: 15000 })
    await expect(ownerPage).toHaveURL(/http:\/\/[^/]+\/?$/)

    await ownerContext.close()

    // 2.2 Cashier Journey (Org ID 6 - Aura Sports Marketplace Club)
    const cashierContext = await browser.newContext()
    const cashierPage = await cashierContext.newPage()
    await cashierPage.addInitScript(() => sessionStorage.setItem('aura_demo_warned', 'true'))

    await cashierPage.goto('/login')
    await cashierPage.locator('input[type="email"], input[name="email"]').fill('cashier@demo.auracourt.vn')
    await cashierPage.locator('input[type="password"], input[name="password"]').fill('Demo12345!')
    await cashierPage.locator('button[type="submit"].auth-submit-btn').click()
    await expect(cashierPage).not.toHaveURL(/.*login/, { timeout: 20000 })

    // Accessing /admin/pos as cashier is permitted
    await cashierPage.goto('/admin/pos')
    await expect(cashierPage).toHaveURL(/.*admin\/pos/, { timeout: 15000 })
    await expect(cashierPage.locator('.pos-layout-wrapper').first()).toBeVisible({ timeout: 15000 })
    await cashierPage.screenshot({ path: 'test-results/uat-screenshots/journey2_cashier_pos.png' })

    // Accessing /admin as cashier routes directly to POS
    await cashierPage.goto('/admin')
    await expect(cashierPage).toHaveURL(/.*admin\/pos/, { timeout: 15000 })

    // Organization management is denied to cashier (owner-only)
    await cashierPage.goto('/admin/organization')
    await expect(cashierPage).not.toHaveURL(/.*admin\/organization/, { timeout: 15000 })
    await expect(cashierPage).toHaveURL(/http:\/\/[^/]+\/?$/)

    // Platform-only routes are denied to cashier
    await cashierPage.goto('/admin/manage')
    await expect(cashierPage).not.toHaveURL(/.*admin\/manage/, { timeout: 15000 })
    await expect(cashierPage).toHaveURL(/http:\/\/[^/]+\/?$/)

    await cashierPage.goto('/admin/venues')
    await expect(cashierPage).not.toHaveURL(/.*admin\/venues/, { timeout: 15000 })
    await expect(cashierPage).toHaveURL(/http:\/\/[^/]+\/?$/)

    await cashierContext.close()
  })

  test('Journey 3: Per-court availability calendar: court selection, slot clarity (19:00-20:00 & 20:00-21:00), switching courts', async ({ page }) => {
    await page.goto('/')

    const availabilitySection = page.locator('#availability')
    await expect(availabilitySection).toBeVisible({ timeout: 25000 })

    // Beside-the-slots controls: nearby court picker & date picker
    const nearbyCourts = availabilitySection.locator('.availability-court-picker')
    await expect(nearbyCourts).toBeVisible({ timeout: 20000 })

    // Initial court (Court 1: Sân 1 - Thảm BWF Tiêu Chuẩn)
    await expect(availabilitySection.locator('.availability-title')).toBeVisible({ timeout: 20000 })

    // Slot clarity: verify 19:00 - 20:00 and 20:00 - 21:00 formatting
    const slot19Court1 = availabilitySection.locator('.slot').filter({ hasText: /19:00.*20:00/ }).first()
    const slot20Court1 = availabilitySection.locator('.slot').filter({ hasText: /20:00.*21:00/ }).first()

    await expect(slot19Court1).toBeVisible({ timeout: 25000 })
    await expect(slot20Court1).toBeVisible({ timeout: 25000 })

    // On Court 1, these two slots are booked/unavailable (disabled)
    await expect(slot19Court1).toBeDisabled()
    await expect(slot20Court1).toBeDisabled()

    // Switch court to Court 2 (Sân 2 - Tiêu Chuẩn) via nearby court chip
    const court2Chip = nearbyCourts.locator('.court-chip').filter({ hasText: /Sân 2/i }).first()
    await expect(court2Chip).toBeVisible({ timeout: 15000 })
    await court2Chip.click()

    // Verify Court 2 chip is active and heading updates
    await expect(court2Chip).toHaveClass(/active/, { timeout: 15000 })
    await expect(availabilitySection.locator('.availability-title')).toContainText(/Sân 2/i, { timeout: 15000 })

    // On Court 2, 19:00 - 20:00 and 20:00 - 21:00 slots are available and selectable
    const slot19Court2 = availabilitySection.locator('.slot').filter({ hasText: /19:00.*20:00/ }).first()
    await expect(slot19Court2).toBeVisible({ timeout: 25000 })
    await expect(slot19Court2).toBeEnabled({ timeout: 25000 })

    // Select slot 19:00 - 20:00 on Court 2
    await slot19Court2.click()
    await expect(slot19Court2).toHaveClass(/selected/)

    // Floating booking summary appears with selected slot details
    const floatingDock = page.locator('.floating-booking')
    await expect(floatingDock).toBeVisible({ timeout: 15000 })
    await expect(floatingDock).toContainText(/1 khung giờ|1 slot/i)

    await page.screenshot({ path: 'test-results/uat-screenshots/journey3_court_availability.png' })
  })

  test('Journey 4: Loyalty point selection preview, balance, discount amount calculation', async ({ page }) => {
    // 4.1 Login as customer with loyalty points
    await page.goto('/login')
    await page.locator('input[type="email"], input[name="email"]').fill('customer@demo.auracourt.vn')
    await page.locator('input[type="password"], input[name="password"]').fill('Demo12345!')
    await page.locator('button[type="submit"].auth-submit-btn').click()
    await expect(page).not.toHaveURL(/.*login/, { timeout: 20000 })

    // 4.2 Wait for home availability and select available slot on Court 2
    const availabilitySection = page.locator('#availability')
    await expect(availabilitySection).toBeVisible({ timeout: 25000 })

    const court2Chip = availabilitySection.locator('.court-chip').filter({ hasText: /Sân 2/i }).first()
    await expect(court2Chip).toBeVisible({ timeout: 20000 })
    await court2Chip.click()
    await expect(court2Chip).toHaveClass(/active/, { timeout: 15000 })

    const slot19Court2 = availabilitySection.locator('.slot').filter({ hasText: /19:00.*20:00/ }).first()
    await expect(slot19Court2).toBeVisible({ timeout: 25000 })
    await expect(slot19Court2).toBeEnabled({ timeout: 25000 })
    await slot19Court2.click()

    // 4.3 Loyalty Point Selector is present in the floating dock
    const loyaltySelector = page.locator('.loyalty-point-selector')
    await expect(loyaltySelector).toBeVisible({ timeout: 20000 })

    // 4.4 Balance badge displays available points: 500 (waiting for backend preview)
    const balanceBadge = loyaltySelector.locator('.loyalty-balance-badge')
    await expect(balanceBadge).toBeVisible({ timeout: 20000 })
    await expect(balanceBadge).toContainText('500', { timeout: 20000 })

    // 4.5 Enter points: 100 points
    const pointsInput = loyaltySelector.locator('.loyalty-input')
    await expect(pointsInput).toBeVisible({ timeout: 15000 })
    await pointsInput.fill('100')
    await pointsInput.press('Enter')

    // 4.6 Authoritative preview calculation renders discount and remaining payable amount
    const breakdown = loyaltySelector.locator('.loyalty-breakdown')
    await expect(breakdown).toBeVisible({ timeout: 20000 })

    const discountValue = breakdown.locator('.discount-value')
    await expect(discountValue).toBeVisible({ timeout: 20000 })
    // 100 points = 5,000 VND discount
    await expect(discountValue).toContainText(/5[.,]000/, { timeout: 20000 })

    const remainingValue = breakdown.locator('.remaining-value')
    await expect(remainingValue).toBeVisible({ timeout: 20000 })

    await page.screenshot({ path: 'test-results/uat-screenshots/journey4_loyalty_preview.png' })

    // 4.7 Clear points selection resets breakdown
    const clearBtn = loyaltySelector.locator('.btn-quick.clear')
    await expect(clearBtn).toBeVisible({ timeout: 15000 })
    await clearBtn.click()

    await expect(breakdown).not.toBeVisible({ timeout: 15000 })
  })
})
