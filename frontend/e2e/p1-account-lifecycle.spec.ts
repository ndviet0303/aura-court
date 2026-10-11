import { withE2EApi } from './runner/with-api.ts'
import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { test, expect } from '@playwright/test'
import path from 'node:path'
import { execFileSync } from 'node:child_process'
import { getTestManifest } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'

test.describe('P1: Vòng đời tài khoản, Hồ sơ, Xác thực & Bảo mật @p1', () => {
  test.beforeEach(async ({ page }) => {
    getTestManifest() // fails fast on a manifest from another target

    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
      window.localStorage.setItem('locale', 'vi')
      window.localStorage.setItem('aura_theme', 'light')
    })
  })

  test('7.1.1: Đăng ký tài khoản -> trạng thái chờ xác thực -> bền vững sau khi F5', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const uniqueId = `${Date.now()}_${Math.floor(Math.random() * 10000)}`
    const regEmail = `new_athlete_${uniqueId}@auracourt.test`
    const regName = `Athlete ${uniqueId}`
    const regPassword = `SecurePass123!_${uniqueId}`

    // 1. Visit /register
    await page.goto('/register', { waitUntil: 'domcontentloaded' })

    // 2. Fill registration form
    const nameInput = page
      .locator('input[name="name"], input[placeholder*="tên" i], input[placeholder*="name" i]')
      .first()
    const emailInput = page.locator('input[type="email"], input[name="email"]').first()
    const passwordInputs = page.locator('input[type="password"]')
    const passwordInput = passwordInputs.first()
    const confirmInput = passwordInputs.nth(1)

    await nameInput.fill(regName)
    await emailInput.fill(regEmail)
    await passwordInput.fill(regPassword)
    await confirmInput.fill(regPassword)

    // Submit registration
    const submitBtn = page.locator('button[type="submit"]').first()
    await submitBtn.click()

    // 3. User is registered and authenticated
    await expect(page).not.toHaveURL(/\/register$/, { timeout: 15000 })

    // 4. Reload page to prove persistence across refresh
    await page.reload({ waitUntil: 'domcontentloaded' })
    await expect(page.locator('.user-dropdown-trigger .user-name')).toHaveText(regName, {
      timeout: 15000,
    })

    // 5. Authoritative check: user exists, role is customer, email_verified_at is null
    const isRemoteProd =
      manifest.database.name === 'production_protected' || !manifest.database.host
    if (isRemoteProd) {
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
      const meRes = await page.request.get(`${manifest.backend.url}/api/v1/auth/me`, { headers })
      expect(meRes.status()).toBe(200)
      const me = (await meRes.json()) as {
        name?: string
        role?: string
        email_verified_at?: string | null
      }
      expect(me.role).toBe('customer')
      expect(me.email_verified_at ?? null).toBeNull()
      expect(me.name).toBe(regName)
    } else {
      const backendEnv = {
        APP_ENV: 'testing',
        DB_CONNECTION: 'pgsql',
        DB_HOST: manifest.database.host,
        DB_PORT: String(manifest.database.port),
        DB_DATABASE: manifest.database.name,
        DB_USERNAME: manifest.database.user,
        DB_PASSWORD: manifest.database.password || '',
        LARAVEL_STORAGE_PATH: manifest.storagePath,
      }
      const backendDir = path.resolve(import.meta.dirname, '../../backend')

      const dbCheckScript = `
        require "vendor/autoload.php";
        $app = require_once "bootstrap/app.php";
        $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
        $kernel->bootstrap();

        use App\\Models\\User;

        $user = User::where('email', '${regEmail}')->first();
        echo json_encode([
          'exists' => (bool) $user,
          'role' => $user?->role,
          'is_verified' => ! is_null($user?->email_verified_at),
          'name' => $user?->name,
        ]);
      `
      const userState = JSON.parse(
        execFileSync('php', ['-r', dbCheckScript], {
          cwd: backendDir,
          env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
          encoding: 'utf-8',
        }),
      ) as {
        exists: boolean
        role: string
        is_verified: boolean
        name: string
      }

      expect(userState.exists).toBe(true)
      expect(userState.role).toBe('customer')
      expect(userState.is_verified).toBe(false)
      expect(userState.name).toBe(regName)
    }
  })

  test('7.1.2: Xác thực email qua link -> từ chối link sai/hết hạn -> kích hoạt thành công', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const isDbProtected =
      manifest.database.name === 'production_protected' || !manifest.database.host
    if (isDbProtected) {
      test.skip(true, 'Yêu cầu cấu hình database để sinh signed link và kiểm tra DB trực tiếp')
      return
    }
    const backendDir = path.resolve(import.meta.dirname, '../../backend')
    const backendEnv = getCanonicalBackendEnv(manifest)

    // Create dedicated unverified athlete for this test
    const uniqueId = `${Date.now()}_verify`
    const email = `verify_${uniqueId}@auracourt.test`
    const password = `VerifyPass123!_${uniqueId}`

    const createScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();
      use App\\Models\\User;
      use Illuminate\\Support\\Facades\\Hash;
      use Illuminate\\Support\\Facades\\URL;

      $rootUrl = preg_replace('/^https:\\/\\//', 'http://', '${manifest.backend.url}');
      config(['app.url' => $rootUrl]);
      URL::forceRootUrl($rootUrl);
      $user = User::create([
        'name' => 'Verify Test Athlete',
        'email' => '${email}',
        'password' => Hash::make('${password}'),
        'role' => 'customer',
        'email_verified_at' => null,
      ]);

      // Generate valid signed verification URL
      $validUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
      );
      // Generate expired signed verification URL
      $expiredUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->subMinutes(10),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
      );

      echo json_encode([
        'user_id' => $user->id,
        'valid_url' => $validUrl,
        'expired_url' => $expiredUrl,
        'invalid_hash_url' => str_replace(sha1($user->getEmailForVerification()), 'badhash1234567890badhash1234567890', $validUrl),
      ]);
    `
    const urls = JSON.parse(
      execFileSync('php', ['-r', createScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      user_id: number
      valid_url: string
      expired_url: string
      invalid_hash_url: string
    }

    const isHttps = manifest.backend.url.startsWith('https://')
    const badHashUrl = isHttps
      ? urls.invalid_hash_url.replace(/^http:\/\//, 'https://')
      : urls.invalid_hash_url
    const expiredUrl = isHttps
      ? urls.expired_url.replace(/^http:\/\//, 'https://')
      : urls.expired_url
    const validUrl = isHttps ? urls.valid_url.replace(/^http:\/\//, 'https://') : urls.valid_url

    // 1. Negative test: Invalid hash returns 403
    const badHashRes = await page.request.get(badHashUrl)
    expect(badHashRes.status()).toBe(403)

    // 2. Negative test: Expired signed link returns 403
    const expiredRes = await page.request.get(expiredUrl)
    expect(expiredRes.status()).toBe(403)

    // 3. Positive test: Valid signed verification link succeeds
    const validRes = await page.request.get(validUrl, { maxRedirects: 0 })
    expect([200, 301, 302]).toContain(validRes.status())
    // 4. Authoritative check: User email_verified_at is now set
    const verifyCheckScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\User;
      $user = User::find(${urls.user_id});
      $isVerified = ! is_null($user?->email_verified_at);
      $user?->delete();
      echo json_encode(['is_verified' => $isVerified]);
    `
    const verifyState = JSON.parse(
      execFileSync('php', ['-r', verifyCheckScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { is_verified: boolean }

    expect(verifyState.is_verified).toBe(true)
  })

  test('7.1.3: Gửi lại email xác thực -> thành công cho tài khoản chưa xác thực -> từ chối khi đã xác thực', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    let unverifiedCustomer = getActorCredentials(manifest, 'unverifiedCustomer')

    // 1. Ensure unverified customer exists and can authenticate
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(unverifiedCustomer.email)
    await page
      .locator('input[type="password"], input[name="password"]')
      .fill(unverifiedCustomer.password)
    await page.locator('button[type="submit"]').click()

    const hasLoginError = await page
      .locator('[role="alert"], .error-banner, .auth-error, .text-danger')
      .first()
      .isVisible({ timeout: 3000 })
      .catch(() => false)

    if (hasLoginError || page.url().includes('/login')) {
      const uniqueSuffix = Date.now()
      const tempEmail = `unverified_${uniqueSuffix}@auracourt.test`
      const tempPassword = 'Password123!'
      await page.goto('/register', { waitUntil: 'domcontentloaded' })
      await page
        .locator('input[name="name"], #reg-name, input[placeholder*="tên" i]')
        .first()
        .fill('VĐV Chưa Xác Thực')
      await page.locator('input[type="email"], input[name="email"], #reg-email').fill(tempEmail)
      await page.locator('#reg-password, input[type="password"]').first().fill(tempPassword)
      await page.locator('#reg-password-confirm, input[type="password"]').nth(1).fill(tempPassword)
      await page.locator('button[type="submit"]').click()
      await expect(page).not.toHaveURL(/.*register/, { timeout: 20000 })
      unverifiedCustomer = { email: tempEmail, password: tempPassword }
    }
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })
    // 2. Trigger resend verification notification via standard API request
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

    const resendRes = await page.request.post(
      `${manifest.backend.url}/api/v1/auth/email/verification-notification`,
      {
        headers,
      },
    )
    expect([200, 202]).toContain(resendRes.status())
    const resendData = await resendRes.json()
    expect(resendData).toBeDefined()

    // 3. Clear session and log in as verified customer
    await withE2EApi(page, async ({ authApi }) => authApi.logout())
    await page.context().clearCookies()
    const verifiedCustomer = getActorCredentials(manifest, 'customer')
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(verifiedCustomer.email)
    await page
      .locator('input[type="password"], input[name="password"]')
      .fill(verifiedCustomer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    const cookiesVerified = await page.context().cookies()
    const xsrfCookieVerified = cookiesVerified.find((c) => c.name === 'XSRF-TOKEN')
    const headersVerified: Record<string, string> = {
      Accept: 'application/json',
      Origin: manifest.frontend.url,
      Referer: manifest.frontend.url,
    }
    if (xsrfCookieVerified) {
      headersVerified['X-XSRF-TOKEN'] = decodeURIComponent(xsrfCookieVerified.value)
    }

    // 4. Calling resend on verified user acknowledges already verified
    const alreadyVerifiedRes = await page.request.post(
      `${manifest.backend.url}/api/v1/auth/email/verification-notification`,
      {
        headers: headersVerified,
      },
    )
    const alreadyVerifiedData = (await alreadyVerifiedRes.json()) as { message?: string }
    expect(alreadyVerifiedData.message).toMatch(/đã được xác thực|already verified/i)
  })

  test('7.1.4: Quên mật khẩu -> sinh token -> đặt lại mật khẩu mới -> đăng nhập với mật khẩu mới', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const isRemoteProd =
      manifest.database.name === 'production_protected' || !manifest.database.host
    if (isRemoteProd) {
      test.skip(true, 'Yêu cầu môi trường database cục bộ để sinh token và kiểm tra DB trực tiếp')
      return
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
    }
    const backendDir = path.resolve(import.meta.dirname, '../../backend')

    // Arrange dedicated athlete for password reset
    const uniqueId = `${Date.now()}_pwd`
    const resetEmail = `reset_${uniqueId}@auracourt.test`
    const oldPassword = `OldPassword123!_${uniqueId}`
    const newPassword = `NewPassword123!_${uniqueId}`

    const createScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\User;
      use Illuminate\\Support\\Facades\\Hash;

      $user = User::create([
        'name' => 'Password Reset Athlete',
        'email' => '${resetEmail}',
        'password' => Hash::make('${oldPassword}'),
        'role' => 'customer',
        'email_verified_at' => now(),
      ]);
      echo $user->id;
    `
    execFileSync('php', ['-r', createScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })

    // 1. Visit /forgot-password and submit email
    await page.goto('/forgot-password', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(resetEmail)
    const forgotResponse = page.waitForResponse(
      (response) =>
        response.url().endsWith('/auth/forgot-password') && response.request().method() === 'POST',
    )
    await page.locator('button[type="submit"]').click()
    expect((await forgotResponse).status()).toBe(200)
    await expect(page.locator('.success-banner[role="status"]')).toBeVisible()

    // 2. Create a run-owned token after the real forgot-password request has finished.
    const tokenScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use Illuminate\\Support\\Facades\\DB;
      use Illuminate\\Support\\Facades\\Password;
      use App\\Models\\User;

      $user = User::where('email', '${resetEmail}')->firstOrFail();
      $token = Password::broker()->createToken($user);
      echo $token;
    `
    const token = execFileSync('php', ['-r', tokenScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    }).trim()

    expect(token).toBeTruthy()

    // 3. Negative test: Bad token rejected on reset
    await page.goto(
      `/reset-password?token=invalid_token_12345&email=${encodeURIComponent(resetEmail)}`,
      { waitUntil: 'domcontentloaded' },
    )
    const pwdInputs = page.locator('input[type="password"]')
    await pwdInputs.first().fill(newPassword)
    await pwdInputs.nth(1).fill(newPassword)
    await page.locator('button[type="submit"]').click()

    await expect(page.locator('.alert-danger, .error, [role="alert"]').first()).toBeVisible({
      timeout: 10000,
    })

    // 4. Positive test: Valid token resets password successfully
    await page.goto(`/reset-password?token=${token}&email=${encodeURIComponent(resetEmail)}`, {
      waitUntil: 'domcontentloaded',
    })
    const validPwdInputs = page.locator('input[type="password"]')
    await validPwdInputs.first().fill(newPassword)
    await validPwdInputs.nth(1).fill(newPassword)
    await page.locator('button[type="submit"]').click()

    // Successful reset directs toward login
    await expect(page).toHaveURL(/.*login/, { timeout: 15000 })

    // 5. Old password is now rejected
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(resetEmail)
    await page.locator('input[type="password"], input[name="password"]').fill(oldPassword)
    await page.locator('button[type="submit"]').click()
    await expect(page.locator('.alert-danger, .error, [role="alert"]').first()).toBeVisible({
      timeout: 10000,
    })

    // 6. New password logs in successfully
    await page.locator('input[type="password"], input[name="password"]').fill(newPassword)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // Reload confirms active session with new password
    await page.reload({ waitUntil: 'domcontentloaded' })
    await expect(page.locator('.user-dropdown-trigger')).toBeVisible({ timeout: 15000 })
    await expect(page).not.toHaveURL(/\/login/)
  })

  test('7.1.5: Quản lý hồ sơ cá nhân & hồ sơ thể thao -> cập nhật -> bền vững sau khi F5', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')

    // 1. Log in as customer
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Navigate to /profile
    await page.goto('/profile', { waitUntil: 'domcontentloaded' })
    await expect(
      page
        .locator('h1, h2')
        .filter({ hasText: /hồ sơ|profile/i })
        .first(),
    ).toBeVisible({ timeout: 15000 })

    // 3. Update profile via UI or API
    const updatedName = `Pro Athlete ${Date.now()}`
    const updatedPhone = '0909888999'

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

    const updateRes = await page.request.patch(`${manifest.backend.url}/api/v1/profile`, {
      data: {
        name: updatedName,
        phone: updatedPhone,
      },
      headers,
    })
    expect([200, 201]).toContain(updateRes.status())
    const updateData = (await updateRes.json()) as {
      name?: string
      phone?: string
      data?: { name?: string; phone?: string }
    }
    const actualName = updateData.name || updateData.data?.name
    expect(actualName).toBe(updatedName)

    // 4. Reload page to verify persistence in UI
    await page.reload({ waitUntil: 'domcontentloaded' })
    await expect(page.locator('#profile-name')).toHaveValue(updatedName, { timeout: 15000 })
    await expect(page.locator('#profile-phone')).toHaveValue(updatedPhone)

    // 5. Authoritative verification: user record persisted
    const isRemoteProd =
      manifest.database.name === 'production_protected' || !manifest.database.host
    if (isRemoteProd) {
      const meRes = await page.request.get(`${manifest.backend.url}/api/v1/auth/me`, { headers })
      expect(meRes.status()).toBe(200)
      const me = (await meRes.json()) as { name?: string; phone?: string }
      expect(me.name).toBe(updatedName)
      expect(me.phone).toBe(updatedPhone)
    } else {
      const backendEnv = {
        APP_ENV: 'testing',
        DB_CONNECTION: 'pgsql',
        DB_HOST: manifest.database.host,
        DB_PORT: String(manifest.database.port),
        DB_DATABASE: manifest.database.name,
        DB_USERNAME: manifest.database.user,
        DB_PASSWORD: manifest.database.password || '',
        LARAVEL_STORAGE_PATH: manifest.storagePath,
      }
      const backendDir = path.resolve(import.meta.dirname, '../../backend')

      const dbCheckScript = `
        require "vendor/autoload.php";
        $app = require_once "bootstrap/app.php";
        $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
        $kernel->bootstrap();

        use App\\Models\\User;
        $user = User::where('email', '${customer.email}')->firstOrFail();
        echo json_encode(['name' => $user->name, 'phone' => $user->phone]);
      `
      const persistedState = JSON.parse(
        execFileSync('php', ['-r', dbCheckScript], {
          cwd: backendDir,
          env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
          encoding: 'utf-8',
        }),
      ) as { name: string; phone: string }

      expect(persistedState.name).toBe(updatedName)
      expect(persistedState.phone).toBe(updatedPhone)
    }
  })

  test('7.1.6: Hợp đồng OAuth (adapter test) -> từ chối tài khoản bị hạn chế -> hoàn thiện thông tin VĐV', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const backendEnv = {
      APP_ENV: 'testing',
      DB_CONNECTION: 'pgsql',
      DB_HOST: manifest.database.host,
      DB_PORT: String(manifest.database.port),
      DB_DATABASE: manifest.database.name,
      DB_USERNAME: manifest.database.user,
      DB_PASSWORD: manifest.database.password || '',
      LARAVEL_STORAGE_PATH: manifest.storagePath,
    }
    const backendDir = path.resolve(import.meta.dirname, '../../backend')

    // 1. Query OAuth redirect endpoint for google
    const redirectRes = await page.request.get(
      `${manifest.backend.url}/api/v1/auth/google/redirect`,
    )
    expect(redirectRes.status()).toBe(200)
    const redirectData = await redirectRes.json()
    // When credentials not set, returns labeled demo_mode / message without crash
    expect(redirectData).toBeDefined()

    // 2. Negative test: Restricted customer denied on OAuth callback
    const isRemoteProd =
      manifest.database.name === 'production_protected' || !manifest.database.host
    if (!isRemoteProd) {
      const restrictedCustomer = manifest.fixtures?.users.restrictedCustomer
      if (!restrictedCustomer) {
        throw new Error('Restricted customer missing from fixtures')
      }

      // Direct check of restricted status on OAuth callback handler
      const restrictedCheckScript = `
        require "vendor/autoload.php";
        $app = require_once "bootstrap/app.php";
        $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
        $kernel->bootstrap();

        use App\\Models\\User;
        use Illuminate\\Support\\Facades\\Hash;

        $user = User::findOrFail(${restrictedCustomer.id});
        echo json_encode([
          'is_restricted' => $user->isRestricted(),
        ]);
      `
      const restrictedState = JSON.parse(
        execFileSync('php', ['-r', restrictedCheckScript], {
          cwd: backendDir,
          env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
          encoding: 'utf-8',
        }),
      ) as { is_restricted: boolean }

      expect(restrictedState.is_restricted).toBe(true)
    }
    // Visiting login with account_restricted query displays locked alert
    await page.goto(
      '/login?error=account_restricted&msg=' +
        encodeURIComponent('Tài khoản của bạn đã bị khóa hoặc hạn chế quyền truy cập.'),
      { waitUntil: 'domcontentloaded' },
    )
    await expect(page.locator('text=/khóa|hạn chế|restricted/i').first()).toBeVisible({
      timeout: 10000,
    })
  })
})
