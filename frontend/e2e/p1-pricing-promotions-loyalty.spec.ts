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

  test('7.7.1: Quản lý quy tắc giá (thời gian hiệu lực & thứ tự ưu tiên rule)', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const org1 = manifest.fixtures?.organizations.org1
    const orgId = org1?.id
    const venue1Id = org1?.venue1A?.id
    const court1Id = org1?.venue1A?.courts?.[0]
    if (!orgId || !venue1Id || !court1Id)
      throw new Error('Org1, Venue1A, or Court1 missing from manifest')

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

    // 1. Arrange pricing rules for org1: Base rule (priority 1), Peak rule (priority 5), Expired Surge rule (priority 10)
    const arrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\PriceRule;
      use Illuminate\\Support\\Str;

      // Arrange a dedicated court for dynamic pricing tests
      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Định Giá Động ' . Str::random(4),
        'court_number' => 'DYN-' . Str::random(4),
        'surface_type' => 'vinyl',
        'is_active' => true,
      ]);

      // 1. Base rule: priority 1, price 100,000 VND
      $base = PriceRule::create([
        'organization_id' => ${orgId},
        'venue_id' => ${venue1Id},
        'court_id' => $court->id,
        'type' => 'base',
        'price' => 100000,
        'priority' => 1,
        'is_active' => true,
        'version' => 1,
      ]);

      // 2. Peak rule: priority 5, price 180,000 VND (17:00 - 21:00, effective today through next month)
      $peak = PriceRule::create([
        'organization_id' => ${orgId},
        'court_id' => $court->id,
        'type' => 'peak',
        'price' => 180000,
        'priority' => 5,
        'starts_at' => '17:00',
        'ends_at' => '21:00',
        'effective_from' => now()->subDay()->toDateString(),
        'effective_until' => now()->addDays(30)->toDateString(),
        'is_active' => true,
        'version' => 1,
      ]);

      // 3. Expired surge rule: priority 10, price 250,000 VND (effective until yesterday)
      $expired = PriceRule::create([
        'organization_id' => ${orgId},
        'venue_id' => ${venue1Id},
        'court_id' => $court->id,
        'type' => 'surge',
        'price' => 250000,
        'priority' => 10,
        'effective_until' => now()->subDay()->toDateString(),
        'is_active' => true,
        'version' => 1,
      ]);

      echo json_encode([
        'court_id' => $court->id,
        'base_id' => $base->id,
        'peak_id' => $peak->id,
        'expired_id' => $expired->id,
      ]);
    `

    const arranged = JSON.parse(
      execFileSync('php', ['-r', arrangeScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { court_id: number; base_id: number; peak_id: number; expired_id: number }
    expect(arranged.base_id).toBeGreaterThan(0)
    expect(arranged.peak_id).toBeGreaterThan(0)
    expect(arranged.expired_id).toBeGreaterThan(0)

    // 2. Log in as owner and navigate to /admin/pricing
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto('/admin/pricing', { waitUntil: 'domcontentloaded' })

    // 3. Verify page title and priority stack
    await expect(page.locator('.page-title')).toContainText('Thiết Lập Ma Trận Giá Linh Hoạt')
    const kpiBar = page.locator('.pricing-kpi-bar')
    await expect(kpiBar).toBeVisible({ timeout: 10000 })

    // 4. Exercise price resolution logic via PriceQuoteService
    const quoteTestScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Services\\Reservation\\PriceQuoteService;
      use Carbon\\Carbon;

      $court = Court::findOrFail(${arranged.court_id});
      $timezone = $court->venue?->timezone ?: 'Asia/Ho_Chi_Minh';
      $quoteService = app(PriceQuoteService::class);

      // Slot A: Peak time 18:00 today (local timezone, formatted without Z offset so Carbon parses as local time)
      $peakSlot = now()->setTimezone($timezone)->setTime(18, 0)->format('Y-m-d\\TH:i:s');
      $peakSnapshots = $quoteService->priceSnapshots($court, [$peakSlot], 60);

      // Slot B: Off-peak morning 08:00 today
      $offPeakSlot = now()->setTimezone($timezone)->setTime(8, 0)->format('Y-m-d\\TH:i:s');
      $offPeakSnapshots = $quoteService->priceSnapshots($court, [$offPeakSlot], 60);
      echo json_encode([
        'peak_price' => $peakSnapshots[0]['unit_price'],
        'peak_rule_type' => $peakSnapshots[0]['rule']['type'],
        'peak_rule_priority' => $peakSnapshots[0]['rule']['priority'],
        'off_peak_price' => $offPeakSnapshots[0]['unit_price'],
        'off_peak_rule_type' => $offPeakSnapshots[0]['rule']['type'],
      ]);
    `

    const quoteCheck = JSON.parse(
      execFileSync('php', ['-r', quoteTestScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      peak_price: number
      peak_rule_type: string
      peak_rule_priority: number
      off_peak_price: number
      off_peak_rule_type: string
    }

    // Priority precedence: peak rule (180,000 VND, priority 5) wins over base rule (100,000 VND, priority 1)
    expect(quoteCheck.peak_price).toBe(180000)
    expect(quoteCheck.peak_rule_type).toBe('peak')
    expect(quoteCheck.peak_rule_priority).toBe(5)

    // Off-peak falls back to base rule (100,000 VND)
    expect(quoteCheck.off_peak_price).toBe(100000)
    expect(quoteCheck.off_peak_rule_type).toBe('base')
  })

  test('7.7.2: Giá của booking hiện hữu không đổi khi cập nhật bảng giá mới (tính bất biến)', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const orgId = org1?.id
    const venue1Id = org1?.venue1A?.id
    const court1Id = org1?.venue1A?.courts?.[0]
    if (!orgId || !venue1Id || !court1Id) throw new Error('Org1 or Court1 missing from manifest')

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

    // 1. Arrange an existing confirmed booking booked at historical price of 100,000 VND
    const arrangeBookingScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Booking;
      use App\\Models\\BookingSlot;
      use App\\Models\\Court;
      use App\\Models\\PriceRule;
      use App\\Models\\User;
      use Illuminate\\Support\\Str;

      // Arrange a dedicated court for historical booking immutability test
      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Lịch Sử ' . Str::random(4),
        'court_number' => 'HIST-' . Str::random(4),
        'surface_type' => 'vinyl',
        'is_active' => true,
      ]);

      $customerUser = User::where('email', '${customer.email}')->firstOrFail();

      // Seed baseline price rule: 100,000 VND
      $rule = PriceRule::create([
        'organization_id' => ${orgId},
        'venue_id' => ${venue1Id},
        'court_id' => $court->id,
        'type' => 'base',
        'price' => 100000,
        'priority' => 1,
        'is_active' => true,
        'version' => 1,
      ]);

      $bookingCode = 'BK-HIST-' . Str::upper(Str::random(6));
      $booking = Booking::create([
        'code' => $bookingCode,
        'user_id' => $customerUser->id,
        'court_id' => $court->id,
        'total_amount' => 100000,
        'discount_amount' => 0,
        'status' => 'confirmed',
        'payment_model' => 'direct_owner',
        'price_snapshot' => [
          'unit_price' => 100000,
          'subtotal' => 100000,
          'rule_id' => $rule->id,
          'rule_version' => 1,
        ],
      ]);

      $slot = BookingSlot::create([
        'booking_id' => $booking->id,
        'court_id' => $court->id,
        'starts_at' => now()->addDays(5)->setTime(14, 0)->utc(),
        'ends_at' => now()->addDays(5)->setTime(15, 0)->utc(),
        'unit_price' => 100000,
        'status' => 'confirmed',
      ]);

      echo json_encode([
        'court_id' => $court->id,
        'booking_id' => $booking->id,
        'booking_code' => $bookingCode,
        'initial_price' => $booking->total_amount,
      ]);
    `

    const arrangedBooking = JSON.parse(
      execFileSync('php', ['-r', arrangeBookingScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { court_id: number; booking_id: number; booking_code: string; initial_price: number }

    expect(arrangedBooking.initial_price).toBe(100000)

    // 2. Owner later increases court price rules: updates base price to 250,000 VND and adds peak 350,000 VND
    const updatePricesScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Booking;
      use App\\Models\\BookingSlot;
      use App\\Models\\Court;
      use App\\Models\\PriceRule;
      use App\\Services\\Reservation\\PriceQuoteService;

      $courtId = ${arrangedBooking.court_id};
      // Update base price rule to 250,000 VND
      $baseRule = PriceRule::where('court_id', $courtId)->where('type', 'base')->first();
      $baseRule->update(['price' => 250000, 'version' => $baseRule->version + 1]);
      // Verify existing booking unchanged
      $existingBooking = Booking::findOrFail(${arrangedBooking.booking_id});
      $existingSlot = BookingSlot::where('booking_id', ${arrangedBooking.booking_id})->first();

      // Query new quote for future slot on this court
      $court = Court::findOrFail($courtId);
      $newQuote = app(PriceQuoteService::class)->priceSnapshots($court, [now()->addDays(6)->setTime(14, 0)->utc()->toIso8601String()], 60);

      echo json_encode([
        'existing_booking_total' => $existingBooking->total_amount,
        'existing_slot_unit_price' => $existingSlot->unit_price,
        'existing_price_snapshot' => $existingBooking->price_snapshot,
        'new_slot_price' => $newQuote[0]['unit_price'],
      ]);
    `

    const verification = JSON.parse(
      execFileSync('php', ['-r', updatePricesScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      existing_booking_total: number
      existing_slot_unit_price: number
      existing_price_snapshot: any
      new_slot_price: number
    }

    // Historical booking price MUST NOT CHANGE (immutability)
    expect(verification.existing_booking_total).toBe(100000)
    expect(verification.existing_slot_unit_price).toBe(100000)
    expect(verification.existing_price_snapshot.unit_price).toBe(100000)

    // New quote reflects updated pricing
    expect(verification.new_slot_price).toBe(250000)

    // 3. Log in as owner, navigate to /admin/pricing and verify UI reflects updated base rule
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto('/admin/pricing', { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.page-title')).toContainText('Thiết Lập Ma Trận Giá Linh Hoạt')
    await expect(page.locator('.priority-stack-container, .rule-card').first()).toBeVisible({
      timeout: 10000,
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
      async ({ apiV2 }, planId) => {
        return await apiV2.post(`/admin/memberships/plans/${planId}/archive`)
      },
      arrangedPlan.plan_id,
    )) as { message?: string }

    expect(archiveRes.message).toContain('Đã lưu trữ gói hội viên thành công!')

    // 5. Query version history: archived plan preserves subscriber note
    const historyRes = (await withE2EApi(page, async ({ apiV2 }) => {
      return await apiV2.get('/admin/memberships/version-history')
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
