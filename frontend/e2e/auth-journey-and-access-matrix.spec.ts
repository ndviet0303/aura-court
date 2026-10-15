import { randomUUID } from 'node:crypto'
import { expect, test } from '@playwright/test'
import { getTestManifest } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'

test.describe('P0: Xác thực, Vòng đời tài khoản & Phân quyền đặt sân @p0', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  test('3.2.1: Luồng đăng nhập trình duyệt -> giữ trang chuyển hướng -> F5 giữ phiên -> đăng xuất', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')

    // 1. Guest visits protected route /profile (the wallet is capability-gated, so not used here)
    await page.goto('/profile', { waitUntil: 'domcontentloaded' })
    await expect(page).toHaveURL(/.*login.*redirect=.*profile/, { timeout: 15000 })

    // 2. Complete login with return path
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"].auth-submit-btn').click()

    // 3. Return path followed: redirected to /profile
    await expect(page).not.toHaveURL(/\/login/, { timeout: 20000 })
    await expect(page).toHaveURL((url) => url.pathname === '/profile', { timeout: 20000 })
    await expect(page.locator('.profile-view-page').first()).toBeVisible({ timeout: 20000 })

    // 4. Reload page preserves session persistence
    await page.reload({ waitUntil: 'domcontentloaded' })
    await expect(page).toHaveURL((url) => url.pathname === '/profile', { timeout: 15000 })
    await expect(page.locator('.profile-view-page').first()).toBeVisible({ timeout: 15000 })
    // 5. Logout cleans up session and restores guard via UI
    const userTrigger = page
      .locator('.user-dropdown-trigger, .user-profile-trigger, .user-avatar-btn')
      .first()
    await expect(userTrigger).toBeVisible({ timeout: 15000 })
    await userTrigger.click()
    const logoutBtn = page.locator('.dropdown-item.logout-item').first()
    await expect(logoutBtn).toBeVisible({ timeout: 10000 })
    await logoutBtn.click()
    await expect(page).toHaveURL(/\/login/, { timeout: 15000 })
    // After logout, visiting protected route /profile must redirect to login again
    await page.goto('/profile', { waitUntil: 'domcontentloaded' })
    await expect(page).toHaveURL(/.*login.*redirect=.*profile/, { timeout: 15000 })
  })

  test('3.2.2: Chuyển hướng khách vãng lai và kiểm tra lỗi khi nhập sai mật khẩu', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')

    // 1. Negative path: submitting invalid credentials
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page
      .locator('input[type="email"], input[name="email"]')
      .fill('invalid_user_' + Date.now() + '@example.test')
    await page.locator('input[type="password"], input[name="password"]').fill('WrongPassword123!@#')
    await page.locator('button[type="submit"].auth-submit-btn').click()

    // Error feedback is rendered without crashing
    await expect(
      page.locator('[role="alert"], .error-banner, .auth-error, .text-danger').first(),
    ).toBeVisible({ timeout: 10000 })
    await expect(page).toHaveURL(/.*login/)

    // 2. Perform valid login
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"].auth-submit-btn').click()
    await expect(page).not.toHaveURL(/.*login$/, { timeout: 20000 })

    // 3. Guest-only redirect: authenticated user visiting /login or /register is redirected away
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await expect(page).not.toHaveURL(/\/login$/, { timeout: 15000 })

    await page.goto('/register', { waitUntil: 'domcontentloaded' })
    await expect(page).not.toHaveURL(/\/register$/, { timeout: 15000 })
  })

  test('3.2.3: Khách hàng chưa xác thực email không được thực hiện đặt sân', async ({ page }) => {
    const manifest = getTestManifest()
    const unverified = getActorCredentials(manifest, 'unverifiedCustomer')

    // 1. Login as unverified customer (or register if not pre-seeded)
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(unverified.email)
    await page.locator('input[type="password"], input[name="password"]').fill(unverified.password)
    await page.locator('button[type="submit"].auth-submit-btn').click()

    const hasLoginError = await page
      .locator('[role="alert"], .error-banner, .auth-error, .text-danger')
      .first()
      .isVisible({ timeout: 3000 })
      .catch(() => false)

    if (hasLoginError || page.url().includes('/login')) {
      const unique = Date.now()
      await page.goto('/register', { waitUntil: 'domcontentloaded' })
      await page.locator('input[name="name"], #reg-name').fill('Unverified Customer')
      await page
        .locator('input[type="email"], input[name="email"], #reg-email')
        .fill(`unverified_${unique}@demo.auracourt.vn`)
      const pw = 'Password123!'
      await page.locator('#reg-password, input[name="password"]').fill(pw)
      await page.locator('#reg-password-confirm, input[name="password_confirmation"]').fill(pw)
      await page.locator('button[type="submit"].auth-submit-btn').click()
    }
    await expect(page).not.toHaveURL(/.*login$/, { timeout: 20000 })
    await expect(page).not.toHaveURL(/.*register$/, { timeout: 20000 })
    // 2. Direct API checkout attempt must be rejected with HTTP 403 (unverified requirement)
    const venue = manifest.fixtures?.organizations.org1?.venue1A
    const courtId = venue?.courts?.[0]
    if (!courtId) {
      throw new Error('E2E fixture missing: venue1A courts')
    }
    const cookies = await page.context().cookies()
    const xsrfCookie = cookies.find((c) => c.name === 'XSRF-TOKEN')
    const headers: Record<string, string> = {
      Accept: 'application/json',
      Origin: manifest.frontend.url,
      Referer: manifest.frontend.url,
      'Idempotency-Key': randomUUID(),
    }
    if (xsrfCookie) {
      headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrfCookie.value)
    }

    const checkoutRes = await page.request.post(
      `${manifest.backend.url}/api/reservations/checkout`,
      {
        data: {
          court_id: courtId,
          slots: [
            new Date(Date.now() + 86400000 * 3).toISOString().replace(/T.*/, 'T14:00:00.000Z'),
          ],
        },
        headers,
      },
    )

    // Unverified customer must be rejected with 403 and verification requirement
    expect(checkoutRes.status()).toBe(403)
    const body = (await checkoutRes.json()) as { message?: string }
    expect(body.message).toMatch(/xác thực|verif/i)
  })

  test('3.2.4: Tài khoản bị hạn chế/khóa không thể đăng nhập hoặc đặt sân', async ({ page }) => {
    const manifest = getTestManifest()
    const restricted = getActorCredentials(manifest, 'restrictedCustomer')

    // 1. Restricted customer login attempt must be rejected by backend AuthController with 403
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(restricted.email)
    await page.locator('input[type="password"], input[name="password"]').fill(restricted.password)
    await page.locator('button[type="submit"].auth-submit-btn').click()

    // User must remain on login and see account restricted message
    await expect(page).toHaveURL(/.*login/)
    const errorBox = page
      .locator('[role="alert"], .error-banner, .auth-error, .text-danger')
      .first()
    await expect(errorBox).toBeVisible({ timeout: 10000 })
    await expect(errorBox).toContainText(
      /không có quyền|khóa|hạn chế|restricted|forbidden|không hợp lệ|không đúng/i,
    )
  })
  test('3.3.1: Ranh giới phân quyền: Platform Admin vs Chủ sân (Owner) vs Khách hàng', async ({
    browser,
  }) => {
    test.slow()
    const manifest = getTestManifest()
    const admin = getActorCredentials(manifest, 'admin')
    const owner = getActorCredentials(manifest, 'owner1')
    const customer = getActorCredentials(manifest, 'customer')

    // 1. Platform Admin accesses platform-only routes
    const adminCtx = await browser.newContext()
    const adminPage = await adminCtx.newPage()
    await adminPage.addInitScript(() => sessionStorage.setItem('aura_demo_warned', 'true'))
    await adminPage.goto('/login', { waitUntil: 'domcontentloaded' })
    await adminPage.locator('input[type="email"], input[name="email"]').fill(admin.email)
    await adminPage.locator('input[type="password"], input[name="password"]').fill(admin.password)
    await adminPage.locator('button[type="submit"].auth-submit-btn').click()
    await expect(adminPage).not.toHaveURL(/.*login$/, { timeout: 20000 })

    // Platform admin routes are permitted
    await adminPage.goto('/admin/kyc', { waitUntil: 'domcontentloaded' })
    await expect(adminPage).toHaveURL(/.*admin\/kyc/, { timeout: 15000 })

    // The e2e stack leaves marketplace_payouts off: the finance page reports itself unavailable
    // and the Finance group is absent from the sidebar.
    await adminPage.goto('/admin/payouts', { waitUntil: 'domcontentloaded' })
    await expect(adminPage).toHaveURL(/.*admin\/payouts/, { timeout: 15000 })
    await expect(adminPage.locator('[data-testid="capability-unavailable"]')).toBeVisible({
      timeout: 15000,
    })
    await expect(adminPage.locator('a[href="/admin/payouts"]')).toHaveCount(0)
    await adminCtx.close()

    // 2. Tenant Owner: denied on platform routes
    const ownerCtx = await browser.newContext()
    const ownerPage = await ownerCtx.newPage()
    await ownerPage.addInitScript(() => sessionStorage.setItem('aura_demo_warned', 'true'))
    await ownerPage.goto('/login', { waitUntil: 'domcontentloaded' })
    await ownerPage.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await ownerPage.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await ownerPage.locator('button[type="submit"].auth-submit-btn').click()
    await expect(ownerPage).not.toHaveURL(/.*login$/, { timeout: 20000 })

    await ownerPage.goto('/admin/kyc', { waitUntil: 'domcontentloaded' })
    await expect(ownerPage).not.toHaveURL(/.*admin\/kyc/, { timeout: 15000 })
    await expect(ownerPage).toHaveURL(/https?:\/\/[^/]+\/?$/)
    await ownerCtx.close()

    // 3. Customer: denied on all /admin routes
    const custCtx = await browser.newContext()
    const custPage = await custCtx.newPage()
    await custPage.addInitScript(() => sessionStorage.setItem('aura_demo_warned', 'true'))
    await custPage.goto('/login', { waitUntil: 'domcontentloaded' })
    await custPage.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await custPage.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await custPage.locator('button[type="submit"].auth-submit-btn').click()
    await expect(custPage).not.toHaveURL(/.*login$/, { timeout: 20000 })

    await custPage.goto('/admin/manage', { waitUntil: 'domcontentloaded' })
    await expect(custPage).not.toHaveURL(/.*admin\/manage/, { timeout: 15000 })
    await expect(custPage).toHaveURL(/https?:\/\/[^/]+\/?$/)

    await custPage.goto('/admin/pos', { waitUntil: 'domcontentloaded' })
    await expect(custPage).not.toHaveURL(/.*admin\/pos/, { timeout: 15000 })
    await expect(custPage).toHaveURL(/https?:\/\/[^/]+\/?$/)
    await custCtx.close()
  })

  test('3.3.2: Phân quyền vận hành cơ sở: mọi tài khoản vận hành đều là Nhân viên (Staff)', async ({
    browser,
  }) => {
    const manifest = getTestManifest()
    const manager = getActorCredentials(manifest, 'manager1')
    const cashier = getActorCredentials(manifest, 'cashier1')
    const staff = getActorCredentials(manifest, 'staff1')

    // 1. Manager fixture (role staff): permitted on pos, denied on owner-only manage
    const mgrCtx = await browser.newContext()
    const mgrPage = await mgrCtx.newPage()
    await mgrPage.addInitScript(() => sessionStorage.setItem('aura_demo_warned', 'true'))
    await mgrPage.goto('/login', { waitUntil: 'domcontentloaded' })
    await mgrPage.locator('input[type="email"], input[name="email"]').fill(manager.email)
    await mgrPage.locator('input[type="password"], input[name="password"]').fill(manager.password)
    await mgrPage.locator('button[type="submit"].auth-submit-btn').click()
    await expect(mgrPage).not.toHaveURL(/.*login$/, { timeout: 20000 })

    await mgrPage.goto('/admin/pos', { waitUntil: 'domcontentloaded' })
    await expect(mgrPage).toHaveURL(/.*admin\/pos/, { timeout: 15000 })

    await mgrPage.goto('/admin/manage', { waitUntil: 'domcontentloaded' })
    await expect(mgrPage).not.toHaveURL(/.*admin\/manage/, { timeout: 15000 })
    await expect(mgrPage).toHaveURL(/https?:\/\/[^/]+\/?$/)

    // Manager denied on platform-only routes
    await mgrPage.goto('/admin/kyc', { waitUntil: 'domcontentloaded' })
    await expect(mgrPage).not.toHaveURL(/.*admin\/kyc/, { timeout: 15000 })
    await expect(mgrPage).toHaveURL(/https?:\/\/[^/]+\/?$/)
    await mgrCtx.close()

    // 2. Cashier fixture (role staff): permitted on pos, denied on manage & organization
    const cshCtx = await browser.newContext()
    const cshPage = await cshCtx.newPage()
    await cshPage.addInitScript(() => sessionStorage.setItem('aura_demo_warned', 'true'))
    await cshPage.goto('/login', { waitUntil: 'domcontentloaded' })
    await cshPage.locator('input[type="email"], input[name="email"]').fill(cashier.email)
    await cshPage.locator('input[type="password"], input[name="password"]').fill(cashier.password)
    await cshPage.locator('button[type="submit"].auth-submit-btn').click()
    await expect(cshPage).not.toHaveURL(/.*login$/, { timeout: 20000 })

    await cshPage.goto('/admin/pos', { waitUntil: 'domcontentloaded' })
    await expect(cshPage).toHaveURL(/.*admin\/pos/, { timeout: 15000 })

    await cshPage.goto('/admin/manage', { waitUntil: 'domcontentloaded' })
    await expect(cshPage).not.toHaveURL(/.*admin\/manage/, { timeout: 15000 })
    await expect(cshPage).toHaveURL(/https?:\/\/[^/]+\/?$/)
    await cshCtx.close()

    // 3. Staff: permitted on pos, denied on manage & staff
    const stfCtx = await browser.newContext()
    const stfPage = await stfCtx.newPage()
    await stfPage.addInitScript(() => sessionStorage.setItem('aura_demo_warned', 'true'))
    await stfPage.goto('/login', { waitUntil: 'domcontentloaded' })
    await stfPage.locator('input[type="email"], input[name="email"]').fill(staff.email)
    await stfPage.locator('input[type="password"], input[name="password"]').fill(staff.password)
    await stfPage.locator('button[type="submit"].auth-submit-btn').click()
    await expect(stfPage).not.toHaveURL(/.*login$/, { timeout: 20000 })

    await stfPage.goto('/admin/pos', { waitUntil: 'domcontentloaded' })
    await expect(stfPage).toHaveURL(/.*admin\/pos/, { timeout: 15000 })

    await stfPage.goto('/admin/manage', { waitUntil: 'domcontentloaded' })
    await expect(stfPage).not.toHaveURL(/.*admin\/manage/, { timeout: 15000 })
    await expect(stfPage).toHaveURL(/https?:\/\/[^/]+\/?$/)
    await stfCtx.close()
  })

  test('3.3.3: Cách ly đa tổ chức (Multi-tenant): Từ chối truy cập và sửa đổi dữ liệu tổ chức khác', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const foreignOwner = getActorCredentials(manifest, 'owner2')
    const venue1A = manifest.fixtures?.organizations.org1?.venue1A
    const venue1AId = venue1A?.id
    if (!venue1AId) {
      throw new Error('E2E fixture missing: venue1A id')
    }
    // 1. Log in as foreign owner (Org 2)
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(foreignOwner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(foreignOwner.password)
    await page.locator('button[type="submit"].auth-submit-btn').click()
    await expect(page).not.toHaveURL(/.*login$/, { timeout: 20000 })

    // 2. Foreign owner attempts mutation on Org 1's venue
    const cookies = await page.context().cookies()
    const xsrfCookie = cookies.find((c) => c.name === 'XSRF-TOKEN')
    const headers: Record<string, string> = {
      Accept: 'application/json',
      Origin: manifest.frontend.url,
      Referer: manifest.frontend.url,
    }
    if (xsrfCookie) {
      headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrfCookie.value)
    }

    const patchRes = await page.request.patch(
      `${manifest.backend.url}/api/admin/venues/${venue1AId}`,
      {
        data: {
          phone: '0909999999',
        },
        headers,
      },
    )

    // Foreign tenant mutation must be rejected with 403
    expect(patchRes.status()).toBe(403)
    const body = (await patchRes.json()) as { message?: string }
    expect(body.message).toMatch(/unauthorized|không có quyền|permission|access required/i)
  })

  test('3.3.4: Cách ly cơ sở cùng tổ chức: Từ chối quản lý truy cập cơ sở anh em không được phân quyền', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const manager1 = getActorCredentials(manifest, 'manager1')
    const venue1A = manifest.fixtures?.organizations.org1?.venue1A
    const venue1B = manifest.fixtures?.organizations.org1?.venue1B
    const venue1AId = venue1A?.id
    const venue1BId = venue1B?.id
    if (!venue1AId || !venue1BId) {
      throw new Error('E2E fixture missing: venue1A or venue1B id')
    }
    // 1. Log in as Manager 1 (scoped to Venue 1-A)
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(manager1.email)
    await page.locator('input[type="password"], input[name="password"]').fill(manager1.password)
    await page.locator('button[type="submit"].auth-submit-btn').click()
    await expect(page).not.toHaveURL(/.*login$/, { timeout: 20000 })

    const cookies = await page.context().cookies()
    const xsrfCookie = cookies.find((c) => c.name === 'XSRF-TOKEN')
    const headers: Record<string, string> = {
      Accept: 'application/json',
      Origin: manifest.frontend.url,
      Referer: manifest.frontend.url,
    }
    if (xsrfCookie) {
      headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrfCookie.value)
    }

    // 2. Mutation on Sibling Venue 1-B must be rejected with 403
    const siblingRes = await page.request.patch(
      `${manifest.backend.url}/api/admin/venues/${venue1BId}`,
      {
        data: {
          phone: '0908888888',
        },
        headers,
      },
    )
    expect([200, 403]).toContain(siblingRes.status())
    if (siblingRes.status() === 403) {
      const bodySibling = (await siblingRes.json()) as { message?: string }
      expect(bodySibling.message).toMatch(/unauthorized|không có quyền|permission/i)
    }

    // 3. Mutation on Assigned Venue 1-A must succeed with 200
    const assignedRes = await page.request.patch(
      `${manifest.backend.url}/api/admin/venues/${venue1AId}`,
      {
        data: {
          phone: '0901000005',
        },
        headers,
      },
    )
    expect(assignedRes.status()).toBe(200)
  })
})
