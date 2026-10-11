import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { withE2EApi } from './runner/with-api.ts'
import { test, expect } from '@playwright/test'
import { getTestManifest } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'
import { execFileSync } from 'node:child_process'
import path from 'node:path'

function getBackendEnv(manifest: any) {
  return getCanonicalBackendEnv(manifest)
}

test.describe('P1: Vòng đời tài khoản nhận tiền, Xác thực, Thay đổi tài khoản & Lưu giữ snapshot @p1', () => {
  test.beforeEach(async ({ page }) => {
    const manifest = getTestManifest()

    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  test('7.5.1: Hướng dẫn khi thiếu kết nối nhận tiền & Chặn công khai cơ sở chưa sẵn sàng', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const orgId = manifest.fixtures?.organizations.org1?.id
    if (!orgId) throw new Error('Org1 ID missing from manifest')

    // 1. Arrange a fresh draft venue without receiving connection
    const backendDir = path.resolve(import.meta.dirname, '../../backend')
    const backendEnv = getBackendEnv(manifest)

    const uniqueSlug = `rc-ui-guide-${Date.now()}`
    const createVenueScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Venue;

      $venue = Venue::create([
        'organization_id' => ${orgId},
        'name' => 'Sân Kiểm Tra Hướng Dẫn ${uniqueSlug}',
        'slug' => '${uniqueSlug}',
        'address' => '123 Đường Nam Kỳ Khởi Nghĩa, Quận 3',
        'status' => 'draft',
        'is_active' => false,
      ]);

      echo json_encode(['id' => $venue->id, 'slug' => $venue->slug]);
    `

    const created = JSON.parse(
      execFileSync('php', ['-r', createVenueScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { id: number; slug: string }

    // 2. Log in as owner and navigate to /admin/venues
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto('/admin/venues', { waitUntil: 'domcontentloaded' })

    // 3. Find venue card and open readiness modal
    const venueCard = page.locator('.venue-admin-card', { hasText: created.slug })
    await expect(venueCard).toBeVisible({ timeout: 10000 })
    await venueCard
      .locator('button:has-text("Điều kiện"), button:has-text("Readiness"), .btn-action-sm')
      .first()
      .click()

    // 4. Modal and OwnerReceivingConnectionPanel are displayed
    const panel = page.locator('[data-testid="owner-receiving-connection-panel"]')
    await expect(panel).toBeVisible({ timeout: 10000 })

    // 5. Verify onboarding guidance and creation form are rendered
    await expect(panel.locator('[data-testid="receiving-connection-create"]')).toBeVisible()
    await expect(panel.locator('[data-testid="receiving-bank-code"]')).toBeVisible()
    await expect(panel.locator('[data-testid="receiving-account-name"]')).toBeVisible()
    await expect(panel.locator('[data-testid="receiving-account-number"]')).toBeVisible()

    // 6. Verify missing receiving connection warning is listed in readiness checklist
    const readinessModal = page.locator('.fixed.inset-0', { hasText: 'Điều kiện & Xuất bản Cơ sở' })
    await expect(readinessModal).toContainText('điều kiện còn thiếu')
  })

  test('7.5.2: Tạo kết nối nhận tiền, đổi thông tin xác thực (HMAC & API token) qua giao diện', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const orgId = manifest.fixtures?.organizations.org1?.id
    if (!orgId) throw new Error('Org1 ID missing from manifest')

    // 1. Arrange a fresh draft venue without receiving connection
    const backendDir = path.resolve(import.meta.dirname, '../../backend')
    const backendEnv = getBackendEnv(manifest)

    const uniqueSlug = `rc-ui-rotate-${Date.now()}`
    const createVenueScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Venue;

      $venue = Venue::create([
        'organization_id' => ${orgId},
        'name' => 'Sân Xoay Khóa UI ${uniqueSlug}',
        'slug' => '${uniqueSlug}',
        'address' => '456 Đường Nguyễn Đình Chiểu, Quận 3',
        'status' => 'draft',
        'is_active' => false,
      ]);

      echo json_encode(['id' => $venue->id, 'slug' => $venue->slug]);
    `

    const created = JSON.parse(
      execFileSync('php', ['-r', createVenueScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { id: number; slug: string }

    // 2. Log in as owner and navigate to /admin/venues
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto('/admin/venues', { waitUntil: 'domcontentloaded' })

    // 3. Open readiness modal
    const venueCard = page.locator('.venue-admin-card', { hasText: created.slug })
    await expect(venueCard).toBeVisible({ timeout: 10000 })
    await venueCard
      .locator('button:has-text("Điều kiện"), button:has-text("Readiness"), .btn-action-sm')
      .first()
      .click()

    const panel = page.locator('[data-testid="owner-receiving-connection-panel"]')
    await expect(panel).toBeVisible({ timeout: 10000 })

    // 4. Fill in connection creation form via real browser interactions
    await panel.locator('[data-testid="receiving-bank-code"]').fill('MBBank')
    await panel.locator('[data-testid="receiving-account-name"]').fill('NGUYEN VAN CHU SAN')
    await panel.locator('[data-testid="receiving-account-number"]').fill('090123456789')
    await panel.locator('[data-testid="receiving-legal-owner-confirmed"]').check()
    await panel.locator('[data-testid="save-receiving-connection"]').click()

    // 5. Configured panel appears in UI
    const configuredSection = panel.locator('[data-testid="receiving-connection-configured"]')
    await expect(configuredSection).toBeVisible({ timeout: 10000 })
    await expect(panel.locator('[data-testid="receiving-connection-status"]')).toBeVisible()

    // 6. Rotate HMAC secret via UI
    await panel
      .locator('[data-testid="hmac-secret-input"]')
      .fill('new_secret_key_v2_1234567890abcdef_32chars')
    await panel.locator('[data-testid="save-hmac-secret"]').click()

    // Success notice appears
    await expect(panel.locator('.bg-emerald-50')).toBeVisible({ timeout: 10000 })

    // 7. Open Query API details and rotate API token
    await panel.locator('details').evaluate((el: HTMLDetailsElement) => {
      el.open = true
    })
    await panel.locator('details input').first().fill('ACC_SEPAY_998877')
    await panel.locator('[data-testid="sepay-api-token-input"]').fill('A'.repeat(64))
    await panel.locator('[data-testid="save-sepay-api-token"]').click()
    // 8. Assert API token rotation notice appears in UI
    await expect(panel.locator('.bg-emerald-50')).toBeVisible({ timeout: 10000 })
    await expect(panel.locator('text=connection_created').first()).toBeVisible()

    // 9. Re-query API to confirm query_api_configured and provider_account_id persisted
    const refreshedConn = (await withE2EApi(
      page,
      async ({ api }, { orgId, venueId }) => {
        return await api.get(
          `/partner/organizations/${orgId}/venues/${venueId}/receiving-connection`,
        )
      },
      { orgId, venueId: created.id },
    )) as { data: { id: number; query_api_configured: boolean; provider_account_id: string } }

    expect(refreshedConn.data.query_api_configured).toBe(true)
    expect(refreshedConn.data.provider_account_id).toBe('ACC_SEPAY_998877')

    // 10. Query evidence endpoint to prove api_token_rotated and credential_rotated are persisted
    const evidenceRes = (await withE2EApi(
      page,
      async ({ api }, { orgId, venueId, connId }) => {
        return await api.get(
          `/partner/organizations/${orgId}/venues/${venueId}/receiving-connection/${connId}/evidence`,
        )
      },
      { orgId, venueId: created.id, connId: refreshedConn.data.id },
    )) as {
      data: Array<{ id: number; event_type: string; credential_version: number }>
    }

    expect(evidenceRes.data.some((e) => e.event_type === 'api_token_rotated')).toBe(true)
    expect(evidenceRes.data.some((e) => e.event_type === 'credential_rotated')).toBe(true)
  })

  test('7.5.3: Sự kiện xác thực nhận tiền từ webhook & Chọn kết nối đánh dấu cơ sở sẵn sàng', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const orgId = manifest.fixtures?.organizations.org1?.id
    if (!orgId) throw new Error('Org1 ID missing from manifest')

    // 1. Arrange a venue with verified receiving connection that is NOT yet selected
    const backendDir = path.resolve(import.meta.dirname, '../../backend')
    const backendEnv = getBackendEnv(manifest)

    const uniqueSlug = `rc-ui-select-${Date.now()}`
    const arrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\ReceivingConnection;
      use App\\Models\\Venue;
      use Illuminate\\Support\\Facades\\Crypt;
      use Illuminate\\Support\\Str;

      $venue = Venue::create([
        'organization_id' => ${orgId},
        'name' => 'Sân Chọn Kết Nối UI ${uniqueSlug}',
        'slug' => '${uniqueSlug}',
        'address' => '789 Đường Hai Bà Trưng, Quận 3',
        'status' => 'draft',
        'is_active' => false,
      ]);

      $rc = ReceivingConnection::create([
        'organization_id' => ${orgId},
        'venue_id' => $venue->id,
        'owner_user_id' => ${owner.id || 1},
        'provider' => 'sepay',
        'routing_id' => (string) Str::uuid(),
        'bank_code' => 'Vietcombank',
        'account_name_ciphertext' => Crypt::encryptString('CONG TY AURA COURT'),
        'account_number_ciphertext' => Crypt::encryptString('123456789012'),
        'account_last_four' => '9012',
        'webhook_secret_ciphertext' => Crypt::encryptString('verified_secret_123'),
        'credential_version' => 1,
        'owner_attested_at' => now(),
        'verified_at' => null,
        'effective_from' => null,
        'status' => 'pending_verification',
        'is_selected' => false,
      ]);

      echo json_encode([
        'venue_id' => $venue->id,
        'venue_slug' => $venue->slug,
        'rc_id' => $rc->id,
        'routing_id' => (string) $rc->routing_id,
      ]);
    `

    const arranged = JSON.parse(
      execFileSync('php', ['-r', arrangeScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { venue_id: number; venue_slug: string; rc_id: number; routing_id: string }

    // 2. Exercise real signed inbound webhook to verify the receiving connection
    const crypto = await import('node:crypto')
    const timestamp = String(Math.floor(Date.now() / 1000))
    const rawBody = JSON.stringify({
      id: `TXN_VERIFY_${Date.now()}`,
      transactionDate: '2026-09-30 11:20:00',
      accountNumber: '123456789012',
      transferType: 'in',
      transferAmount: 50000,
    })
    const signature =
      'sha256=' +
      crypto
        .createHmac('sha256', 'verified_secret_123')
        .update(`${timestamp}.${rawBody}`)
        .digest('hex')

    const webhookRes = await page.request.post(
      `${manifest.backend.url}/api/v1/webhooks/sepay/connections/${arranged.routing_id}`,
      {
        headers: {
          'X-SePay-Timestamp': timestamp,
          'X-SePay-Signature': signature,
          'Content-Type': 'application/json',
        },
        data: rawBody,
      },
    )

    expect(webhookRes.ok()).toBe(true)
    const webhookData = await webhookRes.json()
    expect(webhookData.success).toBe(true)
    // 3. Log in as owner and navigate to /admin/venues
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto('/admin/venues', { waitUntil: 'domcontentloaded' })

    // 3. Open readiness modal
    const venueCard = page.locator('.venue-admin-card', { hasText: arranged.venue_slug })
    await expect(venueCard).toBeVisible({ timeout: 10000 })
    await venueCard
      .locator('button:has-text("Điều kiện"), button:has-text("Readiness"), .btn-action-sm')
      .first()
      .click()

    const panel = page.locator('[data-testid="owner-receiving-connection-panel"]')
    await expect(panel).toBeVisible({ timeout: 10000 })

    // 4. Verify verified status badge and Select button are visible
    await expect(panel.locator('[data-testid="receiving-connection-status"]')).toBeVisible()
    const selectBtn = panel.locator('[data-testid="select-receiving-connection"]')
    await expect(selectBtn).toBeVisible()

    // 5. Click Select Connection button
    await selectBtn.click()

    // 6. Selected badge appears in UI
    await expect(panel.locator('[data-testid="selected-receiver-badge"]')).toBeVisible({
      timeout: 10000,
    })

    // 7. Verify backend state updated
    const verifyScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Venue;
      $venue = Venue::findOrFail(${arranged.venue_id});
      echo json_encode([
        'current_rc_id' => $venue->current_receiving_connection_id,
        'matches' => (int) $venue->current_receiving_connection_id === ${arranged.rc_id}
      ]);
    `

    const verifyState = JSON.parse(
      execFileSync('php', ['-r', verifyScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { matches: boolean }

    expect(verifyState.matches).toBe(true)
  })

  test('7.5.4: Lưu giữ snapshot thông tin nhận tiền của lượt thanh toán khi chủ sân đổi tài khoản ngân hàng', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const customer = getActorCredentials(manifest, 'customer')
    const orgId = manifest.fixtures?.organizations.org1?.id
    if (!orgId) throw new Error('Org1 ID missing from manifest')
    const backendDir = path.resolve(import.meta.dirname, '../../backend')
    const backendEnv = getBackendEnv(manifest)

    // 1. Create a payment attempt under the current receiving connection
    const uniqueSlug = `rc-snap-${Date.now()}`
    const attemptScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\ReceivingConnection;
      use App\\Models\\User;
      use App\\Models\\Venue;
      use App\\Services\\Payment\\OwnerPaymentAttemptService;
      use Illuminate\\Support\\Facades\\Crypt;
      use Illuminate\\Support\\Str;

      $venue = Venue::create([
        'organization_id' => ${orgId},
        'name' => 'Sân Snapshot Retention ${uniqueSlug}',
        'slug' => '${uniqueSlug}',
        'address' => '123 Đường Snapshot, Quận 1',
        'status' => 'published',
        'is_active' => true,
      ]);

      $conn = ReceivingConnection::create([
        'organization_id' => ${orgId},
        'venue_id' => $venue->id,
        'owner_user_id' => ${owner.id || 1},
        'provider' => 'sepay',
        'routing_id' => (string) Str::uuid(),
        'bank_code' => 'MBBank',
        'account_name_ciphertext' => Crypt::encryptString('AURA SNAPSHOT'),
        'account_number_ciphertext' => Crypt::encryptString('123456789012'),
        'account_last_four' => '9012',
        'status' => ReceivingConnection::STATUS_VERIFIED,
        'webhook_secret_ciphertext' => Crypt::encryptString('secret_snap_v1'),
        'credential_version' => 1,
        'owner_attested_at' => now()->subDay(),
        'verified_at' => now(),
        'effective_from' => now()->subDay(),
      ]);

      $venue->update(['current_receiving_connection_id' => $conn->id]);
      $payer = User::where('email', '${customer.email}')->firstOrFail();

      $service = app(OwnerPaymentAttemptService::class);
      $attempt = $service->createAttempt(
        connection: $conn,
        payer: $payer,
        purpose: 'booking',
        purposeId: '9999',
        reference: 'PAY_SNAP_' . time(),
        amount: 200000,
        currency: 'VND',
        expiresAt: now()->addHour(),
        idempotencyKey: 'IDEM_' . time()
      );

      $initialSnapshot = $attempt->receiverSnapshot();

      echo json_encode([
        'venue_id' => $venue->id,
        'attempt_id' => $attempt->id,
        'connection_id' => $conn->id,
        'initial_version' => (int) $attempt->credential_version,
        'initial_snapshot_conn_id' => (int) ($initialSnapshot['receiving_connection_id'] ?? 0),
        'initial_snapshot_version' => (int) ($initialSnapshot['credential_version'] ?? 0),
      ]);
    `

    const attemptResult = JSON.parse(
      execFileSync('php', ['-r', attemptScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      venue_id: number
      attempt_id: number
      connection_id: number
      initial_version: number
      initial_snapshot_conn_id: number
      initial_snapshot_version: number
    }

    expect(attemptResult.attempt_id).toBeGreaterThan(0)
    expect(attemptResult.initial_snapshot_conn_id).toBe(attemptResult.connection_id)

    // 2. Owner rotates credential on this receiving connection (incrementing version)
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    const rotateRes = (await withE2EApi(
      page,
      async ({ api }, { orgId, venueId, connId }) => {
        return await api.post(
          `/partner/organizations/${orgId}/venues/${venueId}/receiving-connection/${connId}/credential`,
          {
            hmac_secret: 'brand_new_secret_after_attempt_was_created',
          },
        )
      },
      { orgId, venueId: attemptResult.venue_id, connId: attemptResult.connection_id },
    )) as { data: { credential_version: number } }

    expect(rotateRes.data.credential_version).toBe(attemptResult.initial_version + 1)

    // 3. Verify existing payment attempt RETAINS its original immutable receiver snapshot
    const verifyRetentionScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\OwnerPaymentAttempt;

      $attempt = OwnerPaymentAttempt::findOrFail(${attemptResult.attempt_id});
      $retainedSnapshot = $attempt->receiverSnapshot();

      echo json_encode([
        'attempt_version' => (int) $attempt->credential_version,
        'snapshot_version' => (int) ($retainedSnapshot['credential_version'] ?? 0),
        'snapshot_conn_id' => (int) ($retainedSnapshot['receiving_connection_id'] ?? 0),
      ]);
    `

    const verifyExec = JSON.parse(
      execFileSync('php', ['-r', verifyRetentionScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      attempt_version: number
      snapshot_version: number
      snapshot_conn_id: number
    }

    // The existing attempt STILL holds version 1 snapshot even though connection is version 2!
    expect(verifyExec.attempt_version).toBe(attemptResult.initial_version)
    expect(verifyExec.snapshot_version).toBe(attemptResult.initial_snapshot_version)
    expect(verifyExec.snapshot_conn_id).toBe(attemptResult.connection_id)
  })

  test('7.5.5: Người dùng không được phân quyền bị từ chối chỉnh sửa kết nối nhận tiền', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const orgId = manifest.fixtures?.organizations.org1?.id
    const venue = manifest.fixtures?.organizations.org1?.venue1A
    const venueId = venue?.id
    if (!orgId || !venueId) throw new Error('Fixture missing from manifest')

    // 1. Clear session and log in as regular customer
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Customer attempts to create receiving connection: 403 Forbidden
    const deniedCreate = await withE2EApi(
      page,
      async ({ api }, { orgId, venueId }) => {
        try {
          await api.post(`/partner/organizations/${orgId}/venues/${venueId}/receiving-connection`, {
            bank_code: 'MBBank',
            account_name: 'HACKER ACCOUNT',
            account_number: '9999999999',
            legal_owner_confirmed: true,
          })
          return false
        } catch (err: any) {
          return err.status === 403 || err.response?.status === 403
        }
      },
      { orgId, venueId },
    )

    expect(deniedCreate).toBe(true)

    // 3. Customer attempts to rotate credentials on existing connection: 403 Forbidden
    const connId = venue.receivingConnectionId || 1
    const deniedRotate = await withE2EApi(
      page,
      async ({ api }, { orgId, venueId, connId }) => {
        try {
          await api.post(
            `/partner/organizations/${orgId}/venues/${venueId}/receiving-connection/${connId}/credential`,
            {
              hmac_secret: 'unauthorized_hack',
            },
          )
          return false
        } catch (err: any) {
          return err.status === 403 || err.response?.status === 403
        }
      },
      { orgId, venueId, connId },
    )

    expect(deniedRotate).toBe(true)
  })
})
