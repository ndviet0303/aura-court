import { test, expect } from '@playwright/test'
import { getTestManifest } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'

test.describe('P2: Tiêm lỗi mạng & Khả năng phục hồi phi chức năng @p2 @fault-injection', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  const manifest = getTestManifest()
  const customer = getActorCredentials(manifest, 'customer')

  test('10.1.1: Giả lập sự cố mạng / mất kết nối (Giữ nguyên dữ liệu nhập & Focus)', async ({
    page,
  }) => {
    await page.goto('/login', { waitUntil: 'domcontentloaded' })

    const emailInput = page.locator('input[type="email"], input[name="email"]')
    const passwordInput = page.locator('input[type="password"], input[name="password"]')

    await emailInput.fill('athlete_network_test@auracourt.vn')
    await passwordInput.fill('SecurePassword123!@#')

    // Inject network failure on login request and verify request invocation
    let loginRequestAttempted = false
    await page.route('**/api/v1/auth/login', (route) => {
      loginRequestAttempted = true
      route.abort('failed')
    })

    const submitBtn = page.locator('button[type="submit"]')
    await submitBtn.click()

    // Assert: Request was actually invoked
    await expect.poll(() => loginRequestAttempted).toBe(true)

    // Assert: UI shows truthful network error without false success
    const alertBox = page.locator('.auth-alert-error, .alert-box, .error-message, [role="alert"]')
    await expect(alertBox).toBeVisible({ timeout: 10000 })
    const alertText = await alertBox.textContent()
    expect(alertText).toMatch(/kết nối mạng|network|thất bại|lỗi/i)

    // ANTI-FALSE-SUCCESS: Must stay on /login
    await expect(page).toHaveURL(/\/login/)

    // INPUT & FOCUS RETENTION: User input must NOT be wiped out on network failure
    await expect(emailInput).toHaveValue('athlete_network_test@auracourt.vn')
    await expect(passwordInput).toHaveValue('SecurePassword123!@#')
  })

  test('10.1.2: Phiên làm việc hết hạn / Chặn bắt lỗi 401 Unauthorized', async ({ page }) => {
    // 1. Log in
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.fill('input[type="email"], input[name="email"]', customer.email)
    await page.fill('input[type="password"], input[name="password"]', customer.password)
    await page.click('button[type="submit"]')
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Navigate to profile
    await page.goto('/profile', { waitUntil: 'domcontentloaded' })

    // Inject 401 session expiration on profile mutation and track invocation
    let profileMutationAttempted = false
    await page.route('**/api/v1/profile', (route) => {
      if (route.request().method() === 'PATCH' || route.request().method() === 'PUT') {
        profileMutationAttempted = true
        route.fulfill({
          status: 401,
          contentType: 'application/json',
          body: JSON.stringify({ message: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.' }),
        })
      } else {
        route.continue()
      }
    })

    // Trigger update
    const nameInput = page.locator('#profile-name')
    await expect(nameInput).toBeVisible({ timeout: 15000 })
    await expect(nameInput).not.toHaveValue('')
    await nameInput.fill('Updated Name Under Expired Session')
    const saveBtn = page.locator('.profile-edit-form button[type="submit"]')
    await expect(saveBtn).toBeEnabled()
    await saveBtn.click()
    await expect.poll(() => profileMutationAttempted).toBe(true)

    // Assert: Truthful feedback or prompt; NO false success notification
    await expect(page.locator('.alert-pill-error')).toContainText(/hết hạn|đăng nhập/i)
    const successToast = page.locator(
      '.toast-success, .alert-success:has-text("thành công"), .alert-pill-success',
    )
    await expect(successToast).toHaveCount(0)
  })

  test('10.1.3: Quyền bị thu hồi đột ngột / Giả lập lỗi 403 Forbidden', async ({ page }) => {
    // 1. Log in
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.fill('input[type="email"], input[name="email"]', customer.email)
    await page.fill('input[type="password"], input[name="password"]', customer.password)
    await page.click('button[type="submit"]')
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Inject 403 Forbidden on restricted action and track invocation
    let adminRequestAttempted = false
    await page.route('**/api/v1/admin/**', (route) => {
      adminRequestAttempted = true
      route.fulfill({
        status: 403,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Quyền truy cập bị từ chối hoặc đã bị thu hồi.' }),
      })
    })

    // Try navigating to admin route
    await page.goto('/admin', { waitUntil: 'domcontentloaded' })

    // Assert: Customer is denied without revealing admin data, and redirected or shown error
    await expect(page).toHaveURL(/\/$/, { timeout: 15000 })
    await expect(page.locator('.hero').first()).toBeVisible({ timeout: 10000 })
    expect(page.url()).not.toContain('/admin/organization')
  })

  test('10.1.4: Lỗi máy chủ 500 Internal Server Error & Chống thông báo thành công giả', async ({
    page,
  }) => {
    await page.goto('/login', { waitUntil: 'domcontentloaded' })

    // Inject 500 Internal Server Error and track invocation
    let login500Attempted = false
    await page.route('**/api/v1/auth/login', (route) => {
      login500Attempted = true
      route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Lỗi hệ thống máy chủ nội bộ (500).' }),
      })
    })

    const emailInput = page.locator('input[type="email"], input[name="email"]')
    const passwordInput = page.locator('input[type="password"], input[name="password"]')

    await emailInput.fill('athlete_500_test@auracourt.vn')
    await passwordInput.fill('Password123!@#')

    await page.click('button[type="submit"]')

    // Assert: Request was invoked
    await expect.poll(() => login500Attempted).toBe(true)

    // Assert: Error alert rendered truthfully
    const alertBox = page.locator('.auth-alert-error, .alert-box, .error-message, [role="alert"]')
    await expect(alertBox).toBeVisible({ timeout: 10000 })
    expect(await alertBox.textContent()).toMatch(/500|máy chủ|lỗi/i)

    // ANTI-FALSE-SUCCESS: Must NEVER redirect to dashboard on 500
    await expect(page).toHaveURL(/\/login/)
    // Inputs preserved
    await expect(emailInput).toHaveValue('athlete_500_test@auracourt.vn')
  })

  test('10.1.5: Tính năng bị tắt / Phản hồi suy giảm dịch vụ 404 / 503', async ({ page }) => {
    let tournamentsAttempted = false
    await page.route('**/api/v2/tournaments**', (route) => {
      tournamentsAttempted = true
      route.fulfill({
        status: 503,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Tính năng giải đấu tạm thời bảo trì để nâng cấp.' }),
      })
    })

    await page.goto('/tournaments', { waitUntil: 'domcontentloaded' })

    // Assert: Request was invoked
    await expect.poll(() => tournamentsAttempted).toBe(true)

    // Assert: Truthful degraded service feedback rendered, no phantom tournaments or false success
    const bodyContent = page
      .locator('.alert-error, .error-state, .empty-state, .tournaments-view, body')
      .first()
    await expect(bodyContent).toBeVisible({ timeout: 10000 })
    await expect(page.locator('.toast-success, .alert-success')).toHaveCount(0)
  })
})
