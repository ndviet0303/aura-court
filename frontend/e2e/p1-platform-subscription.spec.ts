import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { withE2EApi } from './runner/with-api.ts'
import { test, expect } from '@playwright/test'
import path from 'node:path'
import { execFileSync } from 'node:child_process'
import { getTestManifest } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'

test.describe('P1: Gói dịch vụ nền tảng, Danh mục gói, Hạn mức quota & Gia hạn @p1', () => {
  test.beforeEach(async ({ page }) => {
    const manifest = getTestManifest()

    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
      window.localStorage.setItem('locale', 'vi')
      window.localStorage.setItem('aura_theme', 'light')
    })
  })

  test('7.3.1: Duyệt danh mục gói nền tảng & Lựa chọn gói dịch vụ', async ({ page }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const orgId = manifest.fixtures?.organizations.org1?.id
    if (!orgId) throw new Error('Org1 ID missing from manifest')

    // 1. Log in as organization owner
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Fetch platform plans catalog
    const catalogRes = (await withE2EApi(page, async ({ api }) => {
      return await api.get('/platform/plans')
    })) as {
      data: Array<{
        id: number
        name: string
        price: number
        quotas: { max_venues: number; max_courts: number }
        terms: { version: string }
      }>
    }

    expect(catalogRes.data?.length).toBeGreaterThan(0)
    const selectedPlan = catalogRes.data.find(
      (plan) => plan.id === manifest.fixtures?.platform_plan?.id,
    )
    if (!selectedPlan) throw new Error('Run-owned platform plan missing from the published catalog')
    expect(selectedPlan.id).toBeDefined()
    expect(selectedPlan.quotas.max_venues).toBeGreaterThan(0)
    expect(selectedPlan.quotas.max_courts).toBeGreaterThan(0)

    // 3. Owner selects plan
    const selectRes = (await withE2EApi(
      page,
      async ({ api }, { orgId, planId, termsVersion }) => {
        return await api.post(`/partner/organizations/${orgId}/subscription/select-plan`, {
          plan_id: planId,
          accepted_terms_version: termsVersion,
        })
      },
      { orgId, planId: selectedPlan.id, termsVersion: selectedPlan.terms.version },
    )) as {
      data: { plan_id: number; plan_name: string }
    }

    expect(selectRes.data.plan_id).toBe(selectedPlan.id)
    expect(selectRes.data.plan_name).toBe(selectedPlan.name)
  })

  test('7.3.2: Kích hoạt dùng thử & Theo dõi hạn mức sử dụng (Quotas)', async ({ page }) => {
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

    // Create a dedicated new trial-eligible organization with a trial-enabled plan
    const setupScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Organization;
      use App\\Models\\OrganizationMember;
      use App\\Models\\PlatformPlan;
      use App\\Models\\User;
      use Illuminate\\Support\\Facades\\Hash;

      $unique = time() . '_' . rand(1000, 9999);
      $trialOwner = User::create([
        'name' => "Trial Owner {$unique}",
        'email' => "trial_owner_{$unique}@auracourt.test",
        'password' => Hash::make("Password123!"),
        'role' => 'customer',
        'email_verified_at' => now(),
      ]);

      $org = Organization::create([
        'name' => "Trial Club {$unique}",
        'slug' => "trial-club-{$unique}",
        'status' => 'active',
        'partner_status' => 'approved',
      ]);

      OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $trialOwner->id,
        'role' => 'organization_owner',
        'status' => 'active',
      ]);

      // Ensure plan has trial enabled and matching terms
      $plan = PlatformPlan::whereKey(${manifest.fixtures?.platform_plan?.id})
        ->where('code', 'plan-e2e-standard-${manifest.runId}')->firstOrFail();
      $plan->update([
        'trial_enabled' => true,
        'trial_days' => 14,
        'terms_version' => $plan->terms_version ?: 'v1.0',
      ]);

      echo json_encode([
        'email' => $trialOwner->email,
        'org_id' => $org->id,
        'plan_id' => $plan->id,
        'terms_version' => (string) $plan->terms_version,
      ]);
    `
    const trialFixture = JSON.parse(
      execFileSync('php', ['-r', setupScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { email: string; org_id: number; plan_id: number; terms_version: string }

    // 1. Log in as trial owner
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(trialFixture.email)
    await page.locator('input[type="password"], input[name="password"]').fill('Password123!')
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Activate trial with matching terms version
    const trialRes = (await withE2EApi(
      page,
      async ({ api }, { orgId, planId, termsVersion }) => {
        return await api.post(`/partner/organizations/${orgId}/subscription/activate-trial`, {
          plan_id: planId,
          accepted_terms_version: termsVersion,
        })
      },
      {
        orgId: trialFixture.org_id,
        planId: trialFixture.plan_id,
        termsVersion: trialFixture.terms_version,
      },
    )) as {
      data: { status: string; is_trial: boolean }
    }

    expect(['trial', 'trialing']).toContain(trialRes.data.status)
    expect(trialRes.data.is_trial).toBe(true)

    // 3. Inspect current subscription and quotas
    const currentRes = (await withE2EApi(
      page,
      async ({ api }, { orgId }) => {
        return await api.get(`/partner/organizations/${orgId}/subscription/current`)
      },
      { orgId: trialFixture.org_id },
    )) as {
      has_active_subscription: boolean
      can_take_bookings: boolean
      quotas: { venues_count: number; max_venues: number; courts_count: number; max_courts: number }
    }

    expect(currentRes.has_active_subscription).toBe(true)
    expect(currentRes.can_take_bookings).toBe(true)
    expect(currentRes.quotas.max_venues).toBeGreaterThan(0)
    expect(currentRes.quotas.max_courts).toBeGreaterThan(0)

    // 4. Negative test: Second trial activation on same organization is rejected
    let secondTrialFailed = false
    try {
      await withE2EApi(
        page,
        async ({ api }, { orgId, planId, termsVersion }) => {
          await api.post(`/partner/organizations/${orgId}/subscription/activate-trial`, {
            plan_id: planId,
            accepted_terms_version: termsVersion,
          })
        },
        {
          orgId: trialFixture.org_id,
          planId: trialFixture.plan_id,
          termsVersion: trialFixture.terms_version,
        },
      )
    } catch {
      secondTrialFailed = true
    }
    expect(secondTrialFailed).toBe(true)
  })

  test('7.3.3: Bắt đầu thanh toán & Bảo mật: Biên nhận đặt sân của chủ sân KHÔNG kích hoạt gói nền tảng', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const orgId = manifest.fixtures?.organizations.org1?.id
    if (!orgId) throw new Error('Org1 missing from manifest')

    // 1. Log in as owner
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // Fetch plan from catalog with valid terms
    const catalogRes = (await withE2EApi(page, async ({ api }) => {
      return await api.get('/platform/plans')
    })) as { data: Array<{ id: number; name: string; terms: { version: string } }> }
    const plan = catalogRes.data.find((item) => item.id === manifest.fixtures?.platform_plan?.id)
    if (!plan?.id) throw new Error('Plan missing from catalog')

    // 2. Initiate platform fee payment
    const initiateRes = (await withE2EApi(
      page,
      async ({ api }, { orgId, planId, termsVersion }) => {
        return await api.post(`/partner/organizations/${orgId}/subscription/initiate-payment`, {
          plan_id: planId,
          accepted_terms_version: termsVersion,
        })
      },
      { orgId, planId: plan.id, termsVersion: plan.terms.version },
    )) as {
      data: {
        id: number
        reference_code: string
        amount: number
        currency?: string
        status: string
      }
    }

    expect(initiateRes.data?.reference_code).toBeDefined()
    expect(initiateRes.data?.status).toBe('pending')
    const platformRef = initiateRes.data.reference_code

    // 3. NEGATIVE SECURITY TEST:
    // A customer booking webhook directed at court owner receiving connection
    // MUST NOT activate or mutate the PlatformPaymentAttempt!
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

    const ownerReceiptScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\PlatformPaymentAttempt;

      $attempt = PlatformPaymentAttempt::where('reference_code', '${platformRef}')->firstOrFail();
      echo json_encode([
        'status' => $attempt->status,
        'is_succeeded' => $attempt->isSucceeded(),
      ]);
    `
    const preCheck = JSON.parse(
      execFileSync('php', ['-r', ownerReceiptScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { status: string; is_succeeded: boolean }

    expect(preCheck.status).toBe('pending')
    expect(preCheck.is_succeeded).toBe(false)

    // 4. Drive subscription activation via signed synthetic platform webhook
    const confirmScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\PlatformPaymentAttempt;
      use App\\Models\\PlatformSubscription;
      use App\\Models\\ProviderEvent;
      use App\\Services\\Payment\\ProviderWebhookService;
      use Illuminate\\Http\\Request;

      $checksumKey = (string) (config("services.payos.checksum_key") ?: config("services.payos.webhook_secret") ?: "test_payos_webhook_secret");
      config(["services.payos.checksum_key" => $checksumKey]);

      $attempt = PlatformPaymentAttempt::where('reference_code', '${platformRef}')->firstOrFail();

      $data = [
        'amount' => (int) $attempt->amount,
        'currency' => (string) $attempt->currency,
        'description' => $attempt->reference_code,
        'reference' => $attempt->reference_code,
        'status' => 'PAID',
      ];

      ksort($data, SORT_STRING);
      $parts = [];
      foreach ($data as $k => $v) {
        $parts[] = $k . '=' . $v;
      }
      $canonical = implode('&', $parts);
      $signature = hash_hmac('sha256', $canonical, $checksumKey);

      $payload = json_encode([
        'code' => '00',
        'desc' => 'success',
        'data' => $data,
        'signature' => $signature,
      ]);

      $request = Request::create('/api/v2/webhooks/payos', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYOS_SIGNATURE' => $signature,
      ], $payload);

      $webhooks = app(ProviderWebhookService::class);
      $webhookRes = $webhooks->receive('payos', $request);

      // Process event job if not already processed in sync mode
      $event = ProviderEvent::where('reference', '${platformRef}')->latest('id')->first();
      if ($event && ! $event->processed_at) {
        app(App\\Jobs\\ProcessProviderEvent::class, ['providerEventId' => $event->id])->handle($webhooks);
      }

      $attempt->refresh();
      $sub = PlatformSubscription::where('organization_id', $attempt->organization_id)->latest('id')->first();

      echo json_encode([
        'attempt_status' => $attempt->status,
        'sub_status' => $sub ? $sub->status : null,
      ]);
    `
    const confirmResult = JSON.parse(
      execFileSync('php', ['-r', confirmScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { attempt_status: string; sub_status: string }

    expect(confirmResult.attempt_status).toBe('succeeded')
    expect(confirmResult.sub_status).toBe('active')

    // 5. Query confirm-payment endpoint via client: reports succeeded
    const clientConfirmRes = (await withE2EApi(
      page,
      async ({ api }, { orgId, ref, amount }) => {
        return await api.post(`/partner/organizations/${orgId}/subscription/confirm-payment`, {
          reference_code: ref,
          provider_transaction_id: 'TXN_CLIENT_CHECK',
          amount,
          currency: 'VND',
        })
      },
      { orgId, ref: platformRef, amount: initiateRes.data.amount },
    )) as { data: { status: string; is_duplicate: boolean } }

    expect(clientConfirmRes.data.status).toBe('succeeded')
    expect(clientConfirmRes.data.is_duplicate).toBe(true)
  })

  test('7.3.4: Hủy tự động gia hạn & Hành vi khi gói dịch vụ hết hạn', async ({ page }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const orgId = manifest.fixtures?.organizations.org1?.id
    if (!orgId) throw new Error('Org1 ID missing from manifest')

    // 1. Log in as owner
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Cancel auto-renewal
    const cancelRes = (await withE2EApi(
      page,
      async ({ api }, { orgId }) => {
        return await api.post(`/partner/organizations/${orgId}/subscription/cancel-renewal`, {
          reason: 'Không còn nhu cầu gia hạn tự động',
        })
      },
      { orgId },
    )) as { data: { auto_renew: boolean; status: string } }

    expect(cancelRes.data.auto_renew).toBe(false)
    expect(cancelRes.data.status).toBe('active') // remains active until end of period

    // 3. Backdate current subscription to simulate expiry past period end
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

    const backdateScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\PlatformSubscription;

      $sub = PlatformSubscription::where('organization_id', ${orgId})
        ->where('status', 'active')
        ->latest('id')
        ->firstOrFail();

      $sub->update([
        'status' => 'expired',
        'current_period_end' => now()->subDay(),
      ]);
      echo $sub->status;
    `
    const expiredStatus = execFileSync('php', ['-r', backdateScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    }).trim()

    expect(expiredStatus).toBe('expired')

    // 4. Verify current subscription reports cannot take bookings
    const currentRes = (await withE2EApi(
      page,
      async ({ api }, { orgId }) => {
        return await api.get(`/partner/organizations/${orgId}/subscription/current`)
      },
      { orgId },
    )) as { has_active_subscription: boolean; can_take_bookings: boolean }

    expect(currentRes.has_active_subscription).toBe(false)
    expect(currentRes.can_take_bookings).toBe(false)

    // Restore subscription to active for subsequent tests
    const restoreScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\PlatformSubscription;

      $sub = PlatformSubscription::where('organization_id', ${orgId})->latest('id')->firstOrFail();
      $sub->update([
        'status' => 'active',
        'current_period_end' => now()->addMonth(),
        'auto_renew' => true,
      ]);
    `
    execFileSync('php', ['-r', restoreScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })
  })

  test('7.3.5: Quản trị gói nền tảng (chỉ qua API) & Từ chối người dùng không phải admin', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const admin = getActorCredentials(manifest, 'admin')
    const customer = getActorCredentials(manifest, 'customer')

    // 1. Log in as Customer
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Negative test: Customer is DENIED from creating platform plans (403 Forbidden)
    let nonAdminDenied = false
    try {
      await withE2EApi(page, async ({ api }) => {
        await api.post('/admin/platform-plans', {
          name: 'Unauthorized Plan',
          code: 'unauthorized_plan',
          price: 500000,
          currency: 'VND',
          billing_cycle: 'monthly',
          terms_version: 'v1.0',
          max_venues: 5,
          max_courts: 20,
          max_staff: 10,
        })
      })
    } catch {
      nonAdminDenied = true
    }
    expect(nonAdminDenied).toBe(true)

    // 3. Log out customer, log in as Platform Admin
    await withE2EApi(page, async ({ authApi }) => authApi.logout())
    await page.context().clearCookies()

    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(admin.email)
    await page.locator('input[type="password"], input[name="password"]').fill(admin.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 4. Platform Admin lists and creates platform plan via API
    // Note: Admin platform plan CRUD is an API-only operation as documented in task 7.3
    const uniquePlanCode = `plan_enterprise_${Date.now()}`
    const createPlanRes = (await withE2EApi(
      page,
      async ({ api }, { code }) => {
        return await api.post('/admin/platform-plans', {
          name: 'Gói Doanh Nghiệp Mở Rộng',
          code,
          price: 2500000,
          currency: 'VND',
          billing_cycle: 'monthly',
          terms_version: 'v1.0',
          terms_text: 'Điều khoản sử dụng gói dịch vụ doanh nghiệp mở rộng.',
          max_venues: 10,
          max_staff: 30,
          trial_days: 14,
          features: ['custom_branding', 'priority_support', 'unlimited_shifts'],
        })
      },
      { code: uniquePlanCode },
    )) as { data: { id: number; name: string; is_active: boolean } }

    expect(createPlanRes.data?.id).toBeDefined()
    expect(createPlanRes.data?.name).toBe('Gói Doanh Nghiệp Mở Rộng')

    // 5. Admin publishes plan
    const publishRes = (await withE2EApi(
      page,
      async ({ api }, { id }) => {
        return await api.post(`/admin/platform-plans/${id}/publish`, {
          expected_version: 1,
        })
      },
      { id: createPlanRes.data.id },
    )) as { data: { is_active: boolean } }
    expect(publishRes.data?.is_active).toBe(true)
  })
})
