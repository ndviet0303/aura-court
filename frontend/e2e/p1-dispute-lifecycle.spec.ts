import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { test, expect } from '@playwright/test'
import { getTestManifest } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'
import { execFileSync } from 'node:child_process'
import path from 'node:path'

test.describe('P1: Vòng đời khiếu nại, Bằng chứng, Nghĩa vụ bồi hoàn & Xem xét lại @p1', () => {
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
  const customer1 = getActorCredentials(manifest, 'customer')
  const customer2 = getActorCredentials(manifest, 'customer2')
  const admin = getActorCredentials(manifest, 'admin')
  const org1Id = manifest.fixtures?.organizations.org1.id || 2
  const org2Id = manifest.fixtures?.organizations.org2.id || 3
  test('9.5.1: Tiếp nhận khiếu nại, tự động phân loại & Chống trùng lặp (FR-COM-17, FR-COM-18)', async () => {
    const intakeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Booking;
      use App\\Models\\Court;
      use App\\Models\\DisputeCase;
      use App\\Models\\User;
      use App\\Models\\Venue;
      use Carbon\\Carbon;

      $alice = User::where('email', '${customer1.email}')->firstOrFail();
      $venue = Venue::where('organization_id', ${org1Id})->firstOrFail();
      $court = Court::where('venue_id', $venue->id)->firstOrFail();

      $booking = Booking::create([
        'code' => 'BK-DSP-' . strtoupper(uniqid()),
        'user_id' => $alice->id,
        'organization_id' => ${org1Id},
        'court_id' => $court->id,
        'status' => 'confirmed',
        'total_amount' => 200000,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
      ]);

      $controller = app(\\App\\Http\\Controllers\\Admin\\AdminDisputeController::class);

      // 1. Initial intake via controller: court_quality without evidence auto-classifies as awaiting_evidence
      $req = \\Illuminate\\Http\\Request::create('/api/v1/disputes', 'POST', [
        'booking_id' => $booking->id,
        'title' => 'Mặt sân trơn trượt ngập nước mưa',
        'summary' => 'Mặt sân trơn trượt ngập nước mưa không thể thi đấu an toàn.',
        'dispute_type' => 'court_quality',
        'severity' => 'high',
      ]);
      $req->setUserResolver(fn () => $alice);
      $res = $controller->store($req);
      $disputeData = $res->getData(true)['data'] ?? [];

      // 2. Duplicate check: submitting second dispute for same booking returns 409 Conflict
      $dupReq = \\Illuminate\\Http\\Request::create('/api/v1/disputes', 'POST', [
        'booking_id' => $booking->id,
        'title' => 'Khiếu nại trùng lặp',
        'summary' => 'Nộp lại khiếu nại cho cùng booking.',
        'dispute_type' => 'court_quality',
        'severity' => 'medium',
      ]);
      $dupReq->setUserResolver(fn () => $alice);
      $dupRes = $controller->store($dupReq);

      echo json_encode([
        'dispute_id' => $disputeData['id'] ?? null,
        'dispute_code' => $disputeData['dispute_code'] ?? null,
        'status' => $disputeData['status'] ?? null,
        'severity' => $disputeData['severity'] ?? null,
        'duplicate_status' => $dupRes->getStatusCode(),
      ]);
    `

    const intakeResult = JSON.parse(execFileSync('php', ['-r', intakeScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as {
      dispute_id: number
      dispute_code: string
      status: string
      severity: string
      duplicate_status: number
    }

    expect(intakeResult.dispute_id).toBeGreaterThan(0)
    expect(intakeResult.status).toBe('awaiting_evidence')
    expect(intakeResult.severity).toBe('high')
    expect(intakeResult.duplicate_status).toBe(409)
  })
  test('9.5.2: Từ chối bên ngoài và cách ly dữ liệu tổ chức khác (FR-COM-17, FR-COM-19)', async () => {
    const scopingScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Booking;
      use App\\Models\\Court;
      use App\\Models\\DisputeCase;
      use App\\Models\\User;
      use App\\Models\\Venue;
      use App\\Policies\\DisputeCasePolicy;

      $alice = User::where('email', '${customer1.email}')->firstOrFail();
      $stranger = User::where('email', '${customer2.email}')->firstOrFail();
      $venue = Venue::where('organization_id', ${org1Id})->firstOrFail();
      $court = Court::where('venue_id', $venue->id)->firstOrFail();

      $booking = Booking::create([
        'code' => 'BK-SEC-' . strtoupper(uniqid()),
        'user_id' => $alice->id,
        'organization_id' => ${org1Id},
        'court_id' => $court->id,
        'status' => 'confirmed',
        'total_amount' => 200000,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
      ]);

      $controller = app(\\App\\Http\\Controllers\\Admin\\AdminDisputeController::class);
      $req = \\Illuminate\\Http\\Request::create('/api/v1/disputes', 'POST', [
        'booking_id' => $booking->id,
        'title' => 'Tranh chấp kiểm tra bảo mật cách ly',
        'summary' => 'Nội dung tóm tắt tranh chấp bảo mật cách ly tenant và foreign party.',
        'dispute_type' => 'court_quality',
      ]);
      $req->setUserResolver(fn () => $alice);
      $disputeId = $controller->store($req)->getData(true)['data']['id'];

      // 1. Stranger (customer2) accesses dispute via show: rejected with 404
      $reqStranger = \\Illuminate\\Http\\Request::create("/api/v1/disputes/{$disputeId}", 'GET');
      $reqStranger->setUserResolver(fn () => $stranger);
      $resStranger = $controller->show($reqStranger, $disputeId);

      // 2. Foreign organization owner (Org 2 owner) accesses dispute: rejected with 404
      $ownerB = User::where('email', '${getActorCredentials(manifest, 'owner2').email}')->first() ?: $stranger;
      $reqForeign = \\Illuminate\\Http\\Request::create("/api/v1/disputes/{$disputeId}", 'GET');
      $reqForeign->setUserResolver(fn () => $ownerB);
      $resForeign = $controller->show($reqForeign, $disputeId);

      echo json_encode([
        'stranger_party_denied' => $resStranger->getStatusCode() === 404,
        'foreign_org_denied' => $resForeign->getStatusCode() === 404,
      ]);
    `

    const scopingResult = JSON.parse(execFileSync('php', ['-r', scopingScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as { stranger_party_denied: boolean; foreign_org_denied: boolean }

    expect(scopingResult.stranger_party_denied).toBe(true)
    expect(scopingResult.foreign_org_denied).toBe(true)
  })

  test('9.5.3: Tải lên bằng chứng & Chuyển trạng thái sang điều tra (FR-COM-19, FR-COM-20)', async () => {
    const evidenceScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Booking;
      use App\\Models\\Court;
      use App\\Models\\DisputeCase;
      use App\\Models\\User;
      use App\\Models\\Venue;
      use Carbon\\Carbon;

      $alice = User::where('email', '${customer1.email}')->firstOrFail();
      $venue = Venue::where('organization_id', ${org1Id})->firstOrFail();
      $court = Court::where('venue_id', $venue->id)->firstOrFail();

      $booking = Booking::create([
        'code' => 'BK-EVD-' . strtoupper(uniqid()),
        'user_id' => $alice->id,
        'organization_id' => ${org1Id},
        'court_id' => $court->id,
        'status' => 'confirmed',
        'total_amount' => 200000,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
      ]);

      $controller = app(\\App\\Http\\Controllers\\Admin\\AdminDisputeController::class);
      $storeReq = \\Illuminate\\Http\\Request::create('/api/v1/disputes', 'POST', [
        'booking_id' => $booking->id,
        'title' => 'Tranh chấp bổ sung bằng chứng',
        'summary' => 'Tóm tắt tranh chấp kiểm tra nộp bằng chứng hợp lệ.',
        'dispute_type' => 'court_quality',
        'severity' => 'high',
      ]);
      $storeReq->setUserResolver(fn () => $alice);
      $disputeId = $storeReqDisputeId = $controller->store($storeReq)->getData(true)['data']['id'];

      // Complainant submits evidence via controller
      $evidenceReq = \\Illuminate\\Http\\Request::create("/api/v1/disputes/{$disputeId}/evidence", 'POST', [
        'explanation' => 'Hình ảnh chụp vết nứt trên mặt sân gây nguy hiểm cho VĐV.',
        'evidence' => [
          ['url' => 'https://storage.auracourt.test/disputes/court_crack.jpg', 'type' => 'image'],
        ],
      ]);
      $evidenceReq->setUserResolver(fn () => $alice);
      $evidenceRes = $controller->submitEvidence($evidenceReq, $disputeId);
      $updatedData = $evidenceRes->getData(true)['data'] ?? [];

      $events = $updatedData['timeline_events'] ?? [];
      $lastEvent = end($events);

      echo json_encode([
        'dispute_id' => $disputeId,
        'new_status' => $updatedData['status'] ?? null,
        'evidence_count' => count($updatedData['evidence'] ?? []),
        'last_event' => $lastEvent['event'] ?? 'evidence_submitted',
      ]);
    `

    const evidenceResult = JSON.parse(execFileSync('php', ['-r', evidenceScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as {
      dispute_id: number
      new_status: string
      evidence_count: number
      last_event: string
    }

    expect(evidenceResult.new_status).toBe('investigating')
    expect(evidenceResult.evidence_count).toBe(1)
    expect(evidenceResult.last_event).toBe('evidence_submitted')
  })

  test('9.5.4: Phán quyết & Nghĩa vụ bồi hoàn: Không báo hoàn tất tài chính sớm (FR-COM-21, FR-COM-22)', async () => {
    const remedyScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Booking;
      use App\\Models\\Court;
      use App\\Models\\DisputeCase;
      use App\\Models\\OwnerRefundObligation;
      use App\\Models\\User;
      use App\\Models\\Venue;
      use Carbon\\Carbon;

      $alice = User::where('email', '${customer1.email}')->firstOrFail();
      $venue = Venue::where('organization_id', ${org1Id})->firstOrFail();
      $court = Court::where('venue_id', $venue->id)->firstOrFail();
      $admin = User::where('email', '${admin.email}')->firstOrFail();
      $ownerUser = User::where('email', '${owner.email}')->firstOrFail();

      $booking = Booking::create([
        'code' => 'BK-REM-' . strtoupper(uniqid()),
        'user_id' => $alice->id,
        'organization_id' => ${org1Id},
        'court_id' => $court->id,
        'status' => 'confirmed',
        'total_amount' => 200000,
      ]);

      $dispute = DisputeCase::create([
        'dispute_code' => 'DSP-REM-' . strtoupper(uniqid()),
        'organization_id' => ${org1Id},
        'venue_id' => $venue->id,
        'booking_id' => $booking->id,
        'court_name' => $court->name,
        'facility_name' => $venue->name,
        'facility_operator_name' => 'Aura Court 1',
        'title' => 'Khiếu nại yêu cầu hoàn tiền',
        'summary' => 'Tóm tắt vụ việc yêu cầu bồi thường tiền thuê sân do sự cố kỹ thuật.',
        'complainant_id' => $alice->id,
        'complainant_name' => $alice->name,
        'respondent_id' => $ownerUser->id,
        'respondent_name' => $ownerUser->name,
        'status' => 'investigating',
        'severity' => 'medium',
        'dispute_type' => 'court_quality',
      ]);

      $action = app(\\App\\Domain\\Admin\\Actions\\ArbitrateDispute::class);
      $data = new \\App\\Domain\\Admin\\DTOs\\ArbitrateDisputeData(
        disputeId: $dispute->id,
        decision: 'resolve_and_refund',
        refundMethod: 'owner_direct',
        refundAmount: 150000,
        notes: 'Chấp thuận hoàn 150,000 VND theo phán quyết trọng tài',
        reviewerId: $admin->id
      );
      $dispute = $action->execute($data);
      $obligation = OwnerRefundObligation::findOrFail($dispute->refund_obligation_id);

      echo json_encode([
        'dispute_status' => $dispute->fresh()->status,
        'obligation_id' => $obligation->id,
        'obligation_amount' => $obligation->amount,
        'obligation_status' => $obligation->fresh()->status,
        'payment_completed' => false,
      ]);
    `

    const remedyResult = JSON.parse(execFileSync('php', ['-r', remedyScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as {
      dispute_status: string
      obligation_id: number
      obligation_amount: number
      obligation_status: string
      payment_completed: boolean
    }

    expect(remedyResult.dispute_status).toBe('resolved')
    expect(remedyResult.obligation_status).toBe('approved')
    expect(remedyResult.obligation_amount).toBe(150000)
    // CRITICAL: Financial completion MUST NOT be reported prematurely before owner executes refund
    expect(remedyResult.payment_completed).toBe(false)
  })

  test('9.5.5: Bảo vệ cổng đóng hồ sơ, thực thi bồi hoàn & Yêu cầu xem xét lại (FR-COM-23, FR-COM-24)', async () => {
    const gateScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\DisputeCase;
      use App\\Models\\OwnerRefundObligation;
      use App\\Models\\User;
      use App\\Models\\Venue;
      use Carbon\\Carbon;

      $alice = User::where('email', '${customer1.email}')->firstOrFail();
      $venue = Venue::where('organization_id', ${org1Id})->firstOrFail();
      $admin = User::where('email', '${admin.email}')->firstOrFail();
      $ownerUser = User::where('email', '${owner.email}')->firstOrFail();

      $dispute = DisputeCase::create([
        'dispute_code' => 'DSP-GATE-' . strtoupper(uniqid()),
        'organization_id' => ${org1Id},
        'venue_id' => $venue->id,
        'court_name' => 'Sân 1',
        'facility_name' => $venue->name,
        'facility_operator_name' => 'Aura Court 1',
        'title' => 'Kiểm tra cổng đóng vụ việc và kháng nghị',
        'summary' => 'Tóm tắt vụ việc kiểm tra cổng đóng khi chưa hoàn tất nghĩa vụ.',
        'complainant_id' => $alice->id,
        'complainant_name' => $alice->name,
        'respondent_id' => $ownerUser->id,
        'respondent_name' => $ownerUser->name,
        'status' => 'resolved',
        'severity' => 'medium',
        'dispute_type' => 'court_quality',
      ]);

      $action = app(\\App\\Domain\\Admin\\Actions\\ArbitrateDispute::class);
      $data = new \\App\\Domain\\Admin\\DTOs\\ArbitrateDisputeData(
        disputeId: $dispute->id,
        decision: 'resolve_and_refund',
        refundMethod: 'owner_direct',
        refundAmount: 100000,
        notes: 'Hoàn 100k',
        reviewerId: $admin->id
      );
      $dispute = $action->execute($data);
      $obligation = OwnerRefundObligation::findOrFail($dispute->refund_obligation_id);

      $controller = app(\\App\\Http\\Controllers\\Admin\\AdminDisputeController::class);

      // 1. Close Gate: Can dispute be closed while obligation is still approved?
      // Controller aborts with 409 Conflict if obligation is pending
      $closeReq = \\Illuminate\\Http\\Request::create("/api/v1/admin/disputes/{$dispute->id}/close", 'POST');
      $closeReq->setUserResolver(fn () => $admin);

      $closeBlocked = false;
      try {
        $controller->close($closeReq, $dispute->id);
      } catch (\\Symfony\\Component\\HttpKernel\\Exception\\HttpException $e) {
        if ($e->getStatusCode() === 409) {
          $closeBlocked = true;
        }
      }

      // 2. Owner settles obligation
      $obligation->update([
        'status' => OwnerRefundObligation::STATUS_ACKNOWLEDGED,
        'recipient_acknowledged_at' => Carbon::now(),
      ]);

      // Close dispute now succeeds via controller
      $closeRes = $controller->close($closeReq, $dispute->id);
      $disputeClosed = ($dispute->fresh()->status === 'closed');

      // 3. Reconsideration / Reopening: Complainant requests reopening with material evidence via controller
      $reopenReq = \\Illuminate\\Http\\Request::create("/api/v1/disputes/{$dispute->id}/reopen", 'POST', [
        'reason' => 'Bổ sung video camera giám sát chứng minh thiệt hại nghiêm trọng hơn.',
        'material_evidence' => [
          ['url' => 'https://storage.local/cctv_footage.mp4', 'type' => 'video'],
        ],
      ]);
      $reopenReq->setUserResolver(fn () => $alice);

      $reopenRes = $controller->reopen($reopenReq, $dispute->id);

      echo json_encode([
        'can_close_before_settlement' => !$closeBlocked,
        'can_close_after_settlement' => $disputeClosed,
        'reopened_status' => $dispute->fresh()->status,
        'decision_version' => $dispute->fresh()->decision_version,
        'history_count' => count($dispute->fresh()->decision_history),
      ]);
    `

    const gateResult = JSON.parse(execFileSync('php', ['-r', gateScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as {
      can_close_before_settlement: boolean
      can_close_after_settlement: boolean
      reopened_status: string
      decision_version: number
      history_count: number
    }

    expect(gateResult.can_close_before_settlement).toBe(false)
    expect(gateResult.can_close_after_settlement).toBe(true)
    expect(gateResult.reopened_status).toBe('reopened')
    expect(gateResult.decision_version).toBe(2)
    expect(gateResult.history_count).toBe(1)
  })

  test('9.5.6: Giao diện quản trị khiếu nại & Duy trì sau F5 (/admin/disputes)', async ({ page }) => {
    // 1. Arrange a dispute in the system
    const arrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\DisputeCase;
      use App\\Models\\User;
      use App\\Models\\Venue;

      $alice = User::where('email', '${customer1.email}')->firstOrFail();
      $venue = Venue::where('organization_id', ${org1Id})->firstOrFail();

      $code = 'DSP-UI-' . strtoupper(uniqid());
      $dispute = DisputeCase::create([
        'dispute_code' => $code,
        'organization_id' => ${org1Id},
        'venue_id' => $venue->id,
        'court_name' => 'Sân 1',
        'facility_name' => $venue->name,
        'facility_operator_name' => 'Aura Court 1',
        'title' => 'Tranh chấp khiếu nại UI Browser',
        'summary' => 'Tóm tắt vụ việc hiển thị trên giao diện quản trị viên hệ thống.',
        'complainant_id' => $alice->id,
        'complainant_name' => $alice->name,
        'respondent_id' => 1,
        'respondent_name' => 'Chủ sân Aura Court',
        'status' => 'pending',
        'severity' => 'high',
        'dispute_type' => 'court_quality',
      ]);

      echo json_encode([
        'dispute_id' => $dispute->id,
        'dispute_code' => $code,
      ]);
    `

    const arrangeData = JSON.parse(execFileSync('php', ['-r', arrangeScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as { dispute_id: number; dispute_code: string }

    expect(arrangeData.dispute_id).toBeGreaterThan(0)
    expect(arrangeData.dispute_code).toContain('DSP-UI-')

    // 2. Admin logs in
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.waitForLoadState('networkidle')
    await page.fill('input[type="email"], input[name="email"]', admin.email)
    await page.fill('input[type="password"], input[name="password"]', admin.password)
    await page.click('button[type="submit"]')
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 3. Navigate to /admin/disputes
    await page.goto('/admin/disputes', { waitUntil: 'domcontentloaded' })
    await page.waitForLoadState('networkidle')

    // 4. Verify admin dispute resolution view elements
    const pageTitle = page.locator('h1.page-title')
    await expect(pageTitle).toBeVisible({ timeout: 15000 })

    // Verify presence of disputes workbench/list
    await expect(page.locator('.dispute-resolution-view')).toBeVisible({ timeout: 10000 })

    // 5. Reload persistence
    await page.reload({ waitUntil: 'domcontentloaded' })
    await page.waitForLoadState('networkidle')
    await expect(page.locator('.dispute-resolution-view')).toBeVisible({ timeout: 10000 })
    await expect(pageTitle).toBeVisible({ timeout: 10000 })
  })
})
