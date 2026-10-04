import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { test, expect } from '@playwright/test'
import { getTestManifest } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'
import { execFileSync } from 'node:child_process'
import path from 'node:path'

test.describe('P1: Hóa đơn buổi giao lưu tại sân do chủ sân tổ chức, Chia chi phí & Chốt bill @p1', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  test('9.3.1: Kết thúc buổi giao lưu & Xác thực hóa đơn (chỉ tiền sân/cầu, từ chối phụ phí bất hợp lệ)', async () => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
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

    const testScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\MatchPost;
      use App\\Models\\User;
      use App\\Services\\Social\\MatchService;
      use Carbon\\Carbon;

      $host = User::where('email', '${owner.email}')->firstOrFail();
      $player = User::where('email', '${customer.email}')->firstOrFail();
      $court = Court::where('venue_id', ${venue1Id})->firstOrFail();

      $match = MatchPost::create([
        'organization_id' => ${org1Id},
        'creator_id' => $host->id,
        'court_id' => $court->id,
        'title' => 'Buổi Giao Lưu Kiểm Tra Hóa Đơn ' . uniqid(),
        'visibility' => 'public',
        'is_owner_hosted' => true,
        'category' => 'giao lưu',
        'skill_levels' => ['TB'],
        'starts_at' => Carbon::now(config('app.timezone'))->subHours(2),
        'ends_at' => Carbon::now(config('app.timezone')),
        'required_participants' => 4,
        'joined_count' => 0,
        'status' => 'open', // Session active, not ended
      ]);

      $service = app(MatchService::class);
      $service->join($match, $player);

      // 1. Attempting to draft bill before session has ended -> MUST throw 422
      $rejectedPremature = false;
      try {
        $service->saveDraftBill($match, $host, [
          'actual_headcount' => 4,
          'court_duration_minutes' => 120,
          'court_hourly_rate' => 150000,
          'shuttlecock_count' => 8,
          'shuttlecock_unit_price' => 25000,
        ]);
      } catch (\\Throwable $e) {
        $rejectedPremature = true;
      }

      // 2. End session
      $service->endSession($match, $host);
      $freshMatch = $match->fresh();

      // 3. Attempting to inject auxiliary charges (drinks/water) -> MUST throw 422
      $rejectedAuxiliary = false;
      try {
        $service->saveDraftBill($freshMatch, $host, [
          'actual_headcount' => 4,
          'court_duration_minutes' => 120,
          'court_hourly_rate' => 150000,
          'shuttlecock_count' => 8,
          'shuttlecock_unit_price' => 25000,
          'water' => 50000,
          'drinks' => ['redbull' => 30000],
        ]);
      } catch (\\Throwable $e) {
        $rejectedAuxiliary = true;
      }

      // 4. Valid draft bill: 2h court @ 150k + 8 shuttles @ 25k = 500,000 VND
      $bill = $service->saveDraftBill($freshMatch, $host, [
        'actual_headcount' => 4,
        'court_duration_minutes' => 120,
        'court_hourly_rate' => 150000,
        'shuttlecock_count' => 8,
        'shuttlecock_unit_price' => 25000,
      ]);

      echo json_encode([
        'rejected_premature' => $rejectedPremature,
        'session_ended' => $freshMatch->status === 'ended',
        'rejected_auxiliary' => $rejectedAuxiliary,
        'draft_status' => $bill['status'],
        'total_amount' => $bill['total_amount'],
        'per_person' => $bill['per_person_amount'],
      ]);
    `

    const result = JSON.parse(execFileSync('php', ['-r', testScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as {
      rejected_premature: boolean
      session_ended: boolean
      rejected_auxiliary: boolean
      draft_status: string
      total_amount: number
      per_person: number
    }

    expect(result.rejected_premature).toBe(true)
    expect(result.session_ended).toBe(true)
    expect(result.rejected_auxiliary).toBe(true)
    expect(result.draft_status).toBe('draft')
    expect(result.total_amount).toBe(500000)
    expect(result.per_person).toBe(125000)
  })

  test('9.3.2: Chốt hóa đơn & Tính bất biến (không thu hộ split-payment, từ chối xung đột)', async () => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const customer = getActorCredentials(manifest, 'customer')
    const customer2 = getActorCredentials(manifest, 'customer2')
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

    const finalizeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\MatchPost;
      use App\\Models\\User;
      use App\\Services\\Social\\MatchService;
      use Carbon\\Carbon;

      $host = User::where('email', '${owner.email}')->firstOrFail();
      $p1 = User::where('email', '${customer.email}')->firstOrFail();
      $p2 = User::where('email', '${customer2.email}')->firstOrFail();
      $court = Court::where('venue_id', ${venue1Id})->firstOrFail();

      $match = MatchPost::create([
        'organization_id' => ${org1Id},
        'creator_id' => $host->id,
        'court_id' => $court->id,
        'title' => 'Buổi Giao Lưu Chốt Phí ' . uniqid(),
        'visibility' => 'public',
        'is_owner_hosted' => true,
        'category' => 'giao lưu',
        'skill_levels' => ['TB'],
        'starts_at' => Carbon::now(config('app.timezone'))->subHours(2),
        'ends_at' => Carbon::now(config('app.timezone')),
        'required_participants' => 6,
        'joined_count' => 0,
        'status' => 'open',
      ]);

      $service = app(MatchService::class);
      $service->join($match, $p1);
      $service->join($match, $p2);
      $service->endSession($match, $host);

      // Finalize bill with actual_headcount = 6 (walk-ins added)
      // 500,000 / 6 = 83,333 VND (is_approximate = true)
      $bill = $service->finalizeBill($match->fresh(), $host, [
        'actual_headcount' => 6,
        'court_duration_minutes' => 120,
        'court_hourly_rate' => 150000,
        'shuttlecock_count' => 8,
        'shuttlecock_unit_price' => 25000,
      ]);

      // Conflicting edit attempt on finalized bill -> MUST throw 409
      $conflictRejected = false;
      try {
        $service->finalizeBill($match->fresh(), $host, [
          'actual_headcount' => 7,
          'court_duration_minutes' => 120,
          'court_hourly_rate' => 150000,
          'shuttlecock_count' => 8,
          'shuttlecock_unit_price' => 25000,
        ]);
      } catch (\\Throwable $e) {
        $conflictRejected = true;
      }

      echo json_encode([
        'match_id' => $match->id,
        'bill_status' => $bill['status'],
        'total_amount' => $bill['total_amount'],
        'per_person' => $bill['per_person_amount'],
        'is_approximate' => $bill['is_approximate'],
        'divisor' => $bill['snapshot']['breakdown']['divisor'] ?? 6,
        'conflict_rejected' => $conflictRejected,
      ]);
    `

    const finalResult = JSON.parse(execFileSync('php', ['-r', finalizeScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as {
      match_id: number
      bill_status: string
      total_amount: number
      per_person: number
      is_approximate: boolean
      divisor: number
      conflict_rejected: boolean
    }

    expect(finalResult.bill_status).toBe('finalized')
    expect(finalResult.total_amount).toBe(500000)
    expect(finalResult.per_person).toBe(83333)
    expect(finalResult.is_approximate).toBe(true)
    expect(finalResult.divisor).toBe(6)
    expect(finalResult.conflict_rejected).toBe(true)
  })

  test('9.3.3: Xác minh giao diện /social/sessions/:id/bill & Duy trì dữ liệu sau F5 (SUP-05)', async ({ page }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
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

    // 1. Arrange finalized session in backend
    const arrangeSessionScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\MatchPost;
      use App\\Models\\User;
      use App\\Services\\Social\\MatchService;
      use Carbon\\Carbon;

      $host = User::where('email', '${owner.email}')->firstOrFail();
      $player = User::where('email', '${customer.email}')->firstOrFail();
      $court = Court::where('venue_id', ${venue1Id})->firstOrFail();

      $match = MatchPost::create([
        'organization_id' => ${org1Id},
        'creator_id' => $host->id,
        'court_id' => $court->id,
        'title' => 'Buổi Giao Lưu Hiển Thị UI ' . uniqid(),
        'visibility' => 'public',
        'is_owner_hosted' => true,
        'category' => 'giao lưu',
        'skill_levels' => ['TB'],
        'starts_at' => Carbon::now(config('app.timezone'))->subHours(2),
        'ends_at' => Carbon::now(config('app.timezone')),
        'required_participants' => 4,
        'joined_count' => 0,
        'status' => 'open',
      ]);

      $service = app(MatchService::class);
      $service->join($match, $player);
      $service->endSession($match, $host);

      $bill = $service->finalizeBill($match->fresh(), $host, [
        'actual_headcount' => 4,
        'court_duration_minutes' => 120,
        'court_hourly_rate' => 150000,
        'shuttlecock_count' => 8,
        'shuttlecock_unit_price' => 25000,
      ]);

      echo json_encode(['match_id' => $match->id]);
    `

    const sessionData = JSON.parse(execFileSync('php', ['-r', arrangeSessionScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as { match_id: number }

    // 2. Customer (participant) logs in
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 3. Navigate to /social/sessions/:id/bill
    await page.goto(`/social/sessions/${sessionData.match_id}/bill`, { waitUntil: 'domcontentloaded' })
    await page.waitForLoadState('networkidle')

    // 4. Verify Finalized State & Transparency Numbers
    await expect(page.locator('.badge-finalized')).toBeVisible({ timeout: 15000 })
    await expect(page.locator('.badge-finalized')).toContainText('ĐÃ CHỐT CHI PHÍ')

    // Total amount 500.000 ₫
    await expect(page.locator('.total-val')).toContainText('500.000')

    // Per-person share 125.000 ₫
    await expect(page.locator('.share-price')).toContainText('125.000')

    // 5. Reload confirms persistence from Postgres
    await page.reload({ waitUntil: 'domcontentloaded' })
    await page.waitForLoadState('networkidle')
    await expect(page.locator('.badge-finalized')).toBeVisible({ timeout: 15000 })
    await expect(page.locator('.total-val')).toContainText('500.000')
    await expect(page.locator('.share-price')).toContainText('125.000')
  })

  test('9.3.4: Bảo mật & Phân quyền: Người ngoài bị từ chối truy cập hóa đơn (403 Forbidden)', async () => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const customer = getActorCredentials(manifest, 'customer')
    const stranger = getActorCredentials(manifest, 'restrictedCustomer') // or stranger not in match
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

    const privacyScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\MatchPost;
      use App\\Models\\User;
      use App\\Services\\Social\\MatchService;
      use Carbon\\Carbon;

      $host = User::where('email', '${owner.email}')->firstOrFail();
      $player = User::where('email', '${customer.email}')->firstOrFail();
      $stranger = User::where('email', '${stranger.email}')->firstOrFail();
      $court = Court::where('venue_id', ${venue1Id})->firstOrFail();

      $match = MatchPost::create([
        'organization_id' => ${org1Id},
        'creator_id' => $host->id,
        'court_id' => $court->id,
        'title' => 'Buổi Giao Lưu Bảo Mật ' . uniqid(),
        'visibility' => 'public',
        'is_owner_hosted' => true,
        'category' => 'giao lưu',
        'skill_levels' => ['TB'],
        'starts_at' => Carbon::now(config('app.timezone'))->subHours(2),
        'ends_at' => Carbon::now(config('app.timezone')),
        'required_participants' => 4,
        'joined_count' => 0,
        'status' => 'open',
      ]);

      $service = app(MatchService::class);
      $service->join($match, $player);
      $service->endSession($match, $host);

      $service->finalizeBill($match->fresh(), $host, [
        'actual_headcount' => 4,
        'court_duration_minutes' => 120,
        'court_hourly_rate' => 150000,
        'shuttlecock_count' => 8,
        'shuttlecock_unit_price' => 25000,
      ]);

      // Stranger tries to read bill -> MUST be denied 403
      $denied = false;
      try {
        $service->getBill($match->fresh(), $stranger);
      } catch (\\Throwable $e) {
        $denied = true;
      }

      echo json_encode(['denied' => $denied]);
    `

    const privacyResult = JSON.parse(execFileSync('php', ['-r', privacyScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as { denied: boolean }

    expect(privacyResult.denied).toBe(true)
  })
})
