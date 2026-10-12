import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { test, expect } from '@playwright/test'
import { getTestManifest } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'
import { execFileSync } from 'node:child_process'
import path from 'node:path'

test.describe('P1: Doanh thu, Đối soát, Ngoại lệ tài chính & Báo cáo CSV @p1', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  const manifest = getTestManifest()
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

  const owner = getActorCredentials(manifest, 'owner1')
  const admin = getActorCredentials(manifest, 'admin')
  const org1 = manifest.fixtures?.organizations.org1
  const org2 = manifest.fixtures?.organizations.org2
  const org1Id = org1?.id
  const org2Id = org2?.id
  const venue1Id = org1?.venue1A?.id
  const court1Id = org1?.venue1A?.courts[0]
  const venue2Id = org2?.venue2?.id
  if (!org1Id || !org2Id || !venue1Id || !court1Id || !venue2Id)
    throw new Error('Revenue fixtures require the current organizations, venues and court.')

  test('9.6.1: Hai dòng tiền độc lập & Phân bổ doanh thu chuẩn xác (FR-PAY-14, FR-PAY-15, FR-PAY-16)', async () => {
    const reportScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Booking;
      use App\\Models\\Court;
      use App\\Models\\OwnerRefundObligation;
      use App\\Models\\PlatformPaymentAttempt;
      use App\\Models\\PosOrder;
      use App\\Models\\User;
      use App\\Models\\Venue;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Facades\\Crypt;

      $ownerUser = User::where('email', '${owner.email}')->firstOrFail();
      $adminUser = User::where('email', '${admin.email}')->firstOrFail();
      $venue = Venue::where('organization_id', ${org1Id})->findOrFail(${venue1Id});
      $court = Court::where('venue_id', $venue->id)->findOrFail(${court1Id});

      // 1. Confirmed V1 booking: 300,000 VND, with its slot, mapped to a reservation as the backfill does.
      // A dedicated court keeps the confirmed slot clear of the reservation_slots_no_overlap constraint.
      $canonCourt = Court::create([
        'venue_id' => $venue->id,
        'name' => 'Sân Canon ' . strtoupper(uniqid()),
        'court_number' => 'CANON-' . strtoupper(uniqid()),
        'surface_type' => 'vinyl',
        'is_active' => true,
      ]);
      $canonBooking = Booking::create([
        'code' => 'BK-CANON-' . strtoupper(uniqid()),
        'user_id' => $ownerUser->id,
        'court_id' => $canonCourt->id,
        'status' => 'confirmed',
        'total_amount' => 300000,
      ]);
      $canonBooking->slots()->create([
        'court_id' => $canonCourt->id,
        'starts_at' => Carbon::now()->addDays(3)->setTime(9, 0)->utc(),
        'ends_at' => Carbon::now()->addDays(3)->setTime(10, 0)->utc(),
        'unit_price' => 300000,
        'status' => 'confirmed',
      ]);
      app(\\App\\Services\\Reservation\\LegacyBookingBackfillService::class)->backfillBooking($canonBooking);

      // 2. POS order: 150,000 VND
      PosOrder::create([
        'organization_id' => ${org1Id},
        'venue_id' => $venue->id,
        'cashier_id' => $ownerUser->id,
        'customer_id' => $ownerUser->id,
        'status' => 'completed',
        'payment_method' => 'cash',
        'subtotal' => 150000,
        'total_amount' => 150000,
        'completed_at' => Carbon::now(),
      ]);

      // 3. Platform subscription attempt: 600,000 VND
      PlatformPaymentAttempt::create([
        'organization_id' => ${org1Id},
        'reference_code' => 'SUB-DUAL-' . strtoupper(uniqid()),
        'amount' => 600000,
        'currency' => 'VND',
        'provider' => 'vnpay',
        'status' => 'succeeded',
        'paid_at' => Carbon::now(),
      ]);

      // Execute report queries via controller / request simulation
      $from = Carbon::now()->startOfMonth()->toDateString();
      $to = Carbon::now()->endOfMonth()->toDateString();

      // Owner query
      $ownerReq = Illuminate\\Http\\Request::create('/api/v1/admin/reports', 'GET', [
        'venue_id' => $venue->id,
        'from' => $from,
        'to' => $to,
      ]);
      $ownerReq->setUserResolver(fn () => $ownerUser);

      $adminReq = Illuminate\\Http\\Request::create('/api/v1/admin/reports', 'GET', [
        'venue_id' => $venue->id,
        'from' => $from,
        'to' => $to,
      ]);
      $adminReq->setUserResolver(fn () => $adminUser);

      $controller = app(App\\Http\\Controllers\\Admin\\AdminController::class);
      $ownerReport = $controller->report($ownerReq);
      $adminReport = $controller->report($adminReq);

      echo json_encode([
        'owner_report_owner_revenue' => $ownerReport['owner_revenue'],
        'owner_report_platform_fees' => $ownerReport['platform_fees'],
        'owner_report_booking_revenue' => $ownerReport['booking_revenue'],
        'owner_report_pos_revenue' => $ownerReport['pos_revenue'],
        'admin_report_owner_revenue' => $adminReport['owner_revenue'],
        'admin_report_platform_fees' => $adminReport['platform_fees'],
      ]);
    `

    const reportResult = JSON.parse(
      execFileSync('php', ['-r', reportScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      owner_report_owner_revenue: number
      owner_report_platform_fees: number
      owner_report_booking_revenue: number
      owner_report_pos_revenue: number
      admin_report_owner_revenue: number
      admin_report_platform_fees: number
    }

    // Owner revenue combines booking (300k) and pos (150k)
    expect(reportResult.owner_report_booking_revenue).toBeGreaterThanOrEqual(300000)
    expect(reportResult.owner_report_pos_revenue).toBeGreaterThanOrEqual(150000)
    expect(reportResult.owner_report_owner_revenue).toBeGreaterThanOrEqual(450000)

    // CRITICAL: Platform subscription fees (600k) MUST NOT be included in owner report
    expect(reportResult.owner_report_platform_fees).toBe(0)

    // Platform admin sees platform fees separately from owner revenue
    expect(reportResult.admin_report_platform_fees).toBeGreaterThanOrEqual(600000)
    expect(reportResult.admin_report_owner_revenue).toBe(reportResult.owner_report_owner_revenue)
  })

  test('9.6.2: Phân quyền tổ chức và cơ sở: Từ chối báo cáo & xuất dữ liệu cơ sở khác (FR-PAY-19)', async () => {
    const scopingScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\User;
      use App\\Models\\Venue;

      $ownerUser = User::where('email', '${owner.email}')->firstOrFail();
      $venueBeta = Venue::where('organization_id', ${org2Id})->findOrFail(${venue2Id});

      // Owner of Org 1 attempts to query unauthorized venue Beta
      $req = Illuminate\\Http\\Request::create('/api/v1/admin/reports', 'GET', [
        'venue_id' => $venueBeta->id,
      ]);
      $req->setUserResolver(fn () => $ownerUser);

      $controller = app(App\\Http\\Controllers\\Admin\\AdminController::class);

      $reportDenied = false;
      try {
        $controller->report($req);
      } catch (Symfony\\Component\\HttpKernel\\Exception\\HttpException $e) {
        if ($e->getStatusCode() === 403) {
          $reportDenied = true;
        }
      }

      $csvDenied = false;
      try {
        $controller->reportCsv($req);
      } catch (Symfony\\Component\\HttpKernel\\Exception\\HttpException $e) {
        if ($e->getStatusCode() === 403) {
          $csvDenied = true;
        }
      }

      echo json_encode([
        'report_denied' => $reportDenied,
        'csv_denied' => $csvDenied,
      ]);
    `

    const scopingResult = JSON.parse(
      execFileSync('php', ['-r', scopingScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { report_denied: boolean; csv_denied: boolean }

    expect(scopingResult.report_denied).toBe(true)
    expect(scopingResult.csv_denied).toBe(true)
  })

  test('9.6.3: Xuất luồng CSV chuẩn & Ghi nhận đúng tài khoản nhận tiền (FR-PAY-20)', async () => {
    const csvScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\User;
      use App\\Models\\Venue;

      $adminUser = User::where('email', '${admin.email}')->firstOrFail();
      $venue = Venue::where('organization_id', ${org1Id})->findOrFail(${venue1Id});

      // Ensure test data exists independently for both owner and platform attribution
      $ownerAttempt = App\\Models\\OwnerPaymentAttempt::where('venue_id', $venue->id)->where('status', 'paid')->first();
      if (! $ownerAttempt) {
        $conn = App\\Models\\ReceivingConnection::where('venue_id', $venue->id)->first();
        App\\Models\\OwnerPaymentAttempt::create([
          'organization_id' => $venue->organization_id,
          'venue_id' => $venue->id,
          'user_id' => $adminUser->id,
          'receiving_connection_id' => $conn?->id,
          'payment_model' => 'direct_owner_v1',
          'provider' => 'sepay',
          'purpose' => 'booking',
          'purpose_id' => 'BK-CSV-' . uniqid(),
          'reference' => 'REF-' . uniqid(),
          'idempotency_key' => 'IDEM-' . uniqid(),
          'request_fingerprint' => hash('sha256', uniqid()),
          'amount' => 100000,
          'currency' => 'VND',
          'status' => 'paid',
          'credential_version' => 1,
          'receiver_snapshot_ciphertext' => Illuminate\\Support\\Facades\\Crypt::encryptString(json_encode(['name' => 'Owner'])),
          'purpose_snapshot_ciphertext' => Illuminate\\Support\\Facades\\Crypt::encryptString(json_encode(['purpose' => 'booking'])),
          'expires_at' => now()->addHour(),
          'paid_at' => now(),
        ]);
      }

      App\\Models\\PlatformPaymentAttempt::create([
        'organization_id' => $venue->organization_id,
        'reference_code' => 'SUB-CSV-' . uniqid(),
        'provider' => 'payos',
        'amount' => 100000,
        'currency' => 'VND',
        'status' => 'succeeded',
        'paid_at' => now(),
      ]);

      $req = Illuminate\\Http\\Request::create('/api/v1/admin/reports/csv', 'GET', [
        'venue_id' => $venue->id,
      ]);
      $req->setUserResolver(fn () => $adminUser);

      $controller = app(App\\Http\\Controllers\\Admin\\AdminController::class);
      $response = $controller->reportCsv($req);

      // Stream content buffer
      ob_start();
      $response->sendContent();
      $content = ob_get_clean();

      $lines = array_filter(explode("\n", trim($content)));
      $header = str_getcsv(array_shift($lines));

      $hasOwnerRows = false;
      $hasPlatformRows = false;

      foreach ($lines as $line) {
        $row = str_getcsv($line);
        // receiver_type is column index 4
        if (isset($row[4])) {
          if ($row[4] === 'owner') $hasOwnerRows = true;
          if ($row[4] === 'platform') $hasPlatformRows = true;
        }
      }

      echo json_encode([
        'header' => $header,
        'total_rows' => count($lines),
        'has_owner_rows' => $hasOwnerRows,
        'has_platform_rows' => $hasPlatformRows,
      ]);
    `

    const csvResult = JSON.parse(
      execFileSync('php', ['-r', csvScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      header: string[]
      total_rows: number
      has_owner_rows: boolean
      has_platform_rows: boolean
    }

    // Expected canonical CSV headers:
    // ['code', 'source_type', 'customer', 'venue_or_court', 'receiver_type', 'status', 'gross_amount', 'refund_amount', 'net_amount', 'created_at']
    expect(csvResult.header).toContain('code')
    expect(csvResult.header).toContain('source_type')
    expect(csvResult.header).toContain('receiver_type')
    expect(csvResult.header).toContain('gross_amount')
    expect(csvResult.header).toContain('net_amount')

    expect(csvResult.total_rows).toBeGreaterThan(0)
    expect(csvResult.has_owner_rows).toBe(true)
    expect(csvResult.has_platform_rows).toBe(true)
  })

  test('9.6.4: Phát hiện sai lệch đối soát & Bắt buộc đính kèm bằng chứng D47 (FR-PAY-17, FR-PAY-18)', async () => {
    const reconScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Domain\\Finance\\Actions\\AutoResolveReconciliation;
      use App\\Domain\\Finance\\DTOs\\AutoResolveReconciliationData;
      use App\\Models\\FinanceException;
      use App\\Models\\Organization;
      use App\\Models\\OwnerPaymentAllocation;
      use App\\Models\\OwnerPaymentAttempt;
      use App\\Models\\OwnerPaymentReceipt;
      use App\\Models\\ReceivingConnection;
      use App\\Models\\User;
      use App\\Models\\Venue;
      use App\\Services\\Finance\\ReconciliationService;
      use Illuminate\\Support\\Facades\\Crypt;
      use Illuminate\\Support\\Str;
      use Illuminate\\Validation\\ValidationException;

      $org = Organization::create([
        'name' => 'Recon Org ' . uniqid(),
        'slug' => 'recon-' . uniqid(),
        'status' => 'active',
        'partner_status' => 'approved',
      ]);
      $ownerUser = User::where('email', '${owner.email}')->firstOrFail();
      $venue = Venue::create([
        'organization_id' => $org->id,
        'name' => 'Recon Venue',
        'slug' => 'recon-venue-' . uniqid(),
        'status' => 'published',
        'is_active' => true,
      ]);

      $connection = ReceivingConnection::create([
        'organization_id' => $org->id,
        'venue_id' => $venue->id,
        'owner_user_id' => $ownerUser->id,
        'provider' => 'sepay',
        'routing_id' => (string) Str::uuid(),
        'bank_code' => 'MBBank',
        'account_name_ciphertext' => Crypt::encryptString('Test Owner'),
        'account_number_ciphertext' => Crypt::encryptString('0123456789'),
        'account_last_four' => '6789',
        'status' => ReceivingConnection::STATUS_VERIFIED,
        'credential_version' => 1,
        'verified_at' => now(),
      ]);

      $attempt = OwnerPaymentAttempt::create([
        'organization_id' => $org->id,
        'venue_id' => $venue->id,
        'user_id' => $ownerUser->id,
        'receiving_connection_id' => $connection->id,
        'payment_model' => 'direct_owner_v1',
        'provider' => 'sepay',
        'purpose' => 'booking',
        'purpose_id' => 'BK-REC-' . uniqid(),
        'reference' => 'REF-' . uniqid(),
        'idempotency_key' => 'IDEM-' . uniqid(),
        'request_fingerprint' => hash('sha256', uniqid()),
        'amount' => 100000,
        'currency' => 'VND',
        'status' => 'paid',
        'credential_version' => 1,
        'receiver_snapshot_ciphertext' => Crypt::encryptString(json_encode(['name' => 'Owner'])),
        'purpose_snapshot_ciphertext' => Crypt::encryptString(json_encode(['purpose' => 'booking'])),
        'expires_at' => now()->addHour(),
        'paid_at' => now(),
      ]);

      $evidence = App\\Models\\ReceivingConnectionEvidence::firstOrCreate(
        ['receiving_connection_id' => $connection->id],
        [
          'actor_id' => $ownerUser->id,
          'event_type' => 'webhook_verification',
          'credential_version' => 1,
          'event_fingerprint' => hash('sha256', 'setup-evidence-' . uniqid()),
          'evidence_ciphertext' => Crypt::encryptString('verified'),
        ]
      );

      // Pending receipt creates a mismatch
      $receipt = OwnerPaymentReceipt::create([
        'organization_id' => $org->id,
        'venue_id' => $venue->id,
        'receiving_connection_id' => $connection->id,
        'receiving_connection_evidence_id' => $evidence->id,
        'payment_attempt_id' => $attempt->id,
        'provider' => 'sepay',
        'source' => 'webhook',
        'transfer_type' => 'in',
        'provider_event_id' => 'pe-' . uniqid(),
        'event_fingerprint' => hash('sha256', 'pe-' . uniqid()),
        'reference' => $attempt->reference,
        'amount' => 100000,
        'currency' => 'VND',
        'status' => OwnerPaymentReceipt::STATUS_PENDING,
        'receiver_snapshot_ciphertext' => Crypt::encryptString(json_encode(['name' => 'Owner'])),
        'received_at' => now(),
        'credential_version' => 1,
      ]);

      OwnerPaymentAllocation::create([
        'organization_id' => $org->id,
        'venue_id' => $venue->id,
        'receiving_connection_id' => $connection->id,
        'payment_attempt_id' => $attempt->id,
        'payment_receipt_id' => $receipt->id,
        'purpose' => $attempt->purpose,
        'purpose_id' => $attempt->purpose_id,
        'amount' => 100000,
        'currency' => 'VND',
        'credential_version' => 1,
        'receiver_snapshot_ciphertext' => $attempt->receiver_snapshot_ciphertext,
        'purpose_snapshot_ciphertext' => $attempt->purpose_snapshot_ciphertext,
        'allocated_at' => now(),
      ]);

      $reconciliationService = app(ReconciliationService::class);
      $run = $reconciliationService->run($org);

      $autoResolveAction = app(AutoResolveReconciliation::class);

      // 1. Note-only attempt: MUST throw ValidationException
      $noteOnlyBlocked = false;
      $noteOnlyData = new AutoResolveReconciliationData(
        runId: $run->id,
        scope: 'all',
        notes: 'Ghi chú giải trình kiểm toán không kèm chứng từ',
        evidenceReference: null
      );

      try {
        $autoResolveAction->execute($org, $noteOnlyData, $ownerUser);
      } catch (ValidationException $e) {
        $noteOnlyBlocked = true;
      }

      // 2. Clear underlying receipt status
      $receipt->update(['status' => OwnerPaymentReceipt::STATUS_ALLOCATED]);

      // 3. Resolve with verified evidence
      $validData = new AutoResolveReconciliationData(
        runId: $run->id,
        scope: 'all',
        notes: 'Đã xác minh sao kê ngân hàng',
        evidenceReference: 'EVID-STMT-' . uniqid(),
        correctionType: 'bank_statement_verification',
        correctionDetails: ['verified_amount' => 100000]
      );

      $resolveResult = $autoResolveAction->execute($org, $validData, $ownerUser);

      echo json_encode([
        'initial_run_status' => $run->status,
        'note_only_blocked' => $noteOnlyBlocked,
        'resolved_success' => $resolveResult['success'],
        'final_run_status' => $run->fresh()->status,
        'final_mismatch_amount' => $run->fresh()->mismatch_amount,
      ]);
    `

    const reconResult = JSON.parse(
      execFileSync('php', ['-r', reconScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      initial_run_status: string
      note_only_blocked: boolean
      resolved_success: boolean
      final_run_status: string
      final_mismatch_amount: number
    }

    expect(reconResult.initial_run_status).toBe('mismatch')
    // D47: Note-only resolution without verified evidence MUST be blocked
    expect(reconResult.note_only_blocked).toBe(true)

    // With verified evidence and cleared data, resolution succeeds
    expect(reconResult.resolved_success).toBe(true)
    expect(reconResult.final_run_status).toBe('matched')
    expect(reconResult.final_mismatch_amount).toBe(0)
  })

  test('9.6.5: Giao diện đối soát tài chính admin & Duy trì sau F5 (/admin/reconciliation)', async ({
    page,
  }) => {
    // 1. Admin logs in
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.fill('input[type="email"], input[name="email"]', admin.email)
    await page.fill('input[type="password"], input[name="password"]', admin.password)
    await page.click('button[type="submit"]')
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Navigate to /admin/reconciliation
    await page.goto('/admin/reconciliation', { waitUntil: 'domcontentloaded' })

    // 3. Verify presence of reconciliation view elements
    const pageTitle = page.locator('h1.page-title')
    await expect(pageTitle).toBeVisible({ timeout: 15000 })
    await expect(page.locator('.financial-reconciliation-view')).toBeVisible({ timeout: 10000 })

    // 4. Reload persistence
    await page.reload({ waitUntil: 'domcontentloaded' })
    await expect(page.locator('.financial-reconciliation-view')).toBeVisible({ timeout: 10000 })
    await expect(pageTitle).toBeVisible({ timeout: 10000 })
  })
})
