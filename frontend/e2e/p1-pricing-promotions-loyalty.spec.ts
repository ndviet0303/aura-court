import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { withE2EApi } from './runner/with-api.ts'
import { test, expect } from '@playwright/test'
import { getTestManifest } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'
import { execFileSync } from 'node:child_process'
import path from 'node:path'

test.describe('P1: Bảng giá động, Vòng đời khuyến mãi & Quản lý hội viên @p1', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  test('7.7.3: Vòng đời coupon & chiến dịch khuyến mãi, điều kiện áp dụng & hết hạn', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const orgId = org1?.id
    const venue1Id = org1?.venue1A?.id
    const venue2Id = org1?.venue1B?.id
    if (!orgId || !venue1Id || !venue2Id)
      throw new Error('Org1, Venue1A, or Venue1B missing from manifest')

    const backendDir = path.resolve(import.meta.dirname, '../../backend')
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

    // 1. Arrange a campaign with min_spend 150,000 VND scoped to venue1A and coupon PROMO30K
    const promoCode = `PROMO30K_${Date.now()}`
    const arrangePromoScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Coupon;
      use App\\Models\\PromotionCampaign;
      use App\\Models\\User;

      $ownerUser = User::where('email', '${owner.email}')->firstOrFail();

      $campaign = PromotionCampaign::create([
        'organization_id' => ${orgId},
        'name' => 'Chiến dịch Khuyến mãi E2E',
        'status' => PromotionCampaign::STATUS_ACTIVE,
        'venue_id' => ${venue1Id},
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(30),
        'min_spend' => 150000,
        'created_by' => $ownerUser->id,
        'updated_by' => $ownerUser->id,
      ]);

      $coupon = Coupon::create([
        'organization_id' => ${orgId},
        'promotion_campaign_id' => $campaign->id,
        'venue_id' => ${venue1Id},
        'code' => '${promoCode}',
        'discount_type' => 'fixed',
        'discount_value' => 30000,
        'min_spend' => 150000,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(30),
        'status' => Coupon::STATUS_ACTIVE,
        'is_active' => true,
        'created_by' => $ownerUser->id,
        'updated_by' => $ownerUser->id,
      ]);

      echo json_encode(['campaign_id' => $campaign->id, 'coupon_id' => $coupon->id]);
    `

    const arrangedPromo = JSON.parse(
      execFileSync('php', ['-r', arrangePromoScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { campaign_id: number; coupon_id: number }

    // 2. Customer logs in
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 3. Test lifecycle: Valid preview with subtotal >= min_spend and matching venue1A
    const validPreview = (await withE2EApi(
      page,
      async ({ api }, { orgId, code, venueId }) => {
        return await api.post(
          '/coupons/preview',
          {
            subtotal: 200000,
            code,
            venue_id: venueId,
          },
          {
            headers: { 'X-Organization-ID': String(orgId) },
          },
        )
      },
      { orgId, code: promoCode, venueId: venue1Id },
    )) as { discount: number; total: number }

    expect(validPreview.discount).toBe(30000)
    expect(validPreview.total).toBe(170000)

    // 4. Scope condition check: preview on DIFFERENT unassigned venue (venue1B) -> MUST fail with 422
    const wrongVenuePreview = await withE2EApi(
      page,
      async ({ api }, { orgId, code, venueId }) => {
        try {
          await api.post(
            '/coupons/preview',
            {
              subtotal: 200000,
              code,
              venue_id: venueId,
            },
            {
              headers: { 'X-Organization-ID': String(orgId) },
            },
          )
          return { success: true }
        } catch (err: any) {
          return {
            success: false,
            status: err?.status || err?.response?.status,
            message: err?.message || err?.data?.message,
          }
        }
      },
      { orgId, code: promoCode, venueId: venue2Id },
    )

    expect(wrongVenuePreview.success).toBe(false)
    expect(wrongVenuePreview.status).toBe(422)
    expect(wrongVenuePreview.message).toContain('Mã giảm giá không áp dụng cho cơ sở')
    // 5. Min spend check: preview with subtotal 100,000 (< 150,000 min spend) -> discount is 0 (not applied)
    const minSpendPreview = (await withE2EApi(
      page,
      async ({ api }, { orgId, code, venueId }) => {
        return await api.post(
          '/coupons/preview',
          {
            subtotal: 100000,
            code,
            venue_id: venueId,
          },
          {
            headers: { 'X-Organization-ID': String(orgId) },
          },
        )
      },
      { orgId, code: promoCode, venueId: venue1Id },
    )) as { discount: number; total: number }

    expect(minSpendPreview.discount).toBe(0)
    expect(minSpendPreview.total).toBe(100000)

    // 6. Suspend coupon transition
    const suspendScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Coupon;
      $c = Coupon::findOrFail(${arrangedPromo.coupon_id});
      $c->update(['status' => Coupon::STATUS_SUSPENDED, 'is_active' => false]);
    `
    execFileSync('php', ['-r', suspendScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })

    // Suspended preview -> MUST fail with 422
    const suspendedPreview = await withE2EApi(
      page,
      async ({ api }, { orgId, code, venueId }) => {
        try {
          await api.post(
            '/coupons/preview',
            {
              subtotal: 200000,
              code,
              venue_id: venueId,
            },
            {
              headers: { 'X-Organization-ID': String(orgId) },
            },
          )
          return { success: true }
        } catch (err: any) {
          return {
            success: false,
            status: err?.status || err?.response?.status,
            message: err?.message || err?.data?.message,
          }
        }
      },
      { orgId, code: promoCode, venueId: venue1Id },
    )

    expect(suspendedPreview.success).toBe(false)
    expect(suspendedPreview.status).toBe(422)
    expect(suspendedPreview.message).toContain('Mã giảm giá không hợp lệ.')

    // 7. Retire coupon transition
    const retireScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Coupon;
      $c = Coupon::findOrFail(${arrangedPromo.coupon_id});
      $c->update(['status' => Coupon::STATUS_RETIRED, 'is_active' => false]);
    `
    execFileSync('php', ['-r', retireScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })

    // Retired preview -> MUST fail with 422
    const retiredPreview = await withE2EApi(
      page,
      async ({ api }, { orgId, code, venueId }) => {
        try {
          await api.post(
            '/coupons/preview',
            {
              subtotal: 200000,
              code,
              venue_id: venueId,
            },
            {
              headers: { 'X-Organization-ID': String(orgId) },
            },
          )
          return { success: true }
        } catch (err: any) {
          return {
            success: false,
            status: err?.status || err?.response?.status,
            message: err?.message || err?.data?.message,
          }
        }
      },
      { orgId, code: promoCode, venueId: venue1Id },
    )

    expect(retiredPreview.success).toBe(false)
    expect(retiredPreview.status).toBe(422)
    expect(retiredPreview.message).toContain('Mã giảm giá không hợp lệ.')
  })

  test('7.7.4: Chặn kết hợp sai giữa coupon và chiết khấu gói hội viên', async ({ page }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const orgId = org1?.id
    const venue1Id = org1?.venue1A?.id
    if (!orgId || !venue1Id) throw new Error('Org1 missing from manifest')

    const backendDir = path.resolve(import.meta.dirname, '../../backend')
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

    // 1. Arrange a customer with an active membership plan providing 10% court discount,
    // and a coupon with can_combine_with_membership: false
    const nonCombineCode = `NOCOMBINE_${Date.now()}`
    const arrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Coupon;
      use App\\Models\\MembershipEntitlement;
      use App\\Models\\TenantMembershipPlan;
      use App\\Models\\TenantMembership;
      use App\\Models\\User;

      $user = User::where('email', '${customer.email}')->firstOrFail();

      // Create tenant membership plan with 10% discount
      $plan = TenantMembershipPlan::firstOrCreate(
        ['organization_id' => ${orgId}, 'name' => 'Gold VIP Membership'],
        [
          'price' => 300000,
          'status' => 'published',
          'version' => 1,
          'benefits' => [
            'discount_percent' => 10,
          ],
        ]
      );

      // Assign active tenant membership to customer
      $membership = TenantMembership::updateOrCreate(
        ['organization_id' => ${orgId}, 'user_id' => $user->id],
        [
          'plan_id' => $plan->id,
          'plan_version' => 1,
          'status' => 'active',
          'starts_at' => now()->subDay(),
          'ends_at' => now()->addDays(30),
        ]
      );

      MembershipEntitlement::updateOrCreate(
        ['membership_id' => $membership->id, 'benefit_code' => 'court_discount_percent'],
        [
          'organization_id' => ${orgId},
          'benefit_value' => ['percent' => 10],
          'starts_at' => now()->subDay(),
          'ends_at' => now()->addDays(30),
        ]
      );
      // Create coupon with can_combine_with_membership = false
      $coupon = Coupon::create([
        'organization_id' => ${orgId},
        'code' => '${nonCombineCode}',
        'discount_type' => 'fixed',
        'discount_value' => 20000,
        'min_spend' => 50000,
        'can_combine_with_membership' => false,
        'status' => Coupon::STATUS_ACTIVE,
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(30),
      ]);

      echo json_encode(['coupon_code' => $coupon->code]);
    `

    const arranged = JSON.parse(
      execFileSync('php', ['-r', arrangeScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { coupon_code: string }

    // 2. Customer logs in
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 3. Customer attempts to apply the non-combinable coupon while possessing active membership discount -> MUST fail with 422
    const nonCombineAttempt = await withE2EApi(
      page,
      async ({ api }, { orgId, code, venueId }) => {
        try {
          await api.post(
            '/coupons/preview',
            {
              subtotal: 100000,
              code,
              venue_id: venueId,
            },
            {
              headers: { 'X-Organization-ID': String(orgId) },
            },
          )
          return { success: true }
        } catch (err: any) {
          return {
            success: false,
            status: err?.status || err?.response?.status,
            message: err?.message || err?.data?.message,
          }
        }
      },
      { orgId, code: arranged.coupon_code, venueId: venue1Id },
    )

    try {
      expect(nonCombineAttempt.success).toBe(false)
      expect(nonCombineAttempt.status).toBe(422)
      expect(nonCombineAttempt.message).toContain(
        'Mã giảm giá không thể kết hợp cùng ưu đãi hội viên trên cùng một đơn hàng.',
      )
    } finally {
      // Cleanup customer membership so subsequent test suites in the run are not polluted with discount
      const cleanupScript = `
        require "vendor/autoload.php";
        $app = require_once "bootstrap/app.php";
        $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
        $kernel->bootstrap();

        use App\\Models\\MembershipEntitlement;
        use App\\Models\\TenantMembership;
        use App\\Models\\User;

        $u = User::where('email', '${customer.email}')->first();
        if ($u) {
          $mIds = TenantMembership::where('user_id', $u->id)->pluck('id');
          MembershipEntitlement::whereIn('membership_id', $mIds)->delete();
          TenantMembership::where('user_id', $u->id)->delete();
        }
      `
      try {
        execFileSync('php', ['-r', cleanupScript], {
          cwd: backendDir,
          env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
          encoding: 'utf-8',
        })
      } catch {
        // Ignore cleanup failure
      }
    }
  })
  test('7.7.5: Vòng đời gói hội viên, quy tắc nhân điểm & lịch sử phiên bản', async ({ page }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const org1 = manifest.fixtures?.organizations.org1
    const orgId = org1?.id
    if (!orgId) throw new Error('Org1 missing from manifest')

    const backendDir = path.resolve(import.meta.dirname, '../../backend')
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

    // 1. Arrange a TenantMembershipPlan in database
    const arrangePlanScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\TenantMembershipPlan;
      use Illuminate\\Support\\Str;

      $plan = TenantMembershipPlan::create([
        'organization_id' => ${orgId},
        'name' => 'Gói Hội Viên Bạch Kim ' . Str::random(4),
        'tier' => 'platinum',
        'price' => 500000,
        'billing_cycle' => 'monthly',
        'duration_months' => 1,
        'status' => 'published',
        'version' => 1,
        'benefits' => [
          'discount_percent' => 15,
          'point_multiplier' => 2,
          'advance_booking_days' => 7,
        ],
      ]);

      echo json_encode(['plan_id' => $plan->id]);
    `

    const arrangedPlan = JSON.parse(
      execFileSync('php', ['-r', arrangePlanScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { plan_id: number }

    // 2. Owner logs in and navigates to /admin/membership
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto('/admin/membership', { waitUntil: 'domcontentloaded' })

    // 3. Verify page title and nav tabs
    await expect(page.locator('.page-title')).toContainText('Quản lý Gói hội viên & Điểm thưởng')
    const navTabs = page.locator('.nav-tab-btn')
    await expect(navTabs).toHaveCount(3)

    // Switch to Version History tab
    await navTabs.nth(2).click()
    await expect(page.locator('.version-timeline-wrapper')).toBeVisible({ timeout: 10000 })

    // 4. Archive plan via API
    const archiveRes = (await withE2EApi(
      page,
      async ({ api }, planId) => {
        return await api.post(`/admin/memberships/plans/${planId}/archive`)
      },
      arrangedPlan.plan_id,
    )) as { message?: string }

    expect(archiveRes.message).toContain('Đã lưu trữ gói hội viên thành công!')

    // 5. Query version history: archived plan preserves subscriber note
    const historyRes = (await withE2EApi(page, async ({ api }) => {
      return await api.get('/admin/memberships/version-history')
    })) as Array<{
      planId: number
      status: string
      note?: string
      version: string
    }>

    const archivedItem = historyRes.find((item) => item.planId === arrangedPlan.plan_id)
    expect(archivedItem).toBeDefined()
    expect(archivedItem?.status).toBe('archived')
    expect(archivedItem?.note).toContain('Đã lưu trữ và bảo lưu quyền lợi cho hội viên cũ.')
  })
})
