import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { test, expect, type Page } from '@playwright/test'
import { arrangeCourt, getTestManifest, priceCourtPhp } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'
import { execFileSync } from 'node:child_process'
import path from 'node:path'

type Manifest = ReturnType<typeof getTestManifest>

const runPhp = <T>(manifest: Manifest, body: string): T =>
  JSON.parse(
    execFileSync(
      'php',
      [
        '-r',
        `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
      ${body}
    `,
      ],
      {
        cwd: path.resolve(import.meta.dirname, '../../backend'),
        env: getCanonicalBackendEnv(manifest),
        encoding: 'utf-8',
      },
    ),
  ) as T

const loginAs = async (page: Page, actor: { email: string; password: string }) => {
  await page.goto('/login', { waitUntil: 'domcontentloaded' })
  await page.locator('input[type="email"], input[name="email"]').fill(actor.email)
  await page.locator('input[type="password"], input[name="password"]').fill(actor.password)
  await page.locator('button[type="submit"]').click()
  await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })
}

test.describe('P1: Bảo toàn ví cũ, Chặn nạp ví cho nhóm thanh toán trực tiếp @p1', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  test('8.6.1: Nhóm thanh toán trực tiếp bị chặn nạp ví chung với mã lỗi 409 DIRECT_OWNER_PAYMENTS_ENABLED', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const org1Id = org1?.id
    if (!org1Id) throw new Error('Org1 missing from manifest')

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

    // 1. Arrange customer auth token
    const tokenScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\User;
      $user = User::where('email', '${customer.email}')->firstOrFail();
      echo $user->createToken('test')->plainTextToken;
    `
    const token = execFileSync('php', ['-r', tokenScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    }).trim()

    // 2. Direct API call to initiate deposit on direct-payment cohort
    const depositRes = await page.request.post(`${manifest.backend.url}/api/wallet/deposits`, {
      headers: {
        Authorization: `Bearer ${token}`,
        'X-Organization-Id': String(org1Id),
        'Content-Type': 'application/json',
      },
      data: {
        amount: 200000,
        provider: 'sepay',
        organization_id: org1Id,
      },
    })

    // MUST return 409 conflict
    expect(depositRes.status()).toBe(409)
    const errBody = await depositRes.json()
    expect(errBody.code).toBe('DIRECT_OWNER_PAYMENTS_ENABLED')
    expect(errBody.message).toContain('Ví Aura không dùng được')

    // 3. The server decides whether the customer still sees the wallet (`capabilities.wallet_enabled`):
    // true while an active organization uses the legacy wallet or the customer holds a balance.
    const meRes = await page.request.get(`${manifest.backend.url}/api/auth/me`, {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(meRes.ok()).toBe(true)
    const walletEnabled = (await meRes.json()).capabilities?.wallet_enabled
    expect(typeof walletEnabled).toBe('boolean')

    // 4. UI Check: Select the same tenant wallet, then attempt its prohibited top-up.
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto(`/wallet?organization_id=${org1Id}`, { waitUntil: 'domcontentloaded' })
    if (!walletEnabled) {
      // Wallet off: no wallet entry point, and /wallet sends the customer home.
      await expect(page).toHaveURL((url) => url.pathname === '/', { timeout: 15000 })
      await expect(page.locator('.nav-wallet-pill')).toHaveCount(0)
      return
    }

    await expect(page.locator('.wallet-view-page')).toBeVisible({ timeout: 10000 })
    // Click deposit button to open modal
    const depositBtn = page
      .locator('button:has-text("Nạp Tiền"), .btn-primary:has-text("Nạp")')
      .first()
    await depositBtn.click()

    // In modal, click proceed / continue
    const proceedBtn = page
      .locator('.modal-backdrop button.btn-primary, .deposit-modal button.btn-primary')
      .first()
    if (await proceedBtn.isVisible()) {
      await proceedBtn.click()
      // Error banner or toast displayed
      await expect(
        page.locator('.alert-pill-error, .text-danger, .error-message').first(),
      ).toBeVisible({ timeout: 10000 })
    }
  })

  test('8.6.2: Bảo toàn số dư ví cũ: Lịch sử giao dịch & số dư được giữ nguyên, không tự động trừ', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const org1Id = org1?.id
    if (!org1Id) throw new Error('Org1 missing from manifest')

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

    // 1. Arrange customer legacy wallet with 500,000 VND balance and transaction
    const arrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\User;
      use App\\Models\\WalletAccount;
      use App\\Models\\WalletTransaction;
      use Illuminate\\Support\\Str;

      $user = User::where('email', '${customer.email}')->firstOrFail();

      $defaultWallet = app(\\App\\Services\\Payment\\WalletPaymentService::class)->getOrCreateWallet($user->id, ${org1Id});
      $defaultWallet->update(['balance' => 500000]);
      WalletAccount::where('user_id', $user->id)->where('organization_id', ${org1Id})->update(['balance' => 500000]);

      $ref = 'TXN-LEGACY-' . Str::upper(Str::random(6));
      $txn = WalletTransaction::create([
        'organization_id' => $defaultWallet->organization_id,
        'wallet_account_id' => $defaultWallet->id,
        'type' => 'credit',
        'amount' => 500000,
        'balance_after' => 500000,
        'source_type' => 'legacy_deposit',
        'source_id' => random_int(1, 2147483647),
        'reference' => $ref,
      ]);
      echo json_encode([
        'wallet_id' => $defaultWallet->id,
        'balance' => $defaultWallet->balance,
        'reference' => $ref,
      ]);
    `

    const arranged = JSON.parse(
      execFileSync('php', ['-r', arrangeScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      wallet_id: number
      balance: number
      reference: string
    }

    expect(arranged.balance).toBe(500000)

    // 2. Log in and visit /wallet
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })
    // A remaining balance keeps the wallet entry point (`capabilities.wallet_enabled`) on.
    await expect(page.locator('.nav-wallet-pill')).toBeVisible({ timeout: 15000 })

    await page.goto(`/wallet?organization_id=${org1Id}`, { waitUntil: 'domcontentloaded' })

    // 3. Verify balance displayed on card (500.000 ₫)
    await expect(page.locator('.wallet-total-card, .wallet-view-page')).toContainText('500.000')

    // 4. Reload page: balance remains strictly 500,000 VND (no auto-conversion or depletion)
    await page.reload({ waitUntil: 'domcontentloaded' })
    await expect(page.locator('.wallet-total-card, .wallet-view-page')).toContainText('500.000')
  })

  test('8.6.4: Cơ sở thanh toán trực tiếp bắt buộc trả thẳng cho chủ sân (lịch sử chỉ để xem, không thanh toán ví)', async () => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const org1Id = org1?.id
    const venue1Id = org1?.venue1A?.id
    if (!org1Id || !venue1Id) throw new Error('Org1 or Venue1A missing from manifest')

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

    const directCheckoutScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();
      use App\\Models\\Court;
      use App\\Models\\User;
      use App\\Models\\WalletAccount;
      use App\\Services\\Booking\\OrdinaryCheckoutService;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;

      $user = User::where('email', '${customer.email}')->firstOrFail();

      // Ensure customer has 500k in legacy wallet
      $wallet = WalletAccount::firstOrCreate(
        ['organization_id' => ${org1Id}, 'user_id' => $user->id, 'currency' => 'VND'],
        ['balance' => 500000, 'status' => 'active']
      );

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Direct Checkout ' . Str::random(4),
        'court_number' => 'DIR-' . Str::random(4),
        'surface_type' => 'vinyl',
        'is_active' => true,
      ]);

      ${priceCourtPhp(150000)}

      $futureTime = Carbon::now(config('app.timezone'))->addDays(5)->setTime(14, 0, 0);

      $checkoutService = app(OrdinaryCheckoutService::class);

      // Checkout derives payment_model from venue cohort config
      $reservation = $checkoutService->checkout($user, ${org1Id}, [
        'court_id' => $court->id,
        'slots' => [$futureTime->toIso8601String()],
      ]);

      // Assert payment model is dynamically derived as direct_owner_v1
      // and status is held (waiting for direct owner payment attempt, not debited from wallet)
      $walletAfter = $wallet->fresh()->balance;

      echo json_encode([
        'reservation_id' => $reservation->id,
        'payment_model' => $reservation->payment_model,
        'status' => $reservation->status,
        'wallet_before' => 500000,
        'wallet_after' => $walletAfter,
      ]);
    `

    const checkoutResult = JSON.parse(
      execFileSync('php', ['-r', directCheckoutScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      reservation_id: number
      payment_model: string
      status: string
      wallet_before: number
      wallet_after: number
    }

    expect(checkoutResult.reservation_id).toBeGreaterThan(0)
    expect(checkoutResult.payment_model).toBe('direct_owner_v1') // Strictly direct_owner_v1!
    expect(checkoutResult.status).toBe('held')
    // Legacy wallet was NOT debited:
    expect(checkoutResult.wallet_after).toBe(500000)
  })

  test('8.6.5: Cơ sở ngoài danh sách pilot đặt sân qua giao diện bằng ví cũ (legacy_wallet_v1, trừ ví, xác nhận ngay)', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const venue1AId = manifest.fixtures?.organizations.org1?.venue1A?.id
    const planId = manifest.fixtures?.platform_plan?.id
    if (!venue1AId || !planId) throw new Error('E2E fixture missing: venue1A or platform plan')

    // 1. An approved organization outside PILOT_ORGANIZATION_IDS stays on the legacy wallet cohort (D19)
    const cohort = runPhp<{ organization_id: number; venue_id: number }>(
      manifest,
      `
      use App\\Models\\Organization;
      use App\\Models\\PlatformSubscription;
      use App\\Models\\Venue;
      use App\\Models\\VenueBookingPolicy;
      use App\\Models\\VenueOperatingSchedule;
      use Illuminate\\Support\\Str;

      $suffix = Str::lower(Str::random(8));
      $org = Organization::create([
        'name' => 'Legacy Wallet Club ' . $suffix,
        'slug' => 'legacy-wallet-club-' . $suffix,
        'status' => 'active',
        'partner_status' => 'approved',
        'partner_status_changed_at' => now(),
      ]);
      PlatformSubscription::create([
        'organization_id' => $org->id,
        'plan_id' => ${planId},
        'plan_version' => 1,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'auto_renew' => true,
        'starts_at' => now()->subDays(5),
        'ends_at' => now()->addMonth(),
        'price_snapshot' => 500000,
        'currency_snapshot' => 'VND',
      ]);
      $venue = Venue::create([
        'organization_id' => $org->id,
        'name' => 'Legacy Wallet Venue ' . $suffix,
        'slug' => 'legacy-wallet-venue-' . $suffix,
        'address' => '12 Nguyen Trai, District 1',
        'city' => 'Ho Chi Minh',
        'district' => 'District 1',
        'status' => 'published',
        'is_active' => true,
        'published_at' => now(),
      ]);
      // The pilot venue's hours and booking policy, so only the cohort differs
      foreach ([VenueOperatingSchedule::class, VenueBookingPolicy::class] as $model) {
        $copy = $model::where('venue_id', ${venue1AId})->orderBy('version')->firstOrFail()->replicate();
        $copy->venue_id = $venue->id;
        $copy->save();
      }

      echo json_encode(['organization_id' => $org->id, 'venue_id' => $venue->id]);
    `,
    )
    const court = arrangeCourt(manifest, {
      testKey: '8_6_5_legacy_wallet',
      venueId: cohort.venue_id,
      price: 120000,
    })
    expect(court.organization_id).toBe(cohort.organization_id)

    const wallet = runPhp<{ wallet_id: number; balance: number }>(
      manifest,
      `
      use App\\Models\\User;
      use App\\Services\\Payment\\WalletPaymentService;

      $user = User::where('email', '${customer.email}')->firstOrFail();
      $wallet = app(WalletPaymentService::class)->getOrCreateWallet($user->id, ${cohort.organization_id});
      $wallet->update(['balance' => 500000]);

      echo json_encode(['wallet_id' => $wallet->id, 'balance' => (int) $wallet->fresh()->balance]);
    `,
    )
    expect(wallet.balance).toBe(500000)

    // 2. The customer books through the schedule page
    await loginAs(page, customer)
    await page.goto(`/courts/${court.court_id}/schedule`, { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.schedule-split-layout, .schedule-view-page').first()).toBeVisible({
      timeout: 20000,
    })
    const targetCard = page.locator('.date-carousel .date-card-btn').nth(2)
    await targetCard.waitFor({ state: 'visible', timeout: 15000 })
    await targetCard.click()
    await expect(targetCard).toHaveClass(/active/, { timeout: 10000 })
    const availableSlot = page.locator('.slots-grid .slot-cell-btn.is-available').first()
    await expect(availableSlot).toBeVisible({ timeout: 25000 })
    await availableSlot.click()

    // 3. Checkout pays from the wallet: the reservation opens confirmed, with no transfer QR
    const checkoutBtn = page.locator('.schedule-summary-card .btn-checkout')
    await expect(checkoutBtn).toBeEnabled({ timeout: 10000 })
    await checkoutBtn.click()
    await expect(page).toHaveURL(/\/reservations\/RSV[A-Z0-9]+/, { timeout: 20000 })
    const reservationCode = page.url().split('/reservations/')[1]?.split('?')[0]
    await expect(page.locator('.status-badge.confirmed')).toBeVisible({ timeout: 15000 })
    await expect(page.locator('[data-testid="reservation-payment-modal"]')).toHaveCount(0)

    // 4. Authoritative check: legacy wallet model, wallet debited once, no owner payment attempt
    const state = runPhp<{
      status: string
      payment_model: string
      organization_id: number
      total_amount: number
      balance: number
      debit_amounts: number[]
      attempt_count: number
    }>(
      manifest,
      `
      use App\\Models\\OwnerPaymentAttempt;
      use App\\Models\\Reservation;
      use App\\Models\\WalletAccount;
      use App\\Models\\WalletTransaction;

      $reservation = Reservation::withoutGlobalScope('tenant_isolation')->where('code', '${reservationCode}')->firstOrFail();
      echo json_encode([
        'status' => $reservation->status,
        'payment_model' => $reservation->payment_model,
        'organization_id' => (int) $reservation->organization_id,
        'total_amount' => (int) $reservation->total_amount,
        'balance' => (int) WalletAccount::withoutGlobalScope('tenant_isolation')->findOrFail(${wallet.wallet_id})->balance,
        'debit_amounts' => WalletTransaction::withoutGlobalScope('tenant_isolation')
          ->where('wallet_account_id', ${wallet.wallet_id})
          ->where('type', 'debit')
          ->where('source_type', 'reservation_payment')
          ->where('source_id', $reservation->id)
          ->pluck('amount')->map(fn ($amount) => (int) $amount)->all(),
        'attempt_count' => OwnerPaymentAttempt::where('purpose_id', $reservation->code)->count(),
      ]);
    `,
    )

    expect(state.status).toBe('confirmed')
    expect(state.payment_model).toBe('legacy_wallet_v1')
    expect(state.organization_id).toBe(cohort.organization_id)
    expect(state.total_amount).toBe(120000)
    expect(state.debit_amounts).toEqual([120000])
    expect(state.balance).toBe(380000)
    expect(state.attempt_count).toBe(0)
  })

  test('8.6.6: Lịch sử /booking hiển thị booking cũ đã ánh xạ bằng mã gốc và trạng thái cũ, không lộ mã LEGACY-', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const venue1AId = manifest.fixtures?.organizations.org1?.venue1A?.id
    if (!venue1AId) throw new Error('E2E fixture missing: venue1A')

    // 1. A refunded V1 booking, mirrored by the same backfill the 1.2 run uses
    const court = arrangeCourt(manifest, {
      testKey: '8_6_6_history',
      venueId: venue1AId,
      price: 100000,
    })
    const legacy = runPhp<{
      booking_code: string
      reservation_code: string
      reservation_status: string
      legacy_status: string
    }>(
      manifest,
      `
      use App\\Models\\Booking;
      use App\\Models\\User;
      use App\\Services\\Reservation\\LegacyBookingBackfillService;
      use Illuminate\\Support\\Str;

      $user = User::where('email', '${customer.email}')->firstOrFail();
      $startsAt = now()->subDays(3)->setTime(9, 0)->utc();
      $booking = Booking::create([
        'code' => 'BK' . Str::upper(Str::random(10)),
        'user_id' => $user->id,
        'court_id' => ${court.court_id},
        'status' => 'refunded',
        'total_amount' => 100000,
        'payment_model' => 'legacy_wallet_v1',
      ]);
      $booking->slots()->create([
        'court_id' => ${court.court_id},
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->copy()->addHour(),
        'unit_price' => 100000,
        'status' => 'cancelled',
      ]);
      $mapping = app(LegacyBookingBackfillService::class)->backfillBooking($booking->fresh());

      echo json_encode([
        'booking_code' => $booking->code,
        'reservation_code' => $mapping->reservation->code,
        'reservation_status' => $mapping->reservation->status,
        'legacy_status' => $mapping->legacy_status,
      ]);
    `,
    )
    expect(legacy.reservation_code).toBe(`LEGACY-${legacy.booking_code}`)
    expect(legacy.reservation_status).toBe('legacy')
    expect(legacy.legacy_status).toBe('refunded')

    // 2. The customer's history shows the original code and the V1 status
    await loginAs(page, customer)
    await page.goto('/booking', { waitUntil: 'domcontentloaded' })
    const card = page.locator('.user-booking-card').filter({ hasText: `#${legacy.booking_code}` })
    await expect(card).toBeVisible({ timeout: 20000 })
    await expect(card.locator('.status-chip')).toHaveClass(/status-refunded/)
    await expect(card.locator('.status-chip')).toContainText(/hoàn tiền/i)
    await expect(card).not.toContainText('LEGACY-')
  })

  test('8.6.7: Đánh giá sau khi chơi xong ghi theo reservation_id, không gắn booking_id', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const org1Id = manifest.fixtures?.organizations.org1?.id
    const venue1AId = manifest.fixtures?.organizations.org1?.venue1A?.id
    if (!org1Id || !venue1AId) throw new Error('E2E fixture missing: org1 or venue1A')

    // 1. A confirmed reservation whose only slot ended yesterday, the rows checkout writes
    const court = arrangeCourt(manifest, {
      testKey: '8_6_7_review',
      venueId: venue1AId,
      price: 100000,
    })
    const played = runPhp<{ reservation_id: number; reservation_code: string }>(
      manifest,
      `
      use App\\Models\\Reservation;
      use App\\Models\\ReservationEntitlement;
      use App\\Models\\ReservationSlot;
      use App\\Models\\User;
      use Illuminate\\Support\\Str;

      $user = User::where('email', '${customer.email}')->firstOrFail();
      $startsAt = now()->subDay()->setTime(10, 0)->utc();
      $endsAt = $startsAt->copy()->addHour();
      $reservation = Reservation::create([
        'organization_id' => ${org1Id},
        'user_id' => $user->id,
        'court_id' => ${court.court_id},
        'code' => 'RSV' . Str::upper(Str::random(13)),
        'status' => 'confirmed',
        'currency' => 'VND',
        'total_amount' => 100000,
        'confirmed_at' => now()->subDays(2),
        'payment_model' => 'direct_owner_v1',
      ]);
      $slot = ReservationSlot::create([
        'organization_id' => ${org1Id},
        'reservation_id' => $reservation->id,
        'court_id' => ${court.court_id},
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'unit_price' => 100000,
        'status' => 'confirmed',
        'snapshot' => ['starts_at' => $startsAt->toIso8601String(), 'ends_at' => $endsAt->toIso8601String(), 'unit_price' => 100000],
      ]);
      ReservationEntitlement::create([
        'organization_id' => ${org1Id},
        'reservation_id' => $reservation->id,
        'reservation_slot_id' => $slot->id,
        'user_id' => $user->id,
        'court_id' => ${court.court_id},
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'amount' => 100000,
        'status' => 'active',
      ]);

      echo json_encode(['reservation_id' => $reservation->id, 'reservation_code' => $reservation->code]);
    `,
    )

    // 2. The customer reviews it from the history card
    await loginAs(page, customer)
    await page.goto('/booking', { waitUntil: 'domcontentloaded' })
    const card = page
      .locator('.user-booking-card')
      .filter({ hasText: `#${played.reservation_code}` })
    await expect(card).toBeVisible({ timeout: 20000 })
    await card.getByRole('button', { name: /Đánh Giá/i }).click()
    const reviewModal = page
      .locator('.modal-backdrop')
      .filter({ has: page.locator('.stars-picker') })
    await expect(reviewModal).toBeVisible({ timeout: 10000 })
    await reviewModal.locator('.star-btn').nth(3).click()
    await reviewModal.locator('textarea').fill('Sân sạch, đèn sáng, sẽ quay lại.')
    const reviewResponsePromise = page.waitForResponse(
      (res) => res.url().includes('/api/reviews') && res.request().method() === 'POST',
    )
    await reviewModal.locator('.btn-submit').click()
    expect((await reviewResponsePromise).status()).toBe(201)
    await expect(card.getByRole('button', { name: /4★/ })).toBeVisible({ timeout: 15000 })

    // 3. Authoritative check: the review hangs off the reservation, with no V1 booking link
    const review = runPhp<{
      count: number
      reservation_id: number | null
      booking_id: number | null
      rating: number
    }>(
      manifest,
      `
      use App\\Models\\Review;

      $reviews = Review::where('reservation_id', ${played.reservation_id})->get();
      echo json_encode([
        'count' => $reviews->count(),
        'reservation_id' => $reviews->first()?->reservation_id,
        'booking_id' => $reviews->first()?->booking_id,
        'rating' => (int) $reviews->first()?->rating,
      ]);
    `,
    )
    expect(review.count).toBe(1)
    expect(review.reservation_id).toBe(played.reservation_id)
    expect(review.booking_id).toBeNull()
    expect(review.rating).toBe(4)
  })
})
