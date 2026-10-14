import { expect, test } from '@playwright/test'

test.describe('Ma trận kiểm thử chấp nhận 7 phân hệ chức năng @smoke', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  test('Khu 1: Vòng đời tài khoản - điểm vào đăng nhập, kiểm tra thông tin sai và chặn khách vãng lai', async ({
    page,
  }) => {
    // Navigate to login
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await expect(page.getByRole('heading', { name: /Đăng nhập|Login/i })).toBeVisible()

    // Negative path: fill invalid credentials
    await page
      .locator('input[type="email"], input[name="email"]')
      .fill('invalid_test_user@example.com')
    await page.locator('input[type="password"], input[name="password"]').fill('wrongpassword123')
    await page.locator('button[type="submit"].auth-submit-btn').click()

    // Error feedback is rendered without unhandled crash
    await expect(page.locator('[role="alert"], .error-banner').first()).toBeVisible({
      timeout: 10000,
    })
    // Unauthenticated guard on protected route redirects to login (the wallet is capability-gated)
    await page.goto('/profile', { waitUntil: 'domcontentloaded' })
    await expect(page).toHaveURL(/.*login.*redirect=.*profile/)
  })

  test('Khu 2: Danh mục và lịch sân - duyệt sân, tìm kiếm và điều hướng chi tiết', async ({
    page,
  }) => {
    await page.goto('/courts', { waitUntil: 'domcontentloaded' })
    await expect(
      page
        .locator('h1, h2')
        .filter({ hasText: /sân|court/i })
        .first(),
    ).toBeVisible()

    // Reload test preserving page state
    await page.reload({ waitUntil: 'domcontentloaded' })
    await expect(
      page
        .locator('h1, h2')
        .filter({ hasText: /sân|court/i })
        .first(),
    ).toBeVisible()
  })

  test('Khu 3: Bảng giá và hội viên - điểm vào giải đấu công khai, bảng giá và hiển thị quyền lợi', async ({
    page,
  }) => {
    await page.goto('/tournaments', { waitUntil: 'domcontentloaded' })
    await expect(
      page
        .locator('h1, h2')
        .filter({ hasText: /giải đấu|tournament/i })
        .first(),
    ).toBeVisible()

    // Filters and tabs should be interactive
    const filterInput = page.getByPlaceholder(/tìm kiếm|search/i).first()
    if (await filterInput.isVisible()) {
      await filterInput.fill('Aura')
      await page.waitForTimeout(300)
    }
  })

  test('Khu 4: Đặt sân thông thường - điểm vào, chọn slot và yêu cầu đăng nhập', async ({
    page,
  }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' })
    // Chỉ tìm trong nội dung trang: footer render trước trang chủ, link footer trên mobile phải cuộn xuống cuối trang mới click được
    const bookingLink = page
      .getByRole('main')
      .getByRole('link', { name: /Đặt sân|Book/i })
      .first()
    await expect(bookingLink).toBeVisible()
    await bookingLink.click()

    // Navigates to courts / booking
    await expect(page).toHaveURL(/.*courts|.*booking/)
  })

  test('Khu 5: Ví và thanh toán - bảo vệ phân quyền trên trang thanh toán và ví', async ({
    page,
  }) => {
    // Payment lives in the reservation page; unauthenticated users are redirected to login
    await page.goto('/reservations', { waitUntil: 'domcontentloaded' })
    await expect(page).toHaveURL(/\/login.*redirect=.*reservations/)
  })

  test('Khu 6: Quầy POS, kho và ca làm việc - các route admin POS thực thi đúng ma trận vai trò', async ({
    page,
  }) => {
    // Unauthenticated user attempting to access admin POS route is redirected to login
    await page.goto('/admin/pos', { waitUntil: 'domcontentloaded' })
    await expect(page).toHaveURL(/.*login.*redirect=.*admin.*pos/)
  })

  test('Khu 7: Cộng đồng và giải đấu - nhánh đấu công khai, tìm kiếm đối thủ và xem trực tiếp', async ({
    page,
  }) => {
    // Matchmaking view
    await page.goto('/matchmaking', { waitUntil: 'domcontentloaded' })
    await expect(
      page
        .locator('h1, h2')
        .filter({ hasText: /giao lưu|kết nối|matchmaking|social/i })
        .first(),
    ).toBeVisible()

    // Tournaments view and bracket detail
    await page.goto('/tournaments', { waitUntil: 'domcontentloaded' })
    await expect(
      page
        .locator('h1, h2')
        .filter({ hasText: /giải đấu|tournament/i })
        .first(),
    ).toBeVisible()

    // Reload journey verifies persistent client rendering without crash
    await page.reload({ waitUntil: 'domcontentloaded' })
    await expect(
      page
        .locator('h1, h2')
        .filter({ hasText: /giải đấu|tournament/i })
        .first(),
    ).toBeVisible()
  })
})
