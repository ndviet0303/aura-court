import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { test, expect } from '@playwright/test'
import { getTestManifest } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'
import { execFileSync } from 'node:child_process'
import path from 'node:path'

test.describe('P1: Vòng đời chia tiền đặt sân, Mã QR từng phần, Thiếu tiền & Hết hạn @p1', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  test('8.2.1: Tạo nhóm chia tiền với nhiều thành viên/phần đóng góp & xác thực số tiền', async ({
    page,
  }) => {
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

    // 1. Arrange dedicated court and a held reservation of 200,000 VND
    const arrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\PriceRule;
      use App\\Models\\Reservation;
      use App\\Models\\ReservationSlot;
      use App\\Models\\User;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Split Test ' . Str::random(4),
        'court_number' => 'SPLIT-' . Str::random(4),
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

      $user = User::where('email', '${customer.email}')->firstOrFail();
      $start = Carbon::now(config('app.timezone'))->addDays(4)->setTime(18, 0);

      $res = Reservation::create([
        'organization_id' => ${orgId},
        'user_id' => $user->id,
        'court_id' => $court->id,
        'code' => 'RSV-SPLIT-' . Str::upper(Str::random(6)),
        'status' => 'held',
        'currency' => 'VND',
        'total_amount' => 200000,
        'hold_expires_at' => now()->addMinutes(15),
        'payment_model' => 'direct_owner_v1',
      ]);

      ReservationSlot::create([
        'organization_id' => ${orgId},
        'reservation_id' => $res->id,
        'court_id' => $court->id,
        'starts_at' => $start->copy()->utc(),
        'ends_at' => $start->copy()->addHours(2)->utc(),
        'unit_price' => 100000,
        'status' => 'held',
      ]);

      echo json_encode([
        'court_id' => $court->id,
        'reservation_id' => $res->id,
        'reservation_code' => $res->code,
        'user_id' => $user->id,
      ]);
    `

    const arranged = JSON.parse(
      execFileSync('php', ['-r', arrangeScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      court_id: number
      reservation_id: number
      reservation_code: string
      user_id: number
    }

    expect(arranged.reservation_id).toBeGreaterThan(0)

    // 2. Validate split creation constraints via backend service:
    // a. Invalid count (< 2 or > 8) throws 422
    // b. Mismatch sum (!= 200,000) throws 422
    // c. Valid creation (100,000 + 100,000) succeeds and creates attempts
    const validateScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\OwnerPaymentAttempt;
      use App\\Models\\Reservation;
      use App\\Models\\SplitGroup;
      use App\\Models\\User;
      use App\\Services\\Payment\\SplitPaymentService;
      use Illuminate\\Validation\\ValidationException;

      $service = app(SplitPaymentService::class);
      $res = Reservation::find(${arranged.reservation_id});
      $user = User::find(${arranged.user_id});

      $lessSharesError = false;
      try {
        $service->create($res, $user, [['amount' => 200000]]);
      } catch (ValidationException $e) {
        $lessSharesError = true;
      }

      $mismatchSumError = false;
      try {
        $service->create($res, $user, [['amount' => 100000], ['amount' => 50000]]);
      } catch (ValidationException $e) {
        $mismatchSumError = true;
      }

      // Valid creation
      $group = $service->create($res, $user, [['amount' => 100000], ['amount' => 100000]]);
      $attempts = OwnerPaymentAttempt::where('purpose', 'split_share')
        ->where('purpose_id', 'like', 'split_group:' . $group->id . ':%')
        ->get();

      echo json_encode([
        'less_shares_error' => $lessSharesError,
        'mismatch_sum_error' => $mismatchSumError,
        'group_id' => $group->id,
        'expected_amount' => $group->expected_amount,
        'share_count' => $group->share_count,
        'group_status' => $group->status,
        'attempts_count' => $attempts->count(),
      ]);
    `

    const validated = JSON.parse(
      execFileSync('php', ['-r', validateScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      less_shares_error: boolean
      mismatch_sum_error: boolean
      group_id: number
      expected_amount: number
      share_count: number
      group_status: string
      attempts_count: number
    }

    expect(validated.less_shares_error).toBe(true)
    expect(validated.mismatch_sum_error).toBe(true)
    expect(validated.group_id).toBeGreaterThan(0)
    expect(validated.expected_amount).toBe(200000)
    expect(validated.share_count).toBe(2)
    expect(validated.group_status).toBe('pending')
    expect(validated.attempts_count).toBe(2) // 2 payment attempts for the 2 shares

    // 3. Log in as customer and navigate to /split-bill/:id UI
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto(`/split-bill/${validated.group_id}`, { waitUntil: 'domcontentloaded' })

    // 4. Verify UI displays split group details
    await expect(page.locator('[data-testid="split-group"]')).toBeVisible({ timeout: 10000 })
    await expect(page.locator('[data-testid="split-group-id"]')).toContainText(
      String(validated.group_id),
    )
    await expect(page.locator('[data-testid="split-server-state"]')).toContainText('pending')
    const sharesList = page.locator('[data-testid="split-share"]')
    await expect(sharesList).toHaveCount(2)
  })

  test('8.2.2: Các phần đóng góp đã xác thực -> đủ tiền xác nhận nhóm chia và vé đặt sân', async ({
    page,
  }) => {
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

    // 1. Arrange a held reservation and split into 2 shares of 100,000 VND
    const arrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\OwnerPaymentAttempt;
      use App\\Models\\PriceRule;
      use App\\Models\\ReceivingConnection;
      use App\\Models\\Reservation;
      use App\\Models\\ReservationSlot;
      use App\\Models\\SplitGroup;
      use App\\Models\\User;
      use App\\Services\\Payment\\SplitPaymentService;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Split Confirmed ' . Str::random(4),
        'court_number' => 'SPLIT-CONF-' . Str::random(4),
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

      $user = User::where('email', '${customer.email}')->firstOrFail();
      $start = Carbon::now(config('app.timezone'))->addDays(5)->setTime(14, 0);

      $res = Reservation::create([
        'organization_id' => ${orgId},
        'user_id' => $user->id,
        'court_id' => $court->id,
        'code' => 'RSV-SPLIT-' . Str::upper(Str::random(6)),
        'status' => 'held',
        'currency' => 'VND',
        'total_amount' => 200000,
        'hold_expires_at' => now()->addMinutes(15),
        'payment_model' => 'direct_owner_v1',
      ]);

      ReservationSlot::create([
        'organization_id' => ${orgId},
        'reservation_id' => $res->id,
        'court_id' => $court->id,
        'starts_at' => $start->copy()->utc(),
        'ends_at' => $start->copy()->addHours(2)->utc(),
        'unit_price' => 100000,
        'status' => 'held',
      ]);

      $group = app(SplitPaymentService::class)->create($res, $user, [
        ['amount' => 100000],
        ['amount' => 100000],
      ]);

      $share1 = $group->shares[0];
      $share2 = $group->shares[1];

      $attempt1 = OwnerPaymentAttempt::where('purpose_id', 'split_group:' . $group->id . ':share:' . $share1->id)->firstOrFail();
      $attempt2 = OwnerPaymentAttempt::where('purpose_id', 'split_group:' . $group->id . ':share:' . $share2->id)->firstOrFail();

      $connection = ReceivingConnection::where('venue_id', ${venue1Id})->where('status', 'verified')->firstOrFail();

      echo json_encode([
        'group_id' => $group->id,
        'reservation_id' => $res->id,
        'share1_id' => $share1->id,
        'share1_amount' => $share1->amount,
        'share1_ref' => $attempt1->reference,
        'share2_id' => $share2->id,
        'share2_amount' => $share2->amount,
        'share2_ref' => $attempt2->reference,
        'connection_routing_id' => $connection->routing_id,
        'connection_account' => $connection->accountNumber(),
        'transaction_date' => now()->addSeconds(10)->toIso8601String(),
      ]);
    `

    const arranged = JSON.parse(
      execFileSync('php', ['-r', arrangeScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      group_id: number
      reservation_id: number
      share1_id: number
      share1_amount: number
      share1_ref: string
      share2_id: number
      share2_amount: number
      share2_ref: string
      connection_routing_id: string
      connection_account: string
      transaction_date: string
    }

    // 2. Pay Share 1 via SePay webhook
    const sendWebhook = async (ref: string, amount: number, eventId: string) => {
      const payload = JSON.stringify({
        id: eventId,
        transactionDate: arranged.transaction_date,
        accountNumber: arranged.connection_account,
        code: ref,
        content: ref,
        transferType: 'in',
        transferAmount: amount,
        referenceCode: 'BANK-' + eventId,
      })

      const authorizationScript = `
        require "vendor/autoload.php";
        $app = require_once "bootstrap/app.php";
        $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
        $kernel->bootstrap();

        use App\\Models\\ReceivingConnection;
        $conn = ReceivingConnection::where('routing_id', '${arranged.connection_routing_id}')->firstOrFail();
        $secret = $conn->webhook_secret_ciphertext ? \\Illuminate\\Support\\Facades\\Crypt::decryptString($conn->webhook_secret_ciphertext) : 'test-secret';
        echo 'Apikey ' . $secret;
      `
      const authorization = execFileSync('php', ['-r', authorizationScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }).trim()

      const res = await page.request.post(
        `${manifest.backend.url}/api/v1/webhooks/sepay/connections/${arranged.connection_routing_id}`,
        {
          headers: {
            Authorization: authorization,
            'Content-Type': 'application/json',
          },
          data: payload,
        },
      )
      expect(res.ok()).toBe(true)

      // Process receipt
      const procScript = `
        require "vendor/autoload.php";
        $app = require_once "bootstrap/app.php";
        $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
        $kernel->bootstrap();

        use App\\Models\\OwnerPaymentReceipt;
        use App\\Services\\Payment\\OwnerPaymentAttemptService;

        $receipt = OwnerPaymentReceipt::where('provider_event_id', '${eventId}')->first();
        if ($receipt && ! $receipt->processed_at) {
          app(OwnerPaymentAttemptService::class)->processReceipt($receipt->id);
        }
      `
      execFileSync('php', ['-r', procScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      })
    }

    // Pay Share 1
    const eventId1 = 'SPLIT-PAY-1-' + Date.now()
    await sendWebhook(arranged.share1_ref, arranged.share1_amount, eventId1)

    // Check intermediate state: Share 1 is paid, Share 2 pending, group still pending, reservation still held
    const midScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Reservation;
      use App\\Models\\SplitGroup;
      use App\\Models\\SplitShare;

      $group = SplitGroup::find(${arranged.group_id});
      $res = Reservation::find(${arranged.reservation_id});
      $s1 = SplitShare::find(${arranged.share1_id});
      $s2 = SplitShare::find(${arranged.share2_id});

      echo json_encode([
        'group_status' => $group->status,
        'group_paid' => $group->paid_amount,
        'res_status' => $res->status,
        's1_status' => $s1->status,
        's2_status' => $s2->status,
      ]);
    `
    const midState = JSON.parse(
      execFileSync('php', ['-r', midScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      group_status: string
      group_paid: number
      res_status: string
      s1_status: string
      s2_status: string
    }

    expect(midState.s1_status).toBe('paid')
    expect(midState.s2_status).toBe('pending')
    expect(midState.group_status).toBe('pending')
    expect(midState.group_paid).toBe(100000)
    expect(midState.res_status).toBe('held') // Not confirmed yet!

    // Pay Share 2: Reaches 200,000 VND -> MUST confirm group and reservation!
    const eventId2 = 'SPLIT-PAY-2-' + Date.now()
    await sendWebhook(arranged.share2_ref, arranged.share2_amount, eventId2)

    // Check final state: Group is confirmed, Reservation is confirmed!
    const finalScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Reservation;
      use App\\Models\\ReservationSlot;
      use App\\Models\\SplitGroup;
      use App\\Models\\SplitShare;

      $group = SplitGroup::find(${arranged.group_id});
      $res = Reservation::find(${arranged.reservation_id});
      $s1 = SplitShare::find(${arranged.share1_id});
      $s2 = SplitShare::find(${arranged.share2_id});
      $slots = ReservationSlot::where('reservation_id', $res->id)->get();

      echo json_encode([
        'group_status' => $group->status,
        'group_paid' => $group->paid_amount,
        'res_status' => $res->status,
        's1_status' => $s1->status,
        's2_status' => $s2->status,
        'slots_confirmed' => $slots->every(fn ($s) => $s->status === 'confirmed'),
      ]);
    `
    const finalState = JSON.parse(
      execFileSync('php', ['-r', finalScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      group_status: string
      group_paid: number
      res_status: string
      s1_status: string
      s2_status: string
      slots_confirmed: boolean
    }

    expect(finalState.s1_status).toBe('paid')
    expect(finalState.s2_status).toBe('paid')
    expect(finalState.group_paid).toBe(200000)
    expect(finalState.group_status).toBe('confirmed')
    expect(finalState.res_status).toBe('confirmed')
    expect(finalState.slots_confirmed).toBe(true)
  })

  test('8.2.3: Bù tiền thiếu: Người tạo nhóm trả phần còn thiếu -> xác nhận vé đặt sân', async ({
    page,
  }) => {
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

    // 1. Arrange a held reservation (200,000 VND), split into 120,000 + 80,000 VND
    const arrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\OwnerPaymentAttempt;
      use App\\Models\\PriceRule;
      use App\\Models\\ReceivingConnection;
      use App\\Models\\Reservation;
      use App\\Models\\ReservationSlot;
      use App\\Models\\SplitGroup;
      use App\\Models\\User;
      use App\\Services\\Payment\\SplitPaymentService;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Split Cover ' . Str::random(4),
        'court_number' => 'SPLIT-COV-' . Str::random(4),
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

      $user = User::where('email', '${customer.email}')->firstOrFail();
      $start = Carbon::now(config('app.timezone'))->addDays(6)->setTime(16, 0);

      $res = Reservation::create([
        'organization_id' => ${orgId},
        'user_id' => $user->id,
        'court_id' => $court->id,
        'code' => 'RSV-SPLIT-' . Str::upper(Str::random(6)),
        'status' => 'held',
        'currency' => 'VND',
        'total_amount' => 200000,
        'hold_expires_at' => now()->addMinutes(15),
        'payment_model' => 'direct_owner_v1',
      ]);

      ReservationSlot::create([
        'organization_id' => ${orgId},
        'reservation_id' => $res->id,
        'court_id' => $court->id,
        'starts_at' => $start->copy()->utc(),
        'ends_at' => $start->copy()->addHours(2)->utc(),
        'unit_price' => 100000,
        'status' => 'held',
      ]);

      $group = app(SplitPaymentService::class)->create($res, $user, [
        ['amount' => 120000],
        ['amount' => 80000],
      ]);

      $share1 = $group->shares[0];
      $share2 = $group->shares[1];

      $attempt1 = OwnerPaymentAttempt::where('purpose_id', 'split_group:' . $group->id . ':share:' . $share1->id)->firstOrFail();
      $connection = ReceivingConnection::where('venue_id', ${venue1Id})->where('status', 'verified')->firstOrFail();

      echo json_encode([
        'group_id' => $group->id,
        'reservation_id' => $res->id,
        'share1_id' => $share1->id,
        'share1_amount' => $share1->amount,
        'share1_ref' => $attempt1->reference,
        'share2_id' => $share2->id,
        'share2_amount' => $share2->amount,
        'connection_routing_id' => $connection->routing_id,
        'connection_account' => $connection->accountNumber(),
        'transaction_date' => now()->addSeconds(10)->toIso8601String(),
      ]);
    `

    const arranged = JSON.parse(
      execFileSync('php', ['-r', arrangeScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      group_id: number
      reservation_id: number
      share1_id: number
      share1_amount: number
      share1_ref: string
      share2_id: number
      share2_amount: number
      connection_routing_id: string
      connection_account: string
      transaction_date: string
    }

    // 2. Pay Share 1 (120,000 VND) via SePay webhook
    const eventId1 = 'SPLIT-COV-1-' + Date.now()
    const payload1 = JSON.stringify({
      id: eventId1,
      transactionDate: arranged.transaction_date,
      accountNumber: arranged.connection_account,
      code: arranged.share1_ref,
      content: arranged.share1_ref,
      transferType: 'in',
      transferAmount: arranged.share1_amount,
      referenceCode: 'BANK-' + eventId1,
    })

    const authorizationScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\ReceivingConnection;
      $conn = ReceivingConnection::where('routing_id', '${arranged.connection_routing_id}')->firstOrFail();
      $secret = $conn->webhook_secret_ciphertext ? \\Illuminate\\Support\\Facades\\Crypt::decryptString($conn->webhook_secret_ciphertext) : 'test-secret';
      echo 'Apikey ' . $secret;
    `
    const authorization1 = execFileSync('php', ['-r', authorizationScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    }).trim()

    await page.request.post(
      `${manifest.backend.url}/api/v1/webhooks/sepay/connections/${arranged.connection_routing_id}`,
      {
        headers: {
          Authorization: authorization1,
          'Content-Type': 'application/json',
        },
        data: payload1,
      },
    )

    // Process Share 1 receipt
    const procScript1 = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\OwnerPaymentReceipt;
      use App\\Services\\Payment\\OwnerPaymentAttemptService;

      $receipt = OwnerPaymentReceipt::where('provider_event_id', '${eventId1}')->first();
      if ($receipt && ! $receipt->processed_at) {
        app(OwnerPaymentAttemptService::class)->processReceipt($receipt->id);
      }
    `
    execFileSync('php', ['-r', procScript1], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
    })

    // 3. Host calls coverShortfall to cover the remaining 80,000 VND
    const coverScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\OwnerPaymentAttempt;
      use App\\Models\\SplitGroup;
      use App\\Models\\User;
      use App\\Services\\Payment\\SplitPaymentService;

      $group = SplitGroup::find(${arranged.group_id});
      $host = User::find($group->host_user_id);

      // Call coverShortfall
      $coveredGroup = app(SplitPaymentService::class)->coverShortfall($group, $host);
      $coverageAttempt = OwnerPaymentAttempt::where('purpose', 'split_share')
        ->where('purpose_id', 'split_group:' . $group->id . ':coverage')
        ->firstOrFail();

      echo json_encode([
        'coverage_amount' => $coverageAttempt->amount,
        'coverage_ref' => $coverageAttempt->reference,
        'transaction_date' => now()->addSeconds(10)->toIso8601String(),
      ]);
    `
    const coverage = JSON.parse(
      execFileSync('php', ['-r', coverScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      coverage_amount: number
      coverage_ref: string
      transaction_date: string
    }

    expect(coverage.coverage_amount).toBe(80000)

    // 4. Pay the coverage amount (80,000 VND) via webhook
    const eventIdCover = 'SPLIT-COV-2-' + Date.now()
    const payloadCover = JSON.stringify({
      id: eventIdCover,
      transactionDate: coverage.transaction_date,
      accountNumber: arranged.connection_account,
      code: coverage.coverage_ref,
      content: coverage.coverage_ref,
      transferType: 'in',
      transferAmount: coverage.coverage_amount,
      referenceCode: 'BANK-' + eventIdCover,
    })

    const authorizationScriptCover = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\ReceivingConnection;
      $conn = ReceivingConnection::where('routing_id', '${arranged.connection_routing_id}')->firstOrFail();
      $secret = $conn->webhook_secret_ciphertext ? \\Illuminate\\Support\\Facades\\Crypt::decryptString($conn->webhook_secret_ciphertext) : 'test-secret';
      echo 'Apikey ' . $secret;
    `
    const authorizationCover = execFileSync('php', ['-r', authorizationScriptCover], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    }).trim()

    await page.request.post(
      `${manifest.backend.url}/api/v1/webhooks/sepay/connections/${arranged.connection_routing_id}`,
      {
        headers: {
          Authorization: authorizationCover,
          'Content-Type': 'application/json',
        },
        data: payloadCover,
      },
    )

    // Process coverage receipt
    const procScriptCover = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\OwnerPaymentReceipt;
      use App\\Services\\Payment\\OwnerPaymentAttemptService;

      $receipt = OwnerPaymentReceipt::where('provider_event_id', '${eventIdCover}')->first();
      if ($receipt && ! $receipt->processed_at) {
        app(OwnerPaymentAttemptService::class)->processReceipt($receipt->id);
      }
    `
    execFileSync('php', ['-r', procScriptCover], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
    })

    // 5. Verify covered state: host_covers_shortfall is TRUE, Group and Reservation are CONFIRMED!
    const verifyScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Reservation;
      use App\\Models\\SplitGroup;
      use App\\Models\\SplitShare;

      $group = SplitGroup::find(${arranged.group_id});
      $res = Reservation::find(${arranged.reservation_id});
      $s2 = SplitShare::find(${arranged.share2_id});

      echo json_encode([
        'group_status' => $group->status,
        'host_covers_shortfall' => $group->host_covers_shortfall,
        'res_status' => $res->status,
        's2_status' => $s2->status,
        's2_payer_id' => $s2->payer_id,
        'host_id' => $group->host_user_id,
      ]);
    `
    const finalCoveredState = JSON.parse(
      execFileSync('php', ['-r', verifyScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      group_status: string
      host_covers_shortfall: boolean
      res_status: string
      s2_status: string
      s2_payer_id: number
      host_id: number
    }

    expect(finalCoveredState.group_status).toBe('confirmed')
    expect(finalCoveredState.res_status).toBe('confirmed')
    expect(finalCoveredState.host_covers_shortfall).toBe(true)
    expect(finalCoveredState.s2_status).toBe('paid')
    expect(finalCoveredState.s2_payer_id).toBe(finalCoveredState.host_id) // Paid by host!
  })

  test('8.2.4: Nhóm chia tiền hết hạn: Nhóm chưa đủ tiền hết hạn -> giải phóng giữ chỗ, ghi ngoại lệ tài chính cho phần đã trả', async ({
    page,
  }) => {
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

    // 1. Arrange a held reservation split into 2 shares, where Share 1 is paid, but Share 2 is never paid
    const arrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\OwnerPaymentAttempt;
      use App\\Models\\PriceRule;
      use App\\Models\\ReceivingConnection;
      use App\\Models\\Reservation;
      use App\\Models\\ReservationSlot;
      use App\\Models\\SplitGroup;
      use App\\Models\\User;
      use App\\Services\\Payment\\SplitPaymentService;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Split Expire ' . Str::random(4),
        'court_number' => 'SPLIT-EXP-' . Str::random(4),
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

      $user = User::where('email', '${customer.email}')->firstOrFail();
      $start = Carbon::now(config('app.timezone'))->addDays(7)->setTime(11, 0);

      $res = Reservation::create([
        'organization_id' => ${orgId},
        'user_id' => $user->id,
        'court_id' => $court->id,
        'code' => 'RSV-SPLIT-' . Str::upper(Str::random(6)),
        'status' => 'held',
        'currency' => 'VND',
        'total_amount' => 200000,
        'hold_expires_at' => now()->addMinutes(15),
        'payment_model' => 'direct_owner_v1',
      ]);

      ReservationSlot::create([
        'organization_id' => ${orgId},
        'reservation_id' => $res->id,
        'court_id' => $court->id,
        'starts_at' => $start->copy()->utc(),
        'ends_at' => $start->copy()->addHours(2)->utc(),
        'unit_price' => 100000,
        'status' => 'held',
      ]);

      $group = app(SplitPaymentService::class)->create($res, $user, [
        ['amount' => 100000],
        ['amount' => 100000],
      ]);

      $share1 = $group->shares[0];
      $share2 = $group->shares[1];

      $attempt1 = OwnerPaymentAttempt::where('purpose_id', 'split_group:' . $group->id . ':share:' . $share1->id)->firstOrFail();
      $connection = ReceivingConnection::where('venue_id', ${venue1Id})->where('status', 'verified')->firstOrFail();

      echo json_encode([
        'group_id' => $group->id,
        'reservation_id' => $res->id,
        'share1_id' => $share1->id,
        'share1_amount' => $share1->amount,
        'share1_ref' => $attempt1->reference,
        'share2_id' => $share2->id,
        'connection_routing_id' => $connection->routing_id,
        'connection_account' => $connection->accountNumber(),
        'transaction_date' => now()->addSeconds(10)->toIso8601String(),
      ]);
    `

    const arranged = JSON.parse(
      execFileSync('php', ['-r', arrangeScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      group_id: number
      reservation_id: number
      share1_id: number
      share1_amount: number
      share1_ref: string
      share2_id: number
      connection_routing_id: string
      connection_account: string
      transaction_date: string
    }

    // 2. Pay Share 1 via SePay webhook
    const eventIdExp = 'SPLIT-EXP-1-' + Date.now()
    const payloadExp = JSON.stringify({
      id: eventIdExp,
      transactionDate: arranged.transaction_date,
      accountNumber: arranged.connection_account,
      code: arranged.share1_ref,
      content: arranged.share1_ref,
      transferType: 'in',
      transferAmount: arranged.share1_amount,
      referenceCode: 'BANK-' + eventIdExp,
    })

    const authorizationScriptExp = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\ReceivingConnection;
      $conn = ReceivingConnection::where('routing_id', '${arranged.connection_routing_id}')->firstOrFail();
      $secret = $conn->webhook_secret_ciphertext ? \\Illuminate\\Support\\Facades\\Crypt::decryptString($conn->webhook_secret_ciphertext) : 'test-secret';
      echo 'Apikey ' . $secret;
    `
    const authorizationExp = execFileSync('php', ['-r', authorizationScriptExp], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    }).trim()

    await page.request.post(
      `${manifest.backend.url}/api/v1/webhooks/sepay/connections/${arranged.connection_routing_id}`,
      {
        headers: {
          Authorization: authorizationExp,
          'Content-Type': 'application/json',
        },
        data: payloadExp,
      },
    )

    // Process Share 1 receipt
    const procScriptExp = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\OwnerPaymentReceipt;
      use App\\Services\\Payment\\OwnerPaymentAttemptService;

      $receipt = OwnerPaymentReceipt::where('provider_event_id', '${eventIdExp}')->first();
      if ($receipt && ! $receipt->processed_at) {
        app(OwnerPaymentAttemptService::class)->processReceipt($receipt->id);
      }
    `
    execFileSync('php', ['-r', procScriptExp], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
    })

    // 3. Expire this test group after its hold deadline
    const expireScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\FinanceException;
      use App\\Models\\OwnerPaymentAttempt;
      use App\\Models\\Reservation;
      use App\\Models\\SplitGroup;
      use App\\Models\\SplitShare;
      use App\\Services\\Payment\\SplitPaymentService;

      $group = SplitGroup::find(${arranged.group_id});
      $res = Reservation::find(${arranged.reservation_id});
      $group->update(['expires_at' => now()->subMinute()]);
      $res->update(['hold_expires_at' => now()->subMinute()]);

      $service = app(SplitPaymentService::class);
      $expiredGroup = $service->expire($group);
      $expiredCount = $expiredGroup->status === 'expired' ? 1 : 0;

      $freshGroup = $group->fresh(['shares', 'reservation']);
      $s1 = SplitShare::find(${arranged.share1_id});
      $s2 = SplitShare::find(${arranged.share2_id});
      $attempt2 = OwnerPaymentAttempt::where('purpose_id', 'split_group:' . $group->id . ':share:' . $s2->id)->first();

      // The paid share stays traceable as one finance exception, never a refund obligation
      $s1Exceptions = FinanceException::withoutGlobalScopes()
        ->where('type', 'split_expired_paid_share')
        ->where('reference', 'split-share:' . $s1->id)
        ->get();

      echo json_encode([
        'expired_count' => $expiredCount,
        'group_status' => $freshGroup->status,
        'res_status' => $freshGroup->reservation->status,
        's1_status' => $s1->status,
        's2_status' => $s2->status,
        'attempt2_status' => $attempt2?->status,
        's1_exception_count' => $s1Exceptions->count(),
        's1_exception_amount' => $s1Exceptions->first()?->amount ?? 0,
        's1_exception_payment_reference' => $s1Exceptions->first()?->links['payment_reference'] ?? null,
      ]);
    `

    const expiredState = JSON.parse(
      execFileSync('php', ['-r', expireScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      expired_count: number
      group_status: string
      res_status: string
      s1_status: string
      s2_status: string
      attempt2_status: string
      s1_exception_count: number
      s1_exception_amount: number
      s1_exception_payment_reference: string | null
    }

    expect(expiredState.expired_count).toBe(1)
    expect(expiredState.group_status).toBe('expired')
    expect(expiredState.res_status).toBe('expired') // Reservation hold released!
    expect(expiredState.s2_status).toBe('expired') // Unpaid share expired!
    expect(expiredState.attempt2_status).toBe('expired') // Unpaid attempt expired!

    // Paid Share 1 stays traceable as one finance exception the owner settles outside the platform
    expect(expiredState.s1_status).toBe('paid')
    expect(expiredState.s1_exception_count).toBe(1)
    expect(expiredState.s1_exception_amount).toBe(100000)
    expect(expiredState.s1_exception_payment_reference).toBeTruthy()
  })

  test('8.2.5: Bảo mật: Mã tham chiếu chưa xác minh hoặc người lạ cố tình bù tiền bị từ chối', async () => {
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

    // 1. Arrange a held reservation and split group
    const arrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\PriceRule;
      use App\\Models\\Reservation;
      use App\\Models\\ReservationSlot;
      use App\\Models\\SplitGroup;
      use App\\Models\\User;
      use App\\Services\\Payment\\SplitPaymentService;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Split Security ' . Str::random(4),
        'court_number' => 'SPLIT-SEC-' . Str::random(4),
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

      $user = User::where('email', '${customer.email}')->firstOrFail();
      $start = Carbon::now(config('app.timezone'))->addDays(8)->setTime(15, 0);

      $res = Reservation::create([
        'organization_id' => ${orgId},
        'user_id' => $user->id,
        'court_id' => $court->id,
        'code' => 'RSV-SPLIT-' . Str::upper(Str::random(6)),
        'status' => 'held',
        'currency' => 'VND',
        'total_amount' => 200000,
        'hold_expires_at' => now()->addMinutes(15),
        'payment_model' => 'direct_owner_v1',
      ]);

      ReservationSlot::create([
        'organization_id' => ${orgId},
        'reservation_id' => $res->id,
        'court_id' => $court->id,
        'starts_at' => $start->copy()->utc(),
        'ends_at' => $start->copy()->addHours(2)->utc(),
        'unit_price' => 100000,
        'status' => 'held',
      ]);

      $group = app(SplitPaymentService::class)->create($res, $user, [
        ['amount' => 100000],
        ['amount' => 100000],
      ]);

      $share1 = $group->shares[0];

      echo json_encode([
        'group_id' => $group->id,
        'share1_id' => $share1->id,
      ]);
    `

    const arranged = JSON.parse(
      execFileSync('php', ['-r', arrangeScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      group_id: number
      share1_id: number
    }

    // 2. Test security checks:
    // a. payShare with fake payment reference without verified receipt MUST be rejected with 422
    // b. coverShortfall called by unauthorized non-host user MUST be rejected with 403
    const securityScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\SplitGroup;
      use App\\Models\\SplitShare;
      use App\\Models\\User;
      use App\\Services\\Payment\\SplitPaymentService;
      use Symfony\\Component\\HttpKernel\\Exception\\HttpException;

      $service = app(SplitPaymentService::class);
      $group = SplitGroup::find(${arranged.group_id});
      $share = SplitShare::find(${arranged.share1_id});
      $host = User::find($group->host_user_id);
      $otherUser = User::factory()->create();

      $unverifiedRejected = false;
      try {
        $service->payShare($share, $otherUser, 100000, 'FAKE-REF-999');
      } catch (HttpException $e) {
        if ($e->getStatusCode() === 422) {
          $unverifiedRejected = true;
        }
      }

      $unauthorizedCoverRejected = false;
      try {
        $service->coverShortfall($group, $otherUser);
      } catch (HttpException $e) {
        if ($e->getStatusCode() === 403) {
          $unauthorizedCoverRejected = true;
        }
      }

      echo json_encode([
        'unverified_rejected' => $unverifiedRejected,
        'unauthorized_cover_rejected' => $unauthorizedCoverRejected,
      ]);
    `

    const securityResults = JSON.parse(
      execFileSync('php', ['-r', securityScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      unverified_rejected: boolean
      unauthorized_cover_rejected: boolean
    }

    expect(securityResults.unverified_rejected).toBe(true)
    expect(securityResults.unauthorized_cover_rejected).toBe(true)
  })
})
