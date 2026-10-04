import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { test, expect } from '@playwright/test'
import { getTestManifest } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'
import { execFileSync } from 'node:child_process'
import path from 'node:path'

test.describe('P1: Bảo toàn ví cũ, Chặn nạp ví cho nhóm thanh toán trực tiếp & Không sinh credit hoàn tiền ảo @p1', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  test('8.6.1: Nhóm thanh toán trực tiếp bị chặn nạp ví chung với mã lỗi 409 DIRECT_OWNER_PAYMENTS_ENABLED', async ({ page }) => {
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
    const depositRes = await page.request.post(`${manifest.backend.url}/api/v2/wallet/deposits`, {
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
    expect(errBody.message).toContain('Pooled wallet top-ups are disabled for this direct-payment cohort')

    // 3. UI Check: Select the same tenant wallet, then attempt its prohibited top-up.
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto(`/wallet?organization_id=${org1Id}`, { waitUntil: 'domcontentloaded' })
    await page.waitForLoadState('networkidle')

    await expect(page.locator('.wallet-view-page')).toBeVisible({ timeout: 10000 })
    // Click deposit button to open modal
    const depositBtn = page.locator('button:has-text("Nạp Tiền"), .btn-primary:has-text("Nạp")').first()
    await depositBtn.click()

    // In modal, click proceed / continue
    const proceedBtn = page.locator('.modal-backdrop button.btn-primary, .deposit-modal button.btn-primary').first()
    if (await proceedBtn.isVisible()) {
      await proceedBtn.click()
      // Error banner or toast displayed
      await expect(page.locator('.alert-pill-error, .text-danger, .error-message').first()).toBeVisible({ timeout: 10000 })
    }
  })

  test('8.6.2: Bảo toàn số dư ví cũ: Lịch sử giao dịch & số dư được giữ nguyên, không tự động trừ', async ({ page }) => {
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

    const arranged = JSON.parse(execFileSync('php', ['-r', arrangeScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as {
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

    await page.goto(`/wallet?organization_id=${org1Id}`, { waitUntil: 'domcontentloaded' })
    await page.waitForLoadState('networkidle')

    // 3. Verify balance displayed on card (500.000 ₫)
    await expect(page.locator('.wallet-total-card, .wallet-view-page')).toContainText('500.000')

    // 4. Reload page: balance remains strictly 500,000 VND (no auto-conversion or depletion)
    await page.reload({ waitUntil: 'domcontentloaded' })
    await page.waitForLoadState('networkidle')
    await expect(page.locator('.wallet-total-card, .wallet-view-page')).toContainText('500.000')
  })

  test('8.6.3: Nghĩa vụ hoàn tiền của chủ sân không bao giờ tự sinh số dư ví ảo', async () => {
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

    const directRefundScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\OwnerRefundObligation;
      use App\\Models\\PriceRule;
      use App\\Models\\Reservation;
      use App\\Models\\ReservationSlot;
      use App\\Models\\User;
      use App\\Models\\WalletAccount;
      use App\\Services\\Payment\\OwnerRefundService;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;

      $user = User::where('email', '${customer.email}')->firstOrFail();

      // Ensure customer has legacy wallet with 500k balance
      $wallet = WalletAccount::firstOrCreate(
        ['organization_id' => ${org1Id}, 'user_id' => $user->id, 'currency' => 'VND'],
        ['balance' => 500000, 'status' => 'active']
      );
      $initialWalletBalance = $wallet->fresh()->balance;

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Direct Refund ' . Str::random(4),
        'court_number' => 'REF-' . Str::random(4),
        'surface_type' => 'vinyl',
        'is_active' => true,
      ]);

      PriceRule::create([
        'organization_id' => ${org1Id},
        'venue_id' => ${venue1Id},
        'court_id' => $court->id,
        'type' => 'base',
        'price' => 200000,
        'priority' => 1,
        'is_active' => true,
        'version' => 1,
      ]);

      $futureTime = Carbon::now(config('app.timezone'))->addDays(4)->setTime(11, 0);

      // Hold first, then confirm through the verified direct-owner payment lifecycle
      $res = Reservation::create([
        'organization_id' => ${org1Id},
        'user_id' => $user->id,
        'court_id' => $court->id,
        'code' => 'RSV-DIR-REF-' . Str::upper(Str::random(6)),
        'status' => 'held',
        'currency' => 'VND',
        'total_amount' => 200000,
        'payment_model' => 'direct_owner_v1',
        'hold_expires_at' => now()->addHour(),
        'metadata' => [
          'booking_policy_snapshot' => [
            'version' => 1,
            'settings' => ['cancellation' => [
              'deadline_hours' => 24,
              'fee_type' => 'fixed',
              'fee_value' => 0,
              'refund_due_hours' => 48,
            ]],
          ],
        ],
      ]);

      $slot = ReservationSlot::create([
        'organization_id' => ${org1Id},
        'reservation_id' => $res->id,
        'court_id' => $court->id,
        'starts_at' => $futureTime->copy()->utc(),
        'ends_at' => $futureTime->copy()->addHour()->utc(),
        'unit_price' => 200000,
        'status' => 'held',
      ]);

      $connection = \\App\\Models\\ReceivingConnection::findOrFail(\\App\\Models\\Venue::findOrFail(${venue1Id})->current_receiving_connection_id);
      $payments = app(\\App\\Services\\Payment\\OwnerPaymentAttemptService::class);
      $attempt = $payments->createAttempt($connection, $user, 'booking', $res->code,
        'ACREFUND' . Str::upper(Str::random(12)), 200000, 'VND', now()->addHour(), 'e2e-refund-' . Str::uuid());
      $payload = [
        'id' => 'e2e-refund-payment-' . Str::uuid(),
        'transactionDate' => now()->addSeconds(2)->toIso8601String(),
        'accountNumber' => $connection->accountNumber(),
        'code' => $attempt->reference,
        'content' => $attempt->reference,
        'transferType' => 'in',
        'transferAmount' => 200000,
      ];
      $raw = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
      $timestamp = (string) now()->timestamp;
      $secret = \\Illuminate\\Support\\Facades\\Crypt::decryptString($connection->webhook_secret_ciphertext);
      $signature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $raw, $secret);
      $verified = app(\\App\\Services\\Payment\\SePayReceivingConnectionService::class)->verifyInboundWebhook($connection, $timestamp, $signature, $raw);
      $receipt = $payments->receiveVerifiedEvent($connection, $verified);
      $payments->processReceipt($receipt->id);
      $receipt->refresh();
      $attempt->refresh();
      $res->refresh();
      $slot->refresh();
      if ($receipt->status !== 'allocated' || $attempt->status !== 'paid'
          || $attempt->allocations()->count() !== 1
          || $res->status !== 'confirmed' || $slot->status !== 'confirmed') {
        throw new \\RuntimeException('Refund fixture payment failed: ' . ($receipt->exception_code ?? $receipt->status));
      }

      // Execute real OwnerRefundService lifecycle actions
      $refundService = app(OwnerRefundService::class);
      $obligation = $refundService->request(
        $res,
        $user,
        [$slot->id],
        'Customer requested refund',
        ['bank_code' => 'MBBank', 'account_number' => '0123456789', 'account_name' => $user->name],
        'idem_refund_' . Str::random(12)
      );

      $owner = User::where('email', '${getActorCredentials(manifest, 'owner1').email}')->firstOrFail();
      $obligation = $refundService->approve($obligation, $owner);
      $obligation = $refundService->reportExecution($obligation, $owner, 'ACREIMB' . Str::upper(Str::random(8)), 'Chuyển khoản thành công');
      $obligation = $refundService->acknowledge($obligation, $user, true, 'Đã nhận đủ');

      // Assert customer wallet balance AFTER direct refund
      $finalWalletBalance = $wallet->fresh()->balance;

      echo json_encode([
        'initial_balance' => $initialWalletBalance,
        'final_balance' => $finalWalletBalance,
        'obligation_status' => $obligation->fresh()->status,
        'obligation_amount' => $obligation->amount,
      ]);
    `

    const refundResult = JSON.parse(execFileSync('php', ['-r', directRefundScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as {
      initial_balance: number
      final_balance: number
      obligation_status: string
      obligation_amount: number
    }

    expect(['executed', 'acknowledged']).toContain(refundResult.obligation_status)
    expect(refundResult.obligation_amount).toBe(200000)
    // CRITICAL: Wallet balance remains strictly unchanged! No virtual wallet credit created!
    expect(refundResult.final_balance).toBe(refundResult.initial_balance)
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
      use App\\Domain\\Booking\\DTOs\\CreateBookingData;
      use App\\Models\\Court;
      use App\\Models\\PriceRule;
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

      PriceRule::create([
        'organization_id' => ${org1Id},
        'venue_id' => ${venue1Id},
        'court_id' => $court->id,
        'type' => 'base',
        'price' => 150000,
        'priority' => 1,
        'is_active' => true,
        'version' => 1,
      ]);

      $futureTime = Carbon::now(config('app.timezone'))->addDays(5)->setTime(14, 0, 0);

      $checkoutService = app(OrdinaryCheckoutService::class);
      $data = CreateBookingData::fromArray($user->id, [
        'court_id' => $court->id,
        'slots' => [$futureTime->toIso8601String()],
      ]);

      // Call checkoutV1: derives payment_model from venue cohort config
      $booking = $checkoutService->checkoutV1($data);

      // Assert payment model is dynamically derived as direct_owner_v1
      // and status is pending_payment (waiting for direct owner payment attempt, not debited from wallet)
      $walletAfter = $wallet->fresh()->balance;

      echo json_encode([
        'booking_id' => $booking->id,
        'payment_model' => $booking->payment_model,
        'status' => $booking->status,
        'wallet_before' => 500000,
        'wallet_after' => $walletAfter,
      ]);
    `

    const checkoutResult = JSON.parse(execFileSync('php', ['-r', directCheckoutScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as {
      booking_id: number
      payment_model: string
      status: string
      wallet_before: number
      wallet_after: number
    }

    expect(checkoutResult.booking_id).toBeGreaterThan(0)
    expect(checkoutResult.payment_model).toBe('direct_owner_v1') // Strictly direct_owner_v1!
    expect(checkoutResult.status).toBe('pending_payment')
    // Legacy wallet was NOT debited:
    expect(checkoutResult.wallet_after).toBe(500000)
  })
})
