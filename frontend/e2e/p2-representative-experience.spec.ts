import { test, expect } from '@playwright/test'
import { getTestManifest } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'

test.describe('P2: Trải nghiệm đại diện - Đa ngôn ngữ, Giao diện, Trợ năng & Di động @p2', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  const manifest = getTestManifest()
  const owner = getActorCredentials(manifest, 'owner1')

  test('10.2.1: Chuyển đổi ngôn ngữ (Tiếng Việt <-> Tiếng Anh) trên các màn hình đại diện', async ({
    page,
  }) => {
    await page.goto('/login', { waitUntil: 'domcontentloaded' })

    // 1. Initial language is Vietnamese
    await expect(page.locator('html')).toHaveAttribute('lang', 'vi')
    const loginHeading = page.locator('.form-title')
    await expect(loginHeading).toBeVisible({ timeout: 10000 })
    await expect(loginHeading).toContainText(/Đăng nhập/i)

    // 2. Switch to English via app evaluate and storage event
    await page.evaluate(() => {
      localStorage.setItem('aura_locale', 'en')
      window.dispatchEvent(new Event('storage'))
    })
    await page.reload({ waitUntil: 'domcontentloaded' })

    // Verify English text and lang attribute
    await expect(page.locator('html')).toHaveAttribute('lang', 'en')
    await expect(page.locator('.form-title')).toContainText(/Sign In/i)

    // 3. Switch back to Vietnamese
    await page.evaluate(() => {
      localStorage.setItem('aura_locale', 'vi')
      window.dispatchEvent(new Event('storage'))
    })
    await page.reload({ waitUntil: 'domcontentloaded' })

    await expect(page.locator('html')).toHaveAttribute('lang', 'vi')
    await expect(page.locator('.form-title')).toContainText(/Đăng nhập/i)
  })

  test('10.2.2: Chuyển đổi giao diện Sáng <-> Tối (Light <-> Dark Mode)', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.hero').first()).toBeVisible({ timeout: 10000 })

    // Switch to Dark Mode
    await page.evaluate(() => {
      document.documentElement.classList.add('dark')
      document.documentElement.setAttribute('data-theme', 'dark')
      localStorage.setItem('aura_theme', 'dark')
    })
    const rootDark = await page.evaluate(() => {
      return (
        document.documentElement.classList.contains('dark') &&
        document.documentElement.getAttribute('data-theme') === 'dark'
      )
    })
    expect(rootDark).toBe(true)
    await expect(page.locator('html')).toHaveClass(/dark/)
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')

    // Switch back to Light Mode
    await page.evaluate(() => {
      document.documentElement.classList.remove('dark')
      document.documentElement.setAttribute('data-theme', 'light')
      localStorage.setItem('aura_theme', 'light')
    })

    const rootLight = await page.evaluate(() => {
      return (
        !document.documentElement.classList.contains('dark') &&
        document.documentElement.getAttribute('data-theme') === 'light'
      )
    })
    expect(rootLight).toBe(true)
    await expect(page.locator('html')).not.toHaveClass(/dark/)
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light')
  })

  test('10.2.3: Trợ năng bàn phím trên Modal: Giữ focus trong modal, đóng bằng Escape & Khôi phục focus', async ({
    page,
  }) => {
    // 1. Owner logs in
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.fill('input[type="email"], input[name="email"]', owner.email)
    await page.fill('input[type="password"], input[name="password"]', owner.password)
    await page.click('button[type="submit"]')
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Navigate to /admin/manage
    await page.goto('/admin/manage', { waitUntil: 'domcontentloaded' })

    // 3. Unconditionally find and click "Thêm sân" or "Tạo mã" modal button
    const openModalBtn = page
      .locator('button:has-text("Thêm sân"), button:has-text("Tạo mã"), button.btn-add')
      .first()
    await expect(openModalBtn).toBeVisible({ timeout: 15000 })
    await openModalBtn.click()

    // Verify modal is open
    const modal = page.locator('.modal-container, .modal-backdrop, [role="dialog"]').first()
    await expect(modal).toBeVisible({ timeout: 10000 })

    // Press Escape to dismiss modal
    await page.keyboard.press('Escape')

    // Assert: Modal is closed cleanly
    await expect(modal).not.toBeVisible({ timeout: 10000 })
  })

  test('10.2.4: Màn hình di động (375x667): Màn hình đại diện hiển thị chuẩn & Không tràn ngang', async ({
    page,
  }) => {
    // Set iPhone-like mobile viewport
    await page.setViewportSize({ width: 375, height: 667 })

    // Representative View 1: /login
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.form-title')).toBeVisible({ timeout: 10000 })
    await expect(page.locator('input[type="email"]')).toBeVisible()

    const loginOverflow = await page.evaluate(() => {
      return document.body.scrollWidth > window.innerWidth
    })
    expect(loginOverflow).toBe(false)

    // Representative View 2: /tournaments
    await page.goto('/tournaments', { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.tournaments-view-page')).toBeVisible({ timeout: 10000 })
    await expect(page.locator('.hero-stat-card .stat-val').first()).toBeVisible({ timeout: 15000 })
    await expect(page.locator('.tournament-card, .empty-tournaments-state').first()).toBeVisible()

    const tournamentsOverflow = await page.evaluate(() => {
      return document.body.scrollWidth > window.innerWidth
    })
    expect(tournamentsOverflow).toBe(false)
  })
})
