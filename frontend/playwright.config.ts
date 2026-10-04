import { defineConfig, devices } from '@playwright/test'
import { getAvailablePort } from './e2e/runner/environment.ts'

// Cấu hình môi trường mục tiêu: 'local' (mặc định) hoặc 'prod' (production / staging)
const targetEnv = (process.env.E2E_TARGET || process.env.TARGET_ENV || 'local').toLowerCase()
const isProd = targetEnv === 'prod' || targetEnv === 'production'

let effectiveBaseUrl: string

if (isProd) {
  // Chế độ Production: cho phép trỏ domain thực tế (mặc định https://court.ziet.dev hoặc từ PROD_URL / PLAYWRIGHT_BASE_URL)
  const prodUrl = process.env.PROD_URL || process.env.E2E_PROD_URL || process.env.PLAYWRIGHT_BASE_URL || 'https://court.ziet.dev'
  try {
    const parsed = new URL(prodUrl)
    if (!['http:', 'https:'].includes(parsed.protocol)) {
      throw new Error(`Giao thức không hợp lệ: ${parsed.protocol}`)
    }
  } catch (err: any) {
    throw new Error(`CRITICAL: URL Production không hợp lệ: ${err.message}`)
  }
  effectiveBaseUrl = prodUrl
  process.env.PLAYWRIGHT_BASE_URL = effectiveBaseUrl
} else {
  // Chế độ Local: R04 - Bảo vệ an toàn, chỉ cho phép loopback cục bộ
  const callerBaseUrl = process.env.PLAYWRIGHT_BASE_URL
  if (callerBaseUrl) {
    try {
      const parsed = new URL(callerBaseUrl)
      if (!['127.0.0.1', 'localhost', '::1'].includes(parsed.hostname)) {
        throw new Error(`CRITICAL: Cấm trỏ URL remote ở chế độ Local PLAYWRIGHT_BASE_URL="${callerBaseUrl}". Đặt E2E_TARGET=prod nếu muốn test Production.`)
      }
    } catch (err: any) {
      throw new Error(`CRITICAL: URL Local không hợp lệ: ${err.message}`)
    }
  }

  const targetFrontendPort = await getAvailablePort(Number(process.env.PLAYWRIGHT_FRONTEND_PORT) || 4180)
  process.env.PLAYWRIGHT_FRONTEND_PORT = String(targetFrontendPort)
  effectiveBaseUrl = callerBaseUrl || `http://127.0.0.1:${targetFrontendPort}`
  process.env.PLAYWRIGHT_BASE_URL = effectiveBaseUrl
}
export default defineConfig({
  testDir: './e2e',
  testMatch: '**/*.spec.ts',
  testIgnore: ['**/runner/**'],
  timeout: 60000,
  workers: 1,
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  failOnFlakyTests: true,
  globalSetup: './e2e/runner/playwright-global-setup.ts',
  globalTeardown: './e2e/runner/playwright-global-teardown.ts',
  reporter: [
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ['junit', { outputFile: 'test-results/junit.xml' }],
    ['list'],
  ],
  metadata: {
    revision: process.env.CI_COMMIT_SHA || process.env.GIT_REVISION || 'local',
    environment: isProd ? 'production' : (process.env.APP_ENV || 'isolated-e2e'),
    target: isProd ? 'Thực tế (Production)' : 'Cục bộ (Local)',
    target_url: effectiveBaseUrl,
    browser: 'chromium',
    timezone: 'Asia/Ho_Chi_Minh',
    locale: 'vi-VN',
    capability_profile: 'direct_owner_payments,tenant_pos_and_wallet',
  },
  use: {
    baseURL: effectiveBaseUrl,
    timezoneId: 'Asia/Ho_Chi_Minh',
    locale: 'vi-VN',
    trace: {
      mode: 'retain-on-failure',
      sources: false,
      attachments: false,
      screenshots: true,
    },
    screenshot: 'only-on-failure',
  },
  projects: [
    // --- 9 Khu chức năng để chọn và bấm chạy trực quan trên Playwright UI ---
    {
      name: 'Khu 1: Tài khoản & Phân quyền',
      testMatch: /(auth-journey-and-access-matrix|p1-account-lifecycle|p1-partner-registration|p1-staff-management)\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], timezoneId: 'Asia/Ho_Chi_Minh', locale: 'vi-VN' },
    },
    {
      name: 'Khu 2: Cơ sở, Sân & Khả dụng',
      testMatch: /(p0-court-discovery-and-schedule|p1-venue-lifecycle|p1-receiving-connections|p1-platform-subscription)\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], timezoneId: 'Asia/Ho_Chi_Minh', locale: 'vi-VN' },
    },
    {
      name: 'Khu 3: Bảng giá, Khuyến mãi & Hội viên',
      testMatch: /(p1-pricing-promotions-loyalty|booking-access-loyalty-uat)\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], timezoneId: 'Asia/Ho_Chi_Minh', locale: 'vi-VN' },
    },
    {
      name: 'Khu 4: Đặt sân & Thanh toán trực tiếp',
      testMatch: /(p0-booking-payment-journey|p0-cancellation-refund-reschedule|p1-recurring-series|p1-split-payments|p1-resale-marketplace|p1-legacy-wallet-direct-cohort)\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], timezoneId: 'Asia/Ho_Chi_Minh', locale: 'vi-VN' },
    },
    {
      name: 'Khu 5: Quầy POS, Ca & Dịch vụ sân',
      testMatch: /(p0-pos-shift-service|p1-inventory-pos|p1-attendance-walkin-waitlist)\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], timezoneId: 'Asia/Ho_Chi_Minh', locale: 'vi-VN' },
    },
    {
      name: 'Khu 6: Cộng đồng & Giải đấu',
      testMatch: /(p1-tournaments-lifecycle|p1-social-matchmaking-activity|p1-community-feed-moderation|p1-drop-in-session-bill)\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], timezoneId: 'Asia/Ho_Chi_Minh', locale: 'vi-VN' },
    },
    {
      name: 'Khu 7: Tài chính, Đối soát & Khiếu nại',
      testMatch: /(p1-dispute-lifecycle|p1-revenue-reconciliation-reporting)\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], timezoneId: 'Asia/Ho_Chi_Minh', locale: 'vi-VN' },
    },
    {
      name: 'Khu 8: Khả năng phục hồi & Realtime',
      testMatch: /(p2-fault-injection-resilience|p2-realtime-availability-tournament|p2-representative-experience)\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], timezoneId: 'Asia/Ho_Chi_Minh', locale: 'vi-VN' },
    },
    {
      name: 'Khu 9: Điều hướng Công khai (Smoke)',
      testMatch: /(home|seven-areas-acceptance|p2-smoke-public-navigation)\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], timezoneId: 'Asia/Ho_Chi_Minh', locale: 'vi-VN' },
    },

    // --- Cổng kiểm thử CI và phân tầng ưu tiên kỹ thuật ---
    {
      name: 'p0',
      grep: /@p0|P0:/,
      use: { ...devices['Desktop Chrome'], timezoneId: 'Asia/Ho_Chi_Minh', locale: 'vi-VN' },
    },
    {
      name: 'p1',
      grep: /@p1|P1:/,
      use: { ...devices['Desktop Chrome'], timezoneId: 'Asia/Ho_Chi_Minh', locale: 'vi-VN' },
    },
    {
      name: 'p2',
      grep: /@p2|P2:/,
      use: { ...devices['Desktop Chrome'], timezoneId: 'Asia/Ho_Chi_Minh', locale: 'vi-VN' },
    },
    {
      name: 'smoke-chromium',
      grep: /@smoke|Smoke:/,
      use: { ...devices['Desktop Chrome'], timezoneId: 'Asia/Ho_Chi_Minh', locale: 'vi-VN' },
    },
    {
      name: 'smoke-firefox',
      grep: /@smoke|Smoke:/,
      use: { ...devices['Desktop Firefox'], timezoneId: 'Asia/Ho_Chi_Minh', locale: 'vi-VN' },
    },
    {
      name: 'smoke-webkit',
      grep: /@smoke|Smoke:/,
      use: { ...devices['Desktop Safari'], timezoneId: 'Asia/Ho_Chi_Minh', locale: 'vi-VN' },
    },
    {
      name: 'smoke-mobile',
      grep: /@smoke|Smoke:/,
      use: { ...devices['Pixel 5'], timezoneId: 'Asia/Ho_Chi_Minh', locale: 'vi-VN' },
    },
  ],
})
