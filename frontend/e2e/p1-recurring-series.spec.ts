import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { withE2EApi } from './runner/with-api.ts'
import { test, expect } from '@playwright/test'
import { getTestManifest } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'
import { execFileSync } from 'node:child_process'
import path from 'node:path'

test.describe('P1: Đặt sân định kỳ, Xử lý xung đột, Thanh toán theo kỳ & Giải phóng kỳ quá hạn @p1', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  test('8.1.1: Xem trước chuỗi đặt định kỳ nhiều tuần & tính toán (ngày chơi, số buổi, thời lượng & tổng tiền)', async ({ page }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const orgId = org1?.id
    const venue1Id = org1?.venue1A?.id
    if (!orgId || !venue1Id) throw new Error('Org1 or Venue1A missing from manifest')

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

    // 1. Arrange a dedicated court for recurring preview tests
    const arrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\PriceRule;
      use Illuminate\\Support\\Str;

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Định Kỳ Preview ' . Str::random(4),
        'court_number' => 'REC-PREV-' . Str::random(4),
        'surface_type' => 'vinyl',
        'is_active' => true,
      ]);

      // Base pricing rule: 100,000 VND / hour
      PriceRule::create([
        'organization_id' => ${orgId},
        'venue_id' => ${venue1Id},
        'court_id' => $court->id,
        'type' => 'base',
        'price' => 100000,
        'priority' => 1,
        'is_active' => true,
        'version' => 1,
      ]);

      echo json_encode(['court_id' => $court->id]);
    `

    const arranged = JSON.parse(execFileSync('php', ['-r', arrangeScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as { court_id: number }

    const courtId = arranged.court_id
    expect(courtId).toBeGreaterThan(0)

    // 2. Log in as customer and navigate to /recurring-booking
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto('/recurring-booking', { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.wizard-title')).toContainText('Tạo Lịch Định Kỳ Mới')

    // 3. Query server-side preview via API client with authenticated session
    // Request 4 weekly occurrences on Monday, 09:00 - 11:00 (2 hours per session = 200,000 VND per occurrence)
    // Starting on next Monday
    const nextMonday = new Date()
    const daysUntilNextMonday = ((1 + 7 - nextMonday.getDay()) % 7) || 7
    nextMonday.setDate(nextMonday.getDate() + daysUntilNextMonday)
    const startDateStr = nextMonday.toISOString().slice(0, 10)

    const previewResult = await withE2EApi(page, async ({ apiV2 }, { courtId, startDate }) => {
      return await apiV2.post('/recurring-series/preview', {
        court_id: courtId,
        frequency: 'weekly',
        weekdays: [1],
        starts_at: `${startDate} 09:00:00`,
        ends_at: `${startDate} 11:00:00`,
        occurrences: 4,
      })
    }, { courtId, startDate: startDateStr }) as {
      frequency: string
      weekdays: number[]
      count: number
      currency: string
      total_amount: number
      occurrences: Array<{
        index: number
        starts_at: string
        ends_at: string
        weekday: number
        price: number
        conflict: boolean
        conflict_reason: string | null
      }>
    }

    // 4. Assert preview calculation accuracy
    expect(previewResult.frequency).toBe('weekly')
    expect(previewResult.count).toBe(4)
    expect(previewResult.occurrences.length).toBe(4)
    expect(previewResult.currency).toBe('VND')

    // 2 hours at 100,000 VND/hour = 200,000 VND per session
    for (const occ of previewResult.occurrences) {
      expect(occ.price).toBe(200000)
      expect(occ.conflict).toBe(false)
      expect(occ.conflict_reason).toBeNull()
      expect(occ.weekday).toBe(1) // Monday
      const start = new Date(occ.starts_at)
      const end = new Date(occ.ends_at)
      const durationHours = (end.getTime() - start.getTime()) / (1000 * 60 * 60)
      expect(durationHours).toBe(2)
    }

    // Total amount = 4 * 200,000 = 800,000 VND
    expect(previewResult.total_amount).toBe(800000)
  })

  test('8.1.2: Xử lý xung đột: Chính sách all-or-nothing từ chối cả chuỗi với mã lỗi 409 conflict', async ({ page }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const orgId = org1?.id
    const venue1Id = org1?.venue1A?.id
    if (!orgId || !venue1Id) throw new Error('Org1 or Venue1A missing from manifest')

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

    // 1. Arrange dedicated court, set venue policy to all_or_nothing, and book an existing reservation on week 2
    const arrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\PriceRule;
      use App\\Models\\Reservation;
      use App\\Models\\ReservationSlot;
      use App\\Models\\VenueBookingPolicy;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân All-or-Nothing ' . Str::random(4),
        'court_number' => 'REC-AON-' . Str::random(4),
        'surface_type' => 'vinyl',
        'is_active' => true,
      ]);

      PriceRule::create([
        'organization_id' => ${orgId},
        'venue_id' => ${venue1Id},
        'court_id' => $court->id,
        'type' => 'base',
        'price' => 100000,
        'priority' => 1,
        'is_active' => true,
        'version' => 1,
      ]);

      // Ensure VenueBookingPolicy has conflict_policy: all_or_nothing
      $policy = VenueBookingPolicy::where('venue_id', ${venue1Id})->latest('version')->first();
      $settings = $policy ? $policy->settings : [];
      $settings['recurring']['conflict_policy'] = 'all_or_nothing';
      $settings['recurring']['payment_policy'] = 'per_period';
      VenueBookingPolicy::updateOrCreate(
        ['venue_id' => ${venue1Id}, 'version' => ($policy ? $policy->version + 1 : 1)],
        [
          'settings' => $settings,
          'effective_at' => now()->subDay(),
          'created_by' => 1,
        ]
      );

      // Create an existing reservation on next Tuesday (Day 2 of planned series)
      $nextTuesday = Carbon::now(config('app.timezone'))->next(Carbon::TUESDAY)->setTime(14, 0);
      $conflictingRes = Reservation::create([
        'organization_id' => ${orgId},
        'user_id' => 1,
        'court_id' => $court->id,
        'code' => 'RES-AON-CONF-' . Str::upper(Str::random(6)),
        'total_amount' => 200000,
        'status' => 'confirmed',
      ]);
      ReservationSlot::create([
        'organization_id' => ${orgId},
        'reservation_id' => $conflictingRes->id,
        'court_id' => $court->id,
        'starts_at' => $nextTuesday->copy()->utc(),
        'ends_at' => $nextTuesday->copy()->addHours(2)->utc(),
        'unit_price' => 100000,
        'status' => 'confirmed',
      ]);
      echo json_encode([
        'court_id' => $court->id,
        'conflict_date' => $nextTuesday->toDateString(),
      ]);
    `

    const arranged = JSON.parse(execFileSync('php', ['-r', arrangeScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as { court_id: number; conflict_date: string }

    const courtId = arranged.court_id

    // 2. Log in as customer
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto('/recurring-booking', { waitUntil: 'domcontentloaded' })

    // 3. Preview 3-week series on Tuesday 14:00 - 16:00
    const nextTuesdayDate = arranged.conflict_date

    const preview = await withE2EApi(page, async ({ apiV2 }, { courtId, startDate }) => {
      return await apiV2.post('/recurring-series/preview', {
        court_id: courtId,
        frequency: 'weekly',
        weekdays: [2], // Tuesday
        starts_at: `${startDate} 14:00:00`,
        ends_at: `${startDate} 16:00:00`,
        occurrences: 3,
      })
    }, { courtId, startDate: nextTuesdayDate }) as any

    // Occurrence 1 has conflict!
    expect(preview.occurrences[0].conflict).toBe(true)
    expect(preview.occurrences[0].conflict_reason).toBeDefined()

    // 4. Attempt to submit with all_or_nothing policy: MUST be rejected with 409!
    const submitAttempt = await withE2EApi(page, async ({ apiV2 }, { courtId, startDate, preview }) => {
      try {
        const payload = {
          court_id: courtId,
          frequency: 'weekly',
          weekdays: [2],
          starts_at: `${startDate} 14:00:00`,
          ends_at: `${startDate} 16:00:00`,
          occurrences: 3,
          accepted: {
            total: preview.total_amount,
            occurrences: preview.occurrences.map((o: any) => ({ starts_at: o.starts_at, price: o.price })),
          },
          accept_partial: false,
        }
        const res = await apiV2.post('/recurring-series', payload)
        return { success: true, res }
      } catch (err: any) {
        return { success: false, status: err.status || err.response?.status, message: err.message || err.response?.data?.message || String(err) }
      }
    }, { courtId, startDate: nextTuesdayDate, preview })

    expect(submitAttempt.success).toBe(false)
    expect(submitAttempt.status).toBe(409)
    expect(submitAttempt.message).toContain('all-or-nothing')

    // 5. Verify NO series was created in database
    const verifyScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\RecurringSeries;
      echo json_encode(['series_count' => RecurringSeries::where('court_id', ${courtId})->count()]);
    `
    const verify = JSON.parse(execFileSync('php', ['-r', verifyScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as { series_count: number }
    expect(verify.series_count).toBe(0)
  })

  test('8.1.3: Xử lý xung đột: Đàm phán xung đột từng phần -> chấp nhận các buổi còn trống', async ({ page }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const orgId = org1?.id
    const venue1Id = org1?.venue1A?.id
    if (!orgId || !venue1Id) throw new Error('Org1 or Venue1A missing from manifest')

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

    // 1. Arrange dedicated court, set venue policy to partial conflict, and book an existing reservation on week 2
    const arrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\PriceRule;
      use App\\Models\\Reservation;
      use App\\Models\\ReservationSlot;
      use App\\Models\\VenueBookingPolicy;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Partial Conflict ' . Str::random(4),
        'court_number' => 'REC-PART-' . Str::random(4),
        'surface_type' => 'vinyl',
        'is_active' => true,
      ]);

      PriceRule::create([
        'organization_id' => ${orgId},
        'venue_id' => ${venue1Id},
        'court_id' => $court->id,
        'type' => 'base',
        'price' => 100000,
        'priority' => 1,
        'is_active' => true,
        'version' => 1,
      ]);

      // Ensure VenueBookingPolicy has conflict_policy: partial
      $policy = VenueBookingPolicy::where('venue_id', ${venue1Id})->latest('version')->first();
      $settings = $policy ? $policy->settings : [];
      $settings['recurring']['conflict_policy'] = 'partial';
      $settings['recurring']['payment_policy'] = 'per_period';
      VenueBookingPolicy::updateOrCreate(
        ['venue_id' => ${venue1Id}, 'version' => ($policy ? $policy->version + 1 : 1)],
        [
          'settings' => $settings,
          'effective_at' => now()->subDay(),
          'created_by' => 1,
        ]
      );

      // Start on next Wednesday, conflict on Week 2 Wednesday
      $nextWed = Carbon::now(config('app.timezone'))->next(Carbon::WEDNESDAY)->setTime(10, 0);
      $week2Wed = $nextWed->copy()->addWeek();

      $conflictingRes = Reservation::create([
        'organization_id' => ${orgId},
        'user_id' => 1,
        'court_id' => $court->id,
        'code' => 'RES-PART-CONF-' . Str::upper(Str::random(6)),
        'total_amount' => 100000,
        'status' => 'confirmed',
      ]);
      ReservationSlot::create([
        'organization_id' => ${orgId},
        'reservation_id' => $conflictingRes->id,
        'court_id' => $court->id,
        'starts_at' => $week2Wed->copy()->utc(),
        'ends_at' => $week2Wed->copy()->addHour()->utc(),
        'unit_price' => 100000,
        'status' => 'confirmed',
      ]);
      echo json_encode([
        'court_id' => $court->id,
        'start_date' => $nextWed->toDateString(),
      ]);
    `

    const arranged = JSON.parse(execFileSync('php', ['-r', arrangeScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as { court_id: number; start_date: string }

    const courtId = arranged.court_id

    // 2. Log in as customer
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto('/recurring-booking', { waitUntil: 'domcontentloaded' })

    // 3. Preview 4-week series on Wednesday (Week 1, Week 2 [conflicted], Week 3, Week 4)
    const preview = await withE2EApi(page, async ({ apiV2 }, { courtId, startDate }) => {
      return await apiV2.post('/recurring-series/preview', {
        court_id: courtId,
        frequency: 'weekly',
        weekdays: [3], // Wednesday
        starts_at: `${startDate} 10:00:00`,
        ends_at: `${startDate} 11:00:00`,
        occurrences: 4,
      })
    }, { courtId, startDate: arranged.start_date }) as any

    expect(preview.occurrences.length).toBe(4)
    expect(preview.occurrences[1].conflict).toBe(true) // Week 2 is conflicted

    // 4. Submit without accept_partial -> Returns partial_conflict status with excluded occurrences
    const initialSubmit = await withE2EApi(page, async ({ apiV2 }, { courtId, startDate, preview }) => {
      return await apiV2.post('/recurring-series', {
        court_id: courtId,
        frequency: 'weekly',
        weekdays: [3],
        starts_at: `${startDate} 10:00:00`,
        ends_at: `${startDate} 11:00:00`,
        occurrences: 4,
        accepted: {
          total: preview.total_amount,
          occurrences: preview.occurrences.map((o: any) => ({ starts_at: o.starts_at, price: o.price })),
        },
        accept_partial: false,
      })
    }, { courtId, startDate: arranged.start_date, preview }) as {
      status: string
      currency: string
      held_count: number
      revised_total: number
      excluded: Array<{ index: number; starts_at: string; ends_at: string; conflict_reason: string }>
    }

    expect(initialSubmit.status).toBe('partial_conflict')
    expect(initialSubmit.held_count).toBe(3)
    expect(initialSubmit.revised_total).toBe(300000)
    expect(initialSubmit.excluded.length).toBe(1)
    expect(initialSubmit.excluded[0].index).toBe(2)

    // 5. Accept partial negotiation: submit with accept_partial: true
    const acceptedSubmit = await withE2EApi(page, async ({ apiV2 }, { courtId, startDate, preview }) => {
      return await apiV2.post('/recurring-series', {
        court_id: courtId,
        frequency: 'weekly',
        weekdays: [3],
        starts_at: `${startDate} 10:00:00`,
        ends_at: `${startDate} 11:00:00`,
        occurrences: 4,
        accepted: {
          total: preview.total_amount,
          occurrences: preview.occurrences.map((o: any) => ({ starts_at: o.starts_at, price: o.price })),
        },
        accept_partial: true,
      })
    }, { courtId, startDate: arranged.start_date, preview }) as { series: { id: number; occurrence_count: number; status: string; total_amount: number } }

    expect(acceptedSubmit.series?.id).toBeDefined()
    expect(acceptedSubmit.series.occurrence_count).toBe(3) // Exactly 3 sessions retained!
    expect(acceptedSubmit.series.status).toBe('pending') // Pending payment
    expect(acceptedSubmit.series.total_amount).toBe(300000) // 3 * 100,000 VND
  })

  test('8.1.4: Vòng đời thanh toán theo kỳ: Thanh toán kỳ đầu chỉ xác nhận kỳ đó, các kỳ tương lai giữ chỗ', async ({ page }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const orgId = org1?.id
    const venue1Id = org1?.venue1A?.id
    if (!orgId || !venue1Id) throw new Error('Org1 or Venue1A missing from manifest')

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

    // 1. Arrange dedicated court and recurring series spanning 2 monthly cycles
    const arrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\MonthlyInvoice;
      use App\\Models\\OwnerPaymentAttempt;
      use App\\Models\\PriceRule;
      use App\\Models\\ReceivingConnection;
      use App\\Models\\User;
      use App\\Models\\VenueBookingPolicy;
      use App\\Services\\Booking\\RecurringSeriesService;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Per-Period ' . Str::random(4),
        'court_number' => 'REC-PER-' . Str::random(4),
        'surface_type' => 'vinyl',
        'is_active' => true,
      ]);

      PriceRule::create([
        'organization_id' => ${orgId},
        'venue_id' => ${venue1Id},
        'court_id' => $court->id,
        'type' => 'base',
        'price' => 150000,
        'priority' => 1,
        'is_active' => true,
        'version' => 1,
      ]);

      // Set policy: per_period payment
      $policy = VenueBookingPolicy::where('venue_id', ${venue1Id})->latest('version')->first();
      $settings = $policy ? $policy->settings : [];
      $settings['booking']['max_days_ahead'] = 90;
      $settings['recurring']['payment_policy'] = 'per_period';
      $settings['recurring']['conflict_policy'] = 'all_or_nothing';
      $settings['recurring']['overdue_release_hours'] = 24;
      VenueBookingPolicy::updateOrCreate(
        ['venue_id' => ${venue1Id}, 'version' => ($policy ? $policy->version + 1 : 1)],
        [
          'settings' => $settings,
          'effective_at' => now()->subDay(),
          'created_by' => 1,
        ]
      );

      $user = User::where('email', '${customer.email}')->firstOrFail();

      // Start 2 days in the future, 2 monthly occurrences spanning into next month
      $start = Carbon::now(config('app.timezone'))->addDays(2)->setTime(15, 0);
      $service = app(RecurringSeriesService::class);
      $preview = $service->previewSeries($user, [
        'court_id' => $court->id,
        'frequency' => 'monthly',
        'starts_at' => $start->toIso8601String(),
        'ends_at' => $start->copy()->addHour()->toIso8601String(),
        'occurrences' => 2,
      ]);

      $series = $service->createAndConfirm($user, ${orgId}, [
        'court_id' => $court->id,
        'frequency' => 'monthly',
        'starts_at' => $start->toIso8601String(),
        'ends_at' => $start->copy()->addHour()->toIso8601String(),
        'occurrences' => 2,
        'accepted' => [
          'total' => $preview['total_amount'],
          'occurrences' => array_map(fn ($o) => ['starts_at' => $o['starts_at'], 'price' => $o['price']], $preview['occurrences']),
        ],
      ]);

      // Fetch the 2 monthly invoices generated
      $invoices = MonthlyInvoice::where('recurring_series_id', $series->id)->orderBy('period_start')->get();
      $inv1 = $invoices[0];
      $inv2 = $invoices[1] ?? null;

      $attempt1 = OwnerPaymentAttempt::where('purpose_id', 'rec_series:' . $series->id . ':invoice:' . $inv1->id)->firstOrFail();
      $connection = ReceivingConnection::where('venue_id', ${venue1Id})->where('status', 'verified')->firstOrFail();

      echo json_encode([
        'series_id' => $series->id,
        'inv1_id' => $inv1->id,
        'inv1_amount' => $inv1->amount,
        'attempt1_reference' => $attempt1->reference,
        'connection_routing_id' => $connection->routing_id,
        'connection_account' => $connection->accountNumber(),
        'has_inv2' => $inv2 !== null,
        'inv2_id' => $inv2?->id,
        'transaction_date' => now()->addSeconds(15)->toIso8601String(),
      ]);
    `

    const arranged = JSON.parse(execFileSync('php', ['-r', arrangeScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as {
      series_id: number
      inv1_id: number
      inv1_amount: number
      attempt1_reference: string
      connection_routing_id: string
      connection_account: string
      has_inv2: boolean
      inv2_id: number | null
      transaction_date: string
    }

    expect(arranged.series_id).toBeGreaterThan(0)
    expect(arranged.has_inv2).toBe(true)

    // 2. Simulate QR payment for Period 1 invoice via SePay webhook
    const timestamp = String(Math.floor(Date.now() / 1000))
    const eventId = 'REC-PAY-' + Date.now()
    const webhookPayload = JSON.stringify({
      id: eventId,
      transactionDate: arranged.transaction_date,
      accountNumber: arranged.connection_account,
      code: arranged.attempt1_reference,
      content: arranged.attempt1_reference,
      transferType: 'in',
      transferAmount: arranged.inv1_amount,
      referenceCode: 'BANK-' + eventId,
    })

    const signatureScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\ReceivingConnection;
      $conn = ReceivingConnection::where('routing_id', '${arranged.connection_routing_id}')->firstOrFail();
      $secret = $conn->webhook_secret_ciphertext ? \\Illuminate\\Support\\Facades\\Crypt::decryptString($conn->webhook_secret_ciphertext) : 'test-secret';
      $sig = hash_hmac('sha256', '${timestamp}.' . '${webhookPayload}', $secret);
      echo 'sha256=' . $sig;
    `
    const signature = execFileSync('php', ['-r', signatureScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    }).trim()

    const webhookRes = await page.request.post(`${manifest.backend.url}/api/v1/webhooks/sepay/connections/${arranged.connection_routing_id}`, {
      headers: {
        'X-SePay-Timestamp': timestamp,
        'X-SePay-Signature': signature,
        'Content-Type': 'application/json',
      },
      data: webhookPayload,
    })
    expect(webhookRes.ok()).toBe(true)

    // 3. Process payment receipt and verify period confirmation state
    const verifyScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\MonthlyInvoice;
      use App\\Models\\OwnerPaymentReceipt;
      use App\\Models\\RecurringOccurrence;
      use App\\Models\\RecurringSeries;
      use App\\Services\\Payment\\OwnerPaymentAttemptService;

      $receipt = OwnerPaymentReceipt::where('provider_event_id', '${eventId}')->first();
      if ($receipt && ! $receipt->processed_at) {
        app(OwnerPaymentAttemptService::class)->processReceipt($receipt->id);
      }

      $series = RecurringSeries::find(${arranged.series_id});
      $inv1 = MonthlyInvoice::find(${arranged.inv1_id});
      $inv2 = MonthlyInvoice::find(${arranged.inv2_id});

      $occurrences = RecurringOccurrence::where('recurring_series_id', ${arranged.series_id})->orderBy('sequence')->get();

      echo json_encode([
        'receipt_found' => $receipt !== null,
        'receipt_status' => $receipt?->status,
        'receipt_exception' => $receipt?->exception_code,
        'series_status' => $series->status,
        'inv1_status' => $inv1->status,
        'inv2_status' => $inv2->status,
        'occ1_status' => $occurrences[0]->status,
        'occ2_status' => $occurrences[1]->status,
      ]);
    `

    const postPaymentState = JSON.parse(execFileSync('php', ['-r', verifyScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as {
      receipt_found: boolean
      receipt_status: string | null
      receipt_exception: string | null
      series_status: string
      inv1_status: string
      inv2_status: string
      occ1_status: string
      occ2_status: string
    }


    // Invoice 1 is PAID; Occurrence 1 is CONFIRMED
    expect(postPaymentState.inv1_status).toBe('paid')
    expect(postPaymentState.occ1_status).toBe('confirmed')

    // Invoice 2 is still PENDING; Occurrence 2 is still HELD!
    expect(postPaymentState.inv2_status).toBe('pending')
    expect(postPaymentState.occ2_status).toBe('held')

    // Overall series remains PENDING because period 2 is not yet confirmed!
    expect(postPaymentState.series_status).toBe('pending')
  })

  test('8.1.5: Overdue cycle release: overdue grace expiration releases only that period\'s occurrences', async ({ page }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const orgId = org1?.id
    const venue1Id = org1?.venue1A?.id
    if (!orgId || !venue1Id) throw new Error('Org1 or Venue1A missing from manifest')

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

    // 1. Arrange a 2-period recurring series where Period 1 is already paid and confirmed, and Period 2 is past due
    const arrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\MonthlyInvoice;
      use App\\Models\\OwnerPaymentAttempt;
      use App\\Models\\PriceRule;
      use App\\Models\\RecurringOccurrence;
      use App\\Models\\RecurringSeries;
      use App\\Models\\User;
      use App\\Models\\VenueBookingPolicy;
      use App\\Services\\Booking\\RecurringSeriesService;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Overdue Release ' . Str::random(4),
        'court_number' => 'REC-OVR-' . Str::random(4),
        'surface_type' => 'vinyl',
        'is_active' => true,
      ]);

      PriceRule::create([
        'organization_id' => ${orgId},
        'venue_id' => ${venue1Id},
        'court_id' => $court->id,
        'type' => 'base',
        'price' => 120000,
        'priority' => 1,
        'is_active' => true,
        'version' => 1,
      ]);

      $policy = VenueBookingPolicy::where('venue_id', ${venue1Id})->latest('version')->first();
      $settings = $policy ? $policy->settings : [];
      $settings['booking']['max_days_ahead'] = 90;
      $settings['recurring']['payment_policy'] = 'per_period';
      $settings['recurring']['overdue_release_hours'] = 12;
      VenueBookingPolicy::updateOrCreate(
        ['venue_id' => ${venue1Id}, 'version' => ($policy ? $policy->version + 1 : 1)],
        [
          'settings' => $settings,
          'effective_at' => now()->subDay(),
          'created_by' => 1,
        ]
      );

      $user = User::where('email', '${customer.email}')->firstOrFail();
      // Start 2 days in the future, 2 monthly occurrences spanning into next month
      $start = Carbon::now(config('app.timezone'))->addDays(2)->setTime(16, 0);
      $service = app(RecurringSeriesService::class);
      $preview = $service->previewSeries($user, [
        'court_id' => $court->id,
        'frequency' => 'monthly',
        'starts_at' => $start->toIso8601String(),
        'ends_at' => $start->copy()->addHour()->toIso8601String(),
        'occurrences' => 2,
      ]);

      $series = $service->createAndConfirm($user, ${orgId}, [
        'court_id' => $court->id,
        'frequency' => 'monthly',
        'starts_at' => $start->toIso8601String(),
        'ends_at' => $start->copy()->addHour()->toIso8601String(),
        'occurrences' => 2,
        'accepted' => [
          'total' => $preview['total_amount'],
          'occurrences' => array_map(fn ($o) => ['starts_at' => $o['starts_at'], 'price' => $o['price']], $preview['occurrences']),
        ],
      ]);

      $invoices = MonthlyInvoice::where('recurring_series_id', $series->id)->orderBy('period_start')->get();
      $inv1 = $invoices[0];
      $inv2 = $invoices[1];

      $occurrences = RecurringOccurrence::where('recurring_series_id', $series->id)->orderBy('sequence')->get();

      // Period 1 is previously confirmed
      $inv1->update(['status' => 'paid', 'paid_at' => now()->subDays(10)]);
      $occurrences[0]->update(['status' => 'confirmed']);
      $occurrences[0]->reservation()->update(['status' => 'confirmed']);

      // Period 2 is past due (due_at = 3 days ago, exceeding 12 hours grace)
      $inv2->update(['due_at' => now()->subDays(3)]);
      $attempt2 = OwnerPaymentAttempt::where('purpose_id', 'rec_series:' . $series->id . ':invoice:' . $inv2->id)->first();
      if ($attempt2) {
        $attempt2->update(['expires_at' => now()->subDays(3)]);
      }

      echo json_encode([
        'series_id' => $series->id,
        'inv1_id' => $inv1->id,
        'inv2_id' => $inv2->id,
      ]);
    `

    const arranged = JSON.parse(execFileSync('php', ['-r', arrangeScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as {
      series_id: number
      inv1_id: number
      inv2_id: number
    }

    // 2. Trigger the automated overdue release worker
    const releaseScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\MonthlyInvoice;
      use App\\Models\\OwnerPaymentAttempt;
      use App\\Models\\RecurringOccurrence;
      use App\\Models\\RecurringSeries;
      use App\\Services\\Booking\\RecurringSeriesService;

      $releasedCount = app(RecurringSeriesService::class)->markOverdueAndReleaseFuture();

      $series = RecurringSeries::find(${arranged.series_id});
      $inv1 = MonthlyInvoice::find(${arranged.inv1_id});
      $inv2 = MonthlyInvoice::find(${arranged.inv2_id});
      $occurrences = RecurringOccurrence::where('recurring_series_id', ${arranged.series_id})->orderBy('sequence')->get();
      $attempt2 = OwnerPaymentAttempt::where('purpose_id', 'rec_series:' . ${arranged.series_id} . ':invoice:' . ${arranged.inv2_id})->first();

      echo json_encode([
        'released_count' => $releasedCount,
        'series_status' => $series->status,
        'inv1_status' => $inv1->status,
        'inv2_status' => $inv2->status,
        'occ1_status' => $occurrences[0]->status,
        'occ2_status' => $occurrences[1]->status,
        'occ2_reservation_status' => $occurrences[1]->reservation?->status,
        'attempt2_status' => $attempt2?->status,
      ]);
    `

    const releaseState = JSON.parse(execFileSync('php', ['-r', releaseScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as {
      released_count: number
      series_status: string
      inv1_status: string
      inv2_status: string
      occ1_status: string
      occ2_status: string
      occ2_reservation_status: string
      attempt2_status: string
    }

    // 3. Verify overdue isolation behavior:
    // Period 1 remains confirmed and intact!
    expect(releaseState.inv1_status).toBe('paid')
    expect(releaseState.occ1_status).toBe('confirmed')

    // Period 2 occurrences are RELEASED and reservation cancelled!
    expect(releaseState.inv2_status).toBe('overdue')
    expect(releaseState.occ2_status).toBe('released')
    expect(releaseState.occ2_reservation_status).toBe('cancelled')
    expect(releaseState.attempt2_status).toBe('expired')

    // Series transitions to delinquent without cancelling historical confirmed periods
    expect(releaseState.series_status).toBe('delinquent')
  })
})
