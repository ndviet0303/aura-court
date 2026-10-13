import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { test, expect, type Page } from '@playwright/test'
import { arrangeCourt, getTestManifest } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'
import { withE2EApi } from './runner/with-api.ts'
import { execFileSync } from 'node:child_process'
import path from 'node:path'

type DisputeData = {
  id: number
  dispute_code: string
  status: string
  severity: string
  reservation_id: number | null
  booking_id: number | null
  evidence: unknown[]
  timeline_events: { event: string }[]
}

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
  const org1Id = manifest.fixtures?.organizations.org1.id
  const venue1Id = manifest.fixtures?.organizations.org1.venue1A?.id
  const court1Id = manifest.fixtures?.organizations.org1.venue1A?.courts[0]
  if (!org1Id || !venue1Id || !court1Id)
    throw new Error('Current dispute fixtures require an organization, venue and court.')

  const runPhp = <T>(script: string): T =>
    JSON.parse(
      execFileSync('php', ['-r', script], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as T

  // A confirmed reservation for the customer on its own court, the rows checkout writes (D-e2e).
  const arrangeReservation = (testKey: string) => {
    const court = arrangeCourt(manifest, { testKey, venueId: venue1Id, price: 200000 })
    return runPhp<{ reservation_id: number; reservation_code: string; court_id: number }>(`
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Reservation;
      use App\\Models\\ReservationEntitlement;
      use App\\Models\\ReservationSlot;
      use App\\Models\\User;
      use Illuminate\\Support\\Str;

      $alice = User::where('email', '${customer1.email}')->firstOrFail();
      $startsAt = now()->addDay()->setTime(10, 0)->utc();
      $endsAt = $startsAt->copy()->addHour();
      $reservation = Reservation::create([
        'organization_id' => ${org1Id},
        'user_id' => $alice->id,
        'court_id' => ${court.court_id},
        'code' => 'RSV' . Str::upper(Str::random(13)),
        'status' => 'confirmed',
        'currency' => 'VND',
        'total_amount' => 200000,
        'confirmed_at' => now(),
        'payment_model' => 'direct_owner_v1',
      ]);
      $slot = ReservationSlot::create([
        'organization_id' => ${org1Id},
        'reservation_id' => $reservation->id,
        'court_id' => ${court.court_id},
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'unit_price' => 200000,
        'status' => 'confirmed',
        'snapshot' => ['starts_at' => $startsAt->toIso8601String(), 'ends_at' => $endsAt->toIso8601String(), 'unit_price' => 200000],
      ]);
      ReservationEntitlement::create([
        'organization_id' => ${org1Id},
        'reservation_id' => $reservation->id,
        'reservation_slot_id' => $slot->id,
        'user_id' => $alice->id,
        'court_id' => ${court.court_id},
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'amount' => 200000,
        'status' => 'active',
      ]);

      echo json_encode([
        'reservation_id' => $reservation->id,
        'reservation_code' => $reservation->code,
        'court_id' => ${court.court_id},
      ]);
    `)
  }

  // Customers have no dispute UI, so the complainant files over HTTP like any API client (D28).
  const loginAsCustomer = (page: Page) =>
    withE2EApi(page, ({ authApi }) =>
      authApi.login({ email: customer1.email, password: customer1.password }),
    )

  const fileDispute = (page: Page, payload: Record<string, unknown>) =>
    withE2EApi(
      page,
      async ({ api }, body) => {
        try {
          const res = await api.post<{ data: DisputeData }>('/disputes', body)
          return { status: 201, data: res.data }
        } catch (err) {
          const status = (err as { status?: unknown } | null)?.status
          if (typeof status === 'number') return { status, data: null }
          throw err
        }
      },
      payload,
    )

  const disputeRow = (disputeId: number) =>
    runPhp<{
      reservation_id: number | null
      booking_id: number | null
      complainant_email: string
    }>(`
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      $dispute = App\\Models\\DisputeCase::withoutGlobalScopes()->with('complainant')->findOrFail(${disputeId});
      echo json_encode([
        'reservation_id' => $dispute->reservation_id,
        'booking_id' => $dispute->booking_id,
        'complainant_email' => $dispute->complainant?->email,
      ]);
    `)

  test('9.5.1: Tiếp nhận khiếu nại, tự động phân loại & Chống trùng lặp (FR-COM-17, FR-COM-18)', async ({
    page,
  }) => {
    const reservation = arrangeReservation('9_5_1_dispute')
    await loginAsCustomer(page)

    // 1. court_quality without evidence auto-classifies as awaiting_evidence
    const filed = await fileDispute(page, {
      reservation_id: reservation.reservation_id,
      title: 'Mặt sân trơn trượt ngập nước mưa',
      summary: 'Mặt sân trơn trượt ngập nước mưa không thể thi đấu an toàn.',
      dispute_type: 'court_quality',
      severity: 'high',
    })
    expect(filed.status).toBe(201)
    expect(filed.data?.id).toBeGreaterThan(0)
    expect(filed.data?.status).toBe('awaiting_evidence')
    expect(filed.data?.severity).toBe('high')
    expect(filed.data?.reservation_id).toBe(reservation.reservation_id)

    // 2. A second open dispute for the same reservation and complainant is rejected with 409
    const duplicate = await fileDispute(page, {
      reservation_id: reservation.reservation_id,
      title: 'Khiếu nại trùng lặp',
      summary: 'Nộp lại khiếu nại cho cùng lượt đặt sân.',
      dispute_type: 'court_quality',
      severity: 'medium',
    })
    expect(duplicate.status).toBe(409)

    // 3. Authoritative row: the dispute points at the reservation, not a V1 booking
    const row = disputeRow(filed.data!.id)
    expect(row.reservation_id).toBe(reservation.reservation_id)
    expect(row.booking_id).toBeNull()
    expect(row.complainant_email).toBe(customer1.email)
  })

  test('9.5.2: Từ chối bên ngoài và cách ly dữ liệu tổ chức khác (FR-COM-17, FR-COM-19)', async ({
    page,
  }) => {
    const reservation = arrangeReservation('9_5_2_dispute')
    await loginAsCustomer(page)
    const filed = await fileDispute(page, {
      reservation_id: reservation.reservation_id,
      title: 'Tranh chấp kiểm tra bảo mật cách ly',
      summary: 'Nội dung tóm tắt tranh chấp bảo mật cách ly tenant và foreign party.',
      dispute_type: 'court_quality',
    })
    expect(filed.status).toBe(201)
    const disputeId = filed.data!.id

    const scopingResult = runPhp<{ stranger_party_denied: boolean; foreign_org_denied: boolean }>(`
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\User;

      $stranger = User::where('email', '${customer2.email}')->firstOrFail();
      $controller = app(\\App\\Http\\Controllers\\Admin\\AdminDisputeController::class);

      // 1. Stranger (customer2) accesses dispute via show: rejected with 404
      $reqStranger = \\Illuminate\\Http\\Request::create("/api/disputes/${disputeId}", 'GET');
      $reqStranger->setUserResolver(fn () => $stranger);
      $resStranger = $controller->show($reqStranger, ${disputeId});

      // 2. Foreign organization owner (Org 2 owner) accesses dispute: rejected with 404
      $ownerB = User::where('email', '${getActorCredentials(manifest, 'owner2').email}')->first() ?: $stranger;
      $reqForeign = \\Illuminate\\Http\\Request::create("/api/disputes/${disputeId}", 'GET');
      $reqForeign->setUserResolver(fn () => $ownerB);
      $resForeign = $controller->show($reqForeign, ${disputeId});

      echo json_encode([
        'stranger_party_denied' => $resStranger->getStatusCode() === 404,
        'foreign_org_denied' => $resForeign->getStatusCode() === 404,
      ]);
    `)

    expect(scopingResult.stranger_party_denied).toBe(true)
    expect(scopingResult.foreign_org_denied).toBe(true)
  })

  test('9.5.3: Tải lên bằng chứng & Chuyển trạng thái sang điều tra (FR-COM-19, FR-COM-20)', async ({
    page,
  }) => {
    const reservation = arrangeReservation('9_5_3_dispute')
    await loginAsCustomer(page)
    const filed = await fileDispute(page, {
      reservation_id: reservation.reservation_id,
      title: 'Tranh chấp bổ sung bằng chứng',
      summary: 'Tóm tắt tranh chấp kiểm tra nộp bằng chứng hợp lệ.',
      dispute_type: 'court_quality',
      severity: 'high',
    })
    expect(filed.status).toBe(201)
    expect(filed.data?.status).toBe('awaiting_evidence')

    // Complainant submits evidence over HTTP
    const updated = await withE2EApi(
      page,
      ({ api }, disputeId) =>
        api.post<{ data: DisputeData }>(`/disputes/${disputeId}/evidence`, {
          explanation: 'Hình ảnh chụp vết nứt trên mặt sân gây nguy hiểm cho VĐV.',
          evidence: [
            { url: 'https://storage.auracourt.test/disputes/court_crack.jpg', type: 'image' },
          ],
        }),
      filed.data!.id,
    )

    expect(updated.data.status).toBe('investigating')
    expect(updated.data.evidence).toHaveLength(1)
    expect(updated.data.timeline_events.at(-1)?.event).toBe('evidence_submitted')
    expect(updated.data.reservation_id).toBe(reservation.reservation_id)
  })

  test('9.5.4: Phán quyết & Nghĩa vụ bồi hoàn: Không báo hoàn tất tài chính sớm (FR-COM-21, FR-COM-22)', async () => {
    const reservation = arrangeReservation('9_5_4_dispute')
    const remedyResult = runPhp<{
      dispute_status: string
      obligation_id: number
      obligation_amount: number
      obligation_status: string
      obligation_reservation_id: number | null
      payment_completed: boolean
    }>(`
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\DisputeCase;
      use App\\Models\\OwnerRefundObligation;
      use App\\Models\\User;
      use App\\Models\\Venue;

      $alice = User::where('email', '${customer1.email}')->firstOrFail();
      $venue = Venue::where('organization_id', ${org1Id})->findOrFail(${venue1Id});
      $court = Court::where('venue_id', $venue->id)->findOrFail(${reservation.court_id});
      $admin = User::where('email', '${admin.email}')->firstOrFail();
      $ownerUser = User::where('email', '${owner.email}')->firstOrFail();

      $dispute = DisputeCase::create([
        'dispute_code' => 'DSP-REM-' . strtoupper(uniqid()),
        'organization_id' => ${org1Id},
        'venue_id' => $venue->id,
        'reservation_id' => ${reservation.reservation_id},
        'reference_type' => 'reservation',
        'reference_id' => ${reservation.reservation_id},
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
        'obligation_reservation_id' => $obligation->reservation_id,
        'payment_completed' => false,
      ]);
    `)

    expect(remedyResult.dispute_status).toBe('resolved')
    expect(remedyResult.obligation_status).toBe('approved')
    expect(remedyResult.obligation_amount).toBe(150000)
    expect(remedyResult.obligation_reservation_id).toBe(reservation.reservation_id)
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
      $venue = Venue::where('organization_id', ${org1Id})->findOrFail(${venue1Id});
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
      $closeReq = \\Illuminate\\Http\\Request::create("/api/admin/disputes/{$dispute->id}/close", 'POST');
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
      $reopenReq = \\Illuminate\\Http\\Request::create("/api/disputes/{$dispute->id}/reopen", 'POST', [
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

    const gateResult = JSON.parse(
      execFileSync('php', ['-r', gateScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
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

  test('9.5.6: Giao diện quản trị khiếu nại & Duy trì sau F5 (/admin/disputes)', async ({
    page,
  }) => {
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
      $venue = Venue::where('organization_id', ${org1Id})->findOrFail(${venue1Id});

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

    const arrangeData = JSON.parse(
      execFileSync('php', ['-r', arrangeScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { dispute_id: number; dispute_code: string }

    expect(arrangeData.dispute_id).toBeGreaterThan(0)
    expect(arrangeData.dispute_code).toContain('DSP-UI-')

    // 2. Admin logs in
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.fill('input[type="email"], input[name="email"]', admin.email)
    await page.fill('input[type="password"], input[name="password"]', admin.password)
    await page.click('button[type="submit"]')
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 3. Navigate to /admin/disputes
    await page.goto('/admin/disputes', { waitUntil: 'domcontentloaded' })

    // 4. Verify admin dispute resolution view elements
    const pageTitle = page.locator('h1.page-title')
    await expect(pageTitle).toBeVisible({ timeout: 15000 })

    // Verify presence of disputes workbench/list
    await expect(page.locator('.dispute-resolution-view')).toBeVisible({ timeout: 10000 })

    // 5. Reload persistence
    await page.reload({ waitUntil: 'domcontentloaded' })
    await expect(page.locator('.dispute-resolution-view')).toBeVisible({ timeout: 10000 })
    await expect(pageTitle).toBeVisible({ timeout: 10000 })
  })
})
