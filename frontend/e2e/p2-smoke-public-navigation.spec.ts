import { test, expect } from '@playwright/test'
import { getTestManifest } from './runner/test-data.ts'

test.describe('P2: Smoke Điều hướng công khai, Pháp lý & Chỉ đường @p2 @smoke', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  const manifest = getTestManifest()
  const venueSlug = manifest.fixtures?.organizations.org1.venue1A?.slug || 'san-cau-long-quan-1'

  test('10.3.1: Điều hướng trang chủ và khám phá sân công khai (Smoke)', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' })

    // Verify main landmarks
    await expect(page.locator('nav, header').first()).toBeVisible({ timeout: 10000 })
    await expect(page.locator('main, section').first()).toBeVisible({ timeout: 10000 })
  })

  test('10.3.2: Trang pháp lý: Điều khoản dịch vụ & Chính sách bảo mật (SUP-08)', async ({ page }) => {
    // 1. Terms of Service (/terms)
    await page.goto('/terms', { waitUntil: 'domcontentloaded' })
    await expect(page.locator('h1, h2, .page-title').first()).toBeVisible({ timeout: 10000 })

    // 2. Privacy Policy (/privacy)
    await page.goto('/privacy', { waitUntil: 'domcontentloaded' })
    await expect(page.locator('h1, h2, .page-title').first()).toBeVisible({ timeout: 10000 })
  })

  test('10.3.3: Trang giới thiệu: Về chúng tôi, Tầm nhìn & Liên hệ (/about) (SUP-08)', async ({ page }) => {
    await page.goto('/about', { waitUntil: 'domcontentloaded' })
    await expect(page.locator('h1, h2, .about-title, .page-title').first()).toBeVisible({ timeout: 10000 })
  })

  test('10.3.4: Đường dẫn tùy biến 404 & Điều hướng về trang chủ (SUP-08)', async ({ page }) => {
    // Navigate to a guaranteed non-existent URL
    await page.goto('/non-existent-page-url-xyz-404', { waitUntil: 'domcontentloaded' })

    // Verify 404 page elements
    const notFoundSection = page.locator('.not-found, .page-shell.not-found, [class*="not-found"]').first()
    await expect(notFoundSection).toBeVisible({ timeout: 10000 })

    // Verify presence of return home button
    const backHomeBtn = page.locator('a[href="/"], .btn:has-text("Trang chủ"), a:has-text("Home")').first()
    await expect(backHomeBtn).toBeVisible({ timeout: 10000 })

    // Click return home and verify navigation
    await backHomeBtn.click()
    await expect(page).toHaveURL(/\/$/, { timeout: 10000 })
    await expect(page.locator('.hero').first()).toBeVisible({ timeout: 10000 })
    expect(page.url()).not.toContain('non-existent')
  })

  test('10.3.5: Trang chỉ đường tới cơ sở & Thao tác bản đồ GPS / Maps (SUP-07)', async ({ page }) => {
    // Navigate to directions page for the seeded venue
    await page.goto(`/courts/${venueSlug}/directions`, { waitUntil: 'domcontentloaded' })

    // Verify venue directions content layout has loaded
    const directionsContent = page.locator('.directions-content-layout')
    await expect(directionsContent).toBeVisible({ timeout: 15000 })

    // Verify action buttons (Copy address, Open Maps, Share location)
    const mapActionBtn = page.locator('button:has-text("Bản đồ"), button:has-text("Google Maps"), button:has-text("Chỉ đường"), button:has-text("Sao chép")')
    await expect(mapActionBtn.first()).toBeVisible({ timeout: 10000 })
  })
})
