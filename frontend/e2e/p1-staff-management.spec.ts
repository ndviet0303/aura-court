import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { withE2EApi } from './runner/with-api.ts'
import { test, expect } from '@playwright/test'
import { getTestManifest } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'
import { execFileSync } from 'node:child_process'
import path from 'node:path'

test.describe('P1: Quản lý nhân viên, Vai trò, Thu hồi quyền & Hạn mức quota @p1', () => {
  test.beforeEach(async ({ page }) => {
    const manifest = getTestManifest()
    const isRemoteProd = manifest.database.name === 'production_protected' || !manifest.database.host
    if (isRemoteProd) {
      test.skip(true, 'Yêu cầu môi trường database cục bộ để quản lý staff/invitation và kiểm tra DB trực tiếp')
    }

    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  test.afterEach(async () => {
    const manifest = getTestManifest()
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
    const backendDir = path.resolve(import.meta.dirname, '../../backend')
    const cleanupScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\User;
      use App\\Models\\OrganizationMember;
      use App\\Models\\OrganizationInvitation;

      $testUsers = User::where('email', 'like', '%@staff-${manifest.runId.replaceAll('_', '-')}.auracourt.test')->get();
      foreach ($testUsers as $tu) {
        OrganizationMember::where('user_id', $tu->id)->delete();
        OrganizationInvitation::where('email', $tu->email)->delete();
        $tu->delete();
      }
      OrganizationInvitation::where('email', 'like', '%@staff-${manifest.runId.replaceAll('_', '-')}.auracourt.test')->delete();
    `
    try {
      execFileSync('php', ['-r', cleanupScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      })
    } catch {}
  })

  test('7.6.1: Danh bạ nhân viên, điều hướng tab, chỉ số KPI & gửi lời mời qua modal', async ({ page }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const orgId = manifest.fixtures?.organizations.org1?.id
    if (!orgId) throw new Error('Org1 ID missing from manifest')

    // 1. Log in as owner and navigate to /admin/staff
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto('/admin/staff', { waitUntil: 'domcontentloaded' })
    await page.waitForLoadState('networkidle')

    // 2. Verify header & KPI stats
    await expect(page.locator('.page-title')).toContainText('Nhân sự & Phân quyền')
    const kpiCards = page.locator('.staff-kpi-grid .stat-card')
    await expect(kpiCards.first()).toBeVisible({ timeout: 10000 })
    expect(await kpiCards.count()).toBe(4)

    // 3. Verify view navigation tabs (Directory, Roles matrix, Audit)
    const navButtons = page.locator('.view-nav-btn')
    await expect(navButtons).toHaveCount(3)

    // Switch to Roles tab
    await navButtons.nth(1).click()
    await expect(page.locator('.role-matrix-container')).toBeVisible({ timeout: 5000 })

    // Switch to Audit tab
    await navButtons.nth(2).click()
    await expect(page.locator('.audit-logs-container')).toBeVisible({ timeout: 5000 })

    // Switch back to Directory tab
    await navButtons.nth(0).click()
    await expect(page.locator('.staff-directory-container')).toBeVisible({ timeout: 5000 })

    // 4. Open Invite Modal
    const inviteBtn = page.locator('[data-testid="invite-staff-btn"], .btn-primary-cta, button:has-text("Mời Nhân Sự")').first()
    await expect(inviteBtn).toBeVisible()
    await inviteBtn.click()

    const modal = page.locator('.modal-backdrop')
    await expect(modal).toBeVisible()
    await expect(page.locator('.modal-title')).toContainText('Thêm Nhân Viên Mới & Phân Quyền')
    // 5. Fill invitation form
    const invitedEmail = `staff.invited.${Date.now()}@staff-${manifest.runId.replaceAll('_', '-')}.auracourt.test`
    await modal.locator('input[type="text"]').first().fill('Lê Nhân Viên Thu Ngân')
    await modal.locator('input[type="email"]').first().fill(invitedEmail)
    await modal.locator('input[type="tel"]').first().fill('0901234567')

    // Click send invite
    await modal.locator('button:has-text("Gửi Thư Mời"), [data-testid="send-invite-btn"]').first().click()
    // 6. Assert feedback alert appears (or wait briefly if network cleared it)
    const notice = page.locator('[data-testid="staff-notice"], .alert-success, .alert-pill').first()
    await notice.waitFor({ state: 'visible', timeout: 5000 }).catch(() => {})

    // 7. Verify new row appears in directory table with status invited
    const invitedRow = page.locator('.table-grid-row', { hasText: invitedEmail })
    await expect(invitedRow).toBeVisible({ timeout: 10000 })
    await expect(invitedRow.locator('.status-indicator')).toContainText('Đã mời')

    // 8. Verify backend DB: OrganizationInvitation created with status pending and valid token
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

    const checkInviteScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\OrganizationInvitation;
      use App\\Models\\OrganizationMember;

      $inv = OrganizationInvitation::where('organization_id', ${orgId})
        ->where('email', '${invitedEmail}')
        ->first();

      $mem = OrganizationMember::where('organization_id', ${orgId})
        ->whereHas('user', fn($q) => $q->where('email', '${invitedEmail}'))
        ->first();

      echo json_encode([
        'invitation_found' => (bool) $inv,
        'invitation_status' => $inv?->status,
        'invitation_token_length' => strlen($inv?->token ?? ''),
        'invitation_expires_in_future' => $inv ? ($inv->expires_at > now()) : false,
        'member_found' => (bool) $mem,
        'member_status' => $mem?->status,
      ]);
    `

    const checkResult = JSON.parse(execFileSync('php', ['-r', checkInviteScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as {
      invitation_found: boolean
      invitation_status: string
      invitation_token_length: number
      invitation_expires_in_future: boolean
      member_found: boolean
      member_status: string
    }

    expect(checkResult.invitation_found).toBe(true)
    expect(checkResult.invitation_status).toBe('pending')
    expect(checkResult.invitation_token_length).toBe(64)
    expect(checkResult.invitation_expires_in_future).toBe(true)
    expect(checkResult.member_found).toBe(true)
    expect(checkResult.member_status).toBe('invited')
  })

  test('7.6.2: Chấp nhận lời mời qua token & Quyền vai trò theo cơ sở có hiệu lực', async ({ page }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const org1 = manifest.fixtures?.organizations.org1
    const orgId = org1?.id
    const venue1Id = org1?.venue1A?.id
    const venue2Id = org1?.venue1B?.id
    if (!orgId || !venue1Id) throw new Error('Org1 or Venue1 ID missing from manifest')

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

    // 1. Arrange a pending invitation for a specific user with role cashier and venue1 grant
    const inviteeEmail = `invitee.accept.${Date.now()}@staff-${manifest.runId.replaceAll('_', '-')}.auracourt.test`
    const defaultPassword = `Password123!@#${manifest.runId}`
    const arrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\OrganizationInvitation;
      use App\\Models\\OrganizationMember;
      use App\\Models\\User;
      use Illuminate\\Support\\Facades\\Hash;
      use Illuminate\\Support\\Str;

      $ownerUser = User::where('email', '${owner.email}')->firstOrFail();

      $user = User::create([
        'name' => 'Quản Lý Chấp Nhận',
        'email' => '${inviteeEmail}',
        'password' => Hash::make('${defaultPassword}'),
        'role' => 'customer',
        'status' => 'active',
        'email_verified_at' => now(),
        'phone' => '0901000999',
        'phone_verified_at' => now(),
        'password_set' => true,
      ]);

      $token = Str::random(64);
      $inv = OrganizationInvitation::create([
        'organization_id' => ${orgId},
        'invited_by' => $ownerUser->id,
        'email' => '${inviteeEmail}',
        'role' => 'venue_manager',
        'venue_id' => ${venue1Id},
        'venue_ids' => [${venue1Id}],
        'permissions' => ['venue_id' => ${venue1Id}, 'venue_ids' => [${venue1Id}]],
        'token' => $token,
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
      ]);

      OrganizationMember::create([
        'organization_id' => ${orgId},
        'user_id' => $user->id,
        'role' => 'venue_manager',
        'status' => 'invited',
        'permissions' => ['venue_id' => ${venue1Id}, 'venue_ids' => [${venue1Id}]],
      ]);

      echo json_encode(['token' => $token, 'user_id' => $user->id]);
    `

    const arranged = JSON.parse(execFileSync('php', ['-r', arrangeScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as { token: string; user_id: number }

    // 2. Invitee logs in
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(inviteeEmail)
    await page.locator('input[type="password"], input[name="password"]').fill(defaultPassword)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 3. Accept invitation by token via authenticated API caller
    const acceptRes = await withE2EApi(page, async ({ api }, token) => {
      return await api.post(`/invitations/${token}/accept`)
    }, arranged.token) as {
      message?: string
      invitation?: { status: string }
      member?: { status: string; role: string; permissions: any }
    }

    expect(acceptRes.invitation?.status).toBe('accepted')
    expect(acceptRes.member?.status).toBe('active')
    expect(acceptRes.member?.role).toBe('venue_manager')

    // 4. Verify Idempotency: calling accept again returns 200 without error
    const reAcceptRes = await withE2EApi(page, async ({ api }, token) => {
      return await api.post(`/invitations/${token}/accept`)
    }, arranged.token) as {
      message?: string
    }

    expect(reAcceptRes.message).toContain('Lời mời đã được chấp nhận trước đó.')

    // 5. Verify backend member permissions are strictly scoped to venue1
    const verifyMemberScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\OrganizationMember;
      use App\\Models\\User;

      $user = User::find(${arranged.user_id});
      $member = OrganizationMember::where('organization_id', ${orgId})
        ->where('user_id', ${arranged.user_id})
        ->first();

      $canManageVenue1 = $user->canManageVenue(${venue1Id});
      $canManageVenue2 = ${venue2Id ? `$user->canManageVenue(${venue2Id})` : 'false'};

      echo json_encode([
        'status' => $member->status,
        'role' => $member->role,
        'venue_ids' => $member->venue_ids,
        'can_manage_venue1' => $canManageVenue1,
        'can_manage_venue2' => $canManageVenue2,
      ]);
    `

    const memberCheck = JSON.parse(execFileSync('php', ['-r', verifyMemberScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as {
      status: string
      role: string
      venue_ids: number[]
      can_manage_venue1: boolean
      can_manage_venue2: boolean
    }

    expect(memberCheck.status).toBe('active')
    expect(memberCheck.role).toBe('venue_manager')
    expect(memberCheck.can_manage_venue1).toBe(true)
    // Sibling venue access must be denied without explicit grant
    expect(memberCheck.can_manage_venue2).toBe(false)
  })

  test('7.6.3: Thu hồi lời mời & Chứng minh việc thu hồi có hiệu lực tuyệt đối', async ({ page }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const orgId = manifest.fixtures?.organizations.org1?.id
    if (!orgId) throw new Error('Org1 ID missing from manifest')

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

    // 1. Arrange a pending invitation to be revoked
    const candidateEmail = `candidate.revoked.${Date.now()}@staff-${manifest.runId.replaceAll('_', '-')}.auracourt.test`
    const defaultPassword = `Password123!@#${manifest.runId}`
    const arrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\OrganizationInvitation;
      use App\\Models\\OrganizationMember;
      use App\\Models\\User;
      use Illuminate\\Support\\Facades\\Hash;
      use Illuminate\\Support\\Str;

      $ownerUser = User::where('email', '${owner.email}')->firstOrFail();

      $user = User::create([
        'name' => 'Ứng Viên Thu Hồi',
        'email' => '${candidateEmail}',
        'password' => Hash::make('${defaultPassword}'),
        'role' => 'customer',
        'status' => 'active',
        'email_verified_at' => now(),
        'phone' => '0901000888',
        'phone_verified_at' => now(),
        'password_set' => true,
      ]);

      $token = Str::random(64);
      $inv = OrganizationInvitation::create([
        'organization_id' => ${orgId},
        'invited_by' => $ownerUser->id,
        'email' => '${candidateEmail}',
        'role' => 'staff',
        'token' => $token,
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
      ]);

      OrganizationMember::create([
        'organization_id' => ${orgId},
        'user_id' => $user->id,
        'role' => 'staff',
        'status' => 'invited',
      ]);

      echo json_encode(['invitation_id' => $inv->id, 'token' => $token]);
    `

    const arranged = JSON.parse(execFileSync('php', ['-r', arrangeScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as { invitation_id: number; token: string }

    // 2. Owner logs in
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 3. Owner revokes the invitation via API
    const revokeRes = await withE2EApi(page, async ({ api }, { orgId, invId }) => {
      return await api.post(`/organizations/${orgId}/invitations/${invId}/revoke`)
    }, { orgId, invId: arranged.invitation_id }) as { message?: string; invitation?: { status: string } }

    expect(revokeRes.message).toContain('Đã thu hồi lời mời thành công.')
    expect(revokeRes.invitation?.status).toBe('revoked')

    // 4. Invitee logs in
    await withE2EApi(page, async ({ authApi }) => authApi.logout())
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(candidateEmail)
    await page.locator('input[type="password"], input[name="password"]').fill(defaultPassword)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 5. Invitee attempts to accept revoked invitation -> MUST fail with 422
    const acceptAttempt = await withE2EApi(page, async ({ api }, token) => {
      try {
        await api.post(`/invitations/${token}/accept`)
        return { success: true }
      } catch (err: any) {
        return {
          success: false,
          status: err?.status || err?.response?.status,
          message: err?.message || err?.data?.message,
        }
      }
    }, arranged.token)

    expect(acceptAttempt.success).toBe(false)
    expect(acceptAttempt.status).toBe(422)
    expect(acceptAttempt.message).toContain('Lời mời đã bị thu hồi.')
  })

  test('7.6.4: Cập nhật vai trò nhân viên, quyền cơ sở & Khóa/đình chỉ thành viên', async ({ page }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const cashier = getActorCredentials(manifest, 'cashier1')
    const orgId = manifest.fixtures?.organizations.org1?.id
    if (!orgId) throw new Error('Org1 ID missing from manifest')

    // 1. Log in as owner and navigate to /admin/staff
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto('/admin/staff', { waitUntil: 'domcontentloaded' })
    await page.waitForLoadState('networkidle')

    // 2. Locate cashier member row
    const staffRow = page.locator('.table-grid-row', { hasText: cashier.email }).first()
    await expect(staffRow).toBeVisible({ timeout: 10000 })

    // 3. Open Edit Role Modal
    const editBtn = staffRow.locator('[data-testid="edit-staff-btn"], button:has(span:text-is("edit"))').first()
    await editBtn.click()

    const editModal = page.locator('.modal-backdrop')
    await expect(editModal).toBeVisible()
    await expect(page.locator('.modal-title')).toContainText('Cập nhật Quyền hạn & Trạng thái')

    // 4. Change role to Manager and save
    const roleDropdown = editModal.locator('.custom-dropdown-trigger, [data-testid="edit-role-select"], button[aria-label="Dropdown"]').first()
    await roleDropdown.click()
    await page.locator('.dropdown-item', { hasText: 'Quản lý cơ sở' }).first().click()

    await editModal.locator('button:has-text("Lưu Thay Đổi"), [data-testid="confirm-edit-role-btn"]').first().click()
    // Notice appears
    const notice = page.locator('[data-testid="staff-notice"], .alert-success, .alert-pill').first()
    await notice.waitFor({ state: 'visible', timeout: 5000 }).catch(() => {})
    // 5. Verify role badge in table is now Manager
    await expect(staffRow.locator('.role-badge')).toContainText('Quản lý cơ sở')

    // 6. Toggle lock / suspend status
    const toggleBtn = staffRow.locator('[data-testid="toggle-status-btn"], button:has-text("lock"), .actions-col button:nth-child(2)').first()
    await toggleBtn.click()

    // Status indicator updates to suspended
    await expect(staffRow.locator('.status-indicator')).toContainText('Tạm ngưng', { timeout: 10000 })
    // 7. Verify backend DB confirms status is suspended and audit log recorded
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

    const checkAuditScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\ElevatedAuditLog;
      use App\\Models\\OrganizationMember;

      $mem = OrganizationMember::where('organization_id', ${orgId})
        ->whereHas('user', fn($q) => $q->where('email', '${cashier.email}'))
        ->first();

      $log = ElevatedAuditLog::where('organization_id', ${orgId})
        ->where('action', 'STAFF_ROLE_UPDATED')
        ->latest()
        ->first();

      echo json_encode([
        'status' => $mem?->status,
        'role' => $mem?->role,
        'audit_found' => (bool) $log,
        'audit_action' => $log?->action,
      ]);
    `

    const auditCheck = JSON.parse(execFileSync('php', ['-r', checkAuditScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as {
      status: string
      role: string
      audit_found: boolean
      audit_action: string
    }

    expect(auditCheck.status).toBe('suspended')
    expect(auditCheck.role).toBe('venue_manager')
    expect(auditCheck.audit_found).toBe(true)
    expect(auditCheck.audit_action).toBe('STAFF_ROLE_UPDATED')

    // Restore cashier to original role/status for other tests
    const resetCashierScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();
      use App\\Models\\OrganizationMember;
      OrganizationMember::where('organization_id', ${orgId})
        ->whereHas('user', fn($q) => $q->where('email', '${cashier.email}'))
        ->update(['role' => 'cashier', 'status' => 'active']);
    `
    try {
      execFileSync('php', ['-r', resetCashierScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      })
    } catch {}
  })

  test('7.6.5: Bảo vệ chủ sân duy nhất (không thể hạ cấp, khóa hay xóa active owner cuối cùng)', async ({ page }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const orgId = manifest.fixtures?.organizations.org1?.id
    if (!orgId) throw new Error('Org1 ID missing from manifest')

    // 1. Log in as owner and navigate to /admin/staff
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto('/admin/staff', { waitUntil: 'domcontentloaded' })
    await page.waitForLoadState('networkidle')

    // 2. In UI: verify remove button for owner is disabled
    const ownerRow = page.locator('.table-grid-row', { hasText: owner.email }).first()
    await expect(ownerRow).toBeVisible({ timeout: 10000 })
    const removeBtn = ownerRow.locator('[data-testid="remove-staff-btn"], button:has(span:text-is("delete"))').first()
    await expect(removeBtn).toBeDisabled()
    // Arrange a dedicated single-owner test organization to test the last active owner protection invariant
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

    const arrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Organization;
      use App\\Models\\OrganizationMember;
      use App\\Models\\User;
      use Illuminate\\Support\\Str;

      $ownerUser = User::where('email', '${owner.email}')->firstOrFail();
      $testOrg = Organization::create([
        'name' => 'Single Owner Org ' . Str::random(6),
        'slug' => 'single-owner-org-' . Str::lower(Str::random(6)),
        'partner_status' => 'approved',
      ]);
      $ownerMember = OrganizationMember::create([
        'organization_id' => $testOrg->id,
        'user_id' => $ownerUser->id,
        'role' => 'organization_owner',
        'status' => 'active',
      ]);
      echo json_encode(['org_id' => $testOrg->id, 'member_id' => $ownerMember->id]);
    `
    const arrangedSingle = JSON.parse(execFileSync('php', ['-r', arrangeScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as { org_id: number; member_id: number }

    // 3. Attempt direct API demotion of the only active owner -> MUST fail with 422
    const demoteAttempt = await withE2EApi(page, async ({ api }, { testOrgId, memberId }) => {
      try {
        await api.patch(`/organizations/${testOrgId}/members/${memberId}`, { role: 'staff' })
        return { success: true }
      } catch (err: any) {
        return {
          success: false,
          status: err?.status || err?.response?.status,
          message: err?.message || err?.data?.message,
        }
      }
    }, { testOrgId: arrangedSingle.org_id, memberId: arrangedSingle.member_id })

    expect(demoteAttempt.success).toBe(false)
    expect(demoteAttempt.status).toBe(422)
    expect(demoteAttempt.message).toContain('Cannot demote or suspend the last active organization owner.')

    // 4. Attempt direct API suspension of the only active owner -> MUST fail with 422
    const suspendAttempt = await withE2EApi(page, async ({ api }, { testOrgId, memberId }) => {
      try {
        await api.patch(`/organizations/${testOrgId}/members/${memberId}`, { role: 'organization_owner', status: 'suspended' })
        return { success: true }
      } catch (err: any) {
        return {
          success: false,
          status: err?.status || err?.response?.status,
          message: err?.message || err?.data?.message,
        }
      }
    }, { testOrgId: arrangedSingle.org_id, memberId: arrangedSingle.member_id })

    expect(suspendAttempt.success).toBe(false)
    expect(suspendAttempt.status).toBe(422)
    expect(suspendAttempt.message).toContain('Cannot demote or suspend the last active organization owner.')

    // 5. Attempt direct API deletion of the only active owner -> MUST fail with 422
    const deleteAttempt = await withE2EApi(page, async ({ api }, { testOrgId, memberId }) => {
      try {
        await api.delete(`/organizations/${testOrgId}/members/${memberId}`)
        return { success: true }
      } catch (err: any) {
        return {
          success: false,
          status: err?.status || err?.response?.status,
          message: err?.message || err?.data?.message,
        }
      }
    }, { testOrgId: arrangedSingle.org_id, memberId: arrangedSingle.member_id })

    expect(deleteAttempt.success).toBe(false)
    expect(deleteAttempt.status).toBe(422)
    expect(deleteAttempt.message).toContain('Cannot remove the last active organization owner.')

    // Clean up testOrg
    const cleanupScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Organization;
      use App\\Models\\OrganizationMember;

      OrganizationMember::where('organization_id', ${arrangedSingle.org_id})->delete();
      Organization::where('id', ${arrangedSingle.org_id})->delete();
    `
    try {
      execFileSync('php', ['-r', cleanupScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      })
    } catch {}
  })

  test('7.6.6: Thực thi hạn mức số lượng nhân viên theo gói subscription', async ({ page }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')

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

    // 1. Arrange a test organization with active plan where max_staff is 1, and 1 staff is already active
    const arrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Organization;
      use App\\Models\\OrganizationMember;
      use App\\Models\\PlatformPlan;
      use App\\Models\\PlatformSubscription;
      use App\\Models\\User;
      use Illuminate\\Support\\Str;

      $ownerUser = User::where('email', '${owner.email}')->firstOrFail();

      $plan = PlatformPlan::create([
        'code' => 'plan-quota-staff-' . Str::random(8),
        'name' => 'Single Staff Quota Plan',
        'version' => 1,
        'price' => 200000,
        'currency' => 'VND',
        'billing_cycle' => 'monthly',
        'max_venues' => 1,
        'max_courts' => 2,
        'max_staff' => 1,
        'status' => 'published',
        'is_active' => true,
        'published_at' => now(),
      ]);

      $org = Organization::create([
        'name' => 'CLB Giới Hạn Quota Staff ' . Str::random(6),
        'slug' => 'clb-quota-staff-' . Str::random(6),
        'status' => 'active',
        'partner_status' => 'approved',
      ]);

      OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $ownerUser->id,
        'role' => 'organization_owner',
        'status' => 'active',
      ]);

      // Seed 1 active staff member to completely fill the quota (1/1)
      $staffUser = User::create([
        'name' => 'Nhân Viên Đầy Quota',
        'email' => 'full.quota.staff.' . Str::random(8) . '@staff-${manifest.runId.replaceAll('_', '-')}.auracourt.test',
        'password' => bcrypt('Password123!@#'),
        'role' => 'customer',
        'status' => 'active',
        'email_verified_at' => now(),
      ]);

      OrganizationMember::create([
        'organization_id' => $org->id,
        'user_id' => $staffUser->id,
        'role' => 'staff',
        'status' => 'active',
      ]);

      PlatformSubscription::create([
        'organization_id' => $org->id,
        'plan_id' => $plan->id,
        'plan_version' => 1,
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'current_period_starts_at' => now()->subDay(),
        'current_period_ends_at' => now()->addDays(30),
      ]);

      echo json_encode(['org_id' => $org->id, 'plan_id' => $plan->id]);
    `

    const arranged = JSON.parse(execFileSync('php', ['-r', arrangeScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as { org_id: number; plan_id: number }

    // 2. Log in as owner
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 3. Attempt to invite a 2nd staff member when quota is 1 -> MUST fail with 422 validation exception
    const quotaExceededAttempt = await withE2EApi(page, async ({ api }, testOrgId) => {
      try {
        await api.post(`/organizations/${testOrgId}/invitations`, {
          email: `overflow.staff@staff-${manifest.runId.replaceAll('_', '-')}.auracourt.test`,
          role: 'staff',
        })
        return { success: true }
      } catch (err: any) {
        return {
          success: false,
          status: err?.status || err?.response?.status,
          message: err?.message || err?.data?.message,
        }
      }
    }, arranged.org_id)

    expect(quotaExceededAttempt.success).toBe(false)
    expect(quotaExceededAttempt.status).toBe(422)
    expect(quotaExceededAttempt.message).toContain('Đã đạt giới hạn tối đa 1 nhân sự của gói')
  })

  test('7.6.7: Chuyển đổi ngữ cảnh tổ chức làm việc (Switch organization)', async ({ page }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const org1Id = manifest.fixtures?.organizations.org1?.id
    if (!org1Id) throw new Error('Org1 ID missing from manifest')

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

    // 1. Arrange a second organization org_switch where owner1 is also an active owner
    const switchOrgScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Organization;
      use App\\Models\\OrganizationMember;
      use App\\Models\\User;
      use App\\Models\\Venue;
      use Illuminate\\Support\\Str;

      $ownerUser = User::where('email', '${owner.email}')->firstOrFail();

      $org2 = Organization::create([
        'name' => 'Tổ Chức Thứ Hai Đổi Bối Cảnh ' . Str::random(6),
        'slug' => 'to-chuc-thu-hai-' . Str::random(6),
        'status' => 'active',
        'partner_status' => 'approved',
      ]);

      OrganizationMember::create([
        'organization_id' => $org2->id,
        'user_id' => $ownerUser->id,
        'role' => 'organization_owner',
        'status' => 'active',
      ]);

      $venue = Venue::create([
        'organization_id' => $org2->id,
        'name' => 'Sân Riêng Tổ Chức Hai ' . Str::random(4),
        'slug' => 'san-rieng-hai-' . Str::random(4),
        'address' => '456 Lê Duẩn, Quận 1',
        'status' => 'published',
        'is_active' => true,
      ]);

      echo json_encode(['org_id' => $org2->id, 'venue_id' => $venue->id]);
    `

    const arrangedSwitch = JSON.parse(execFileSync('php', ['-r', switchOrgScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as { org_id: number; venue_id: number }

    // 2. Log in as owner
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 3. Query members of org1 vs members of org_switch to verify clean isolation
    const isolationTest = await withE2EApi(page, async ({ api }, { org1Id, org2Id }) => {
      const org1Res = await api.get(`/organizations/${org1Id}/members`) as { members: any[] }
      const org2Res = await api.get(`/organizations/${org2Id}/members`) as { members: any[] }

      return {
        org1MembersCount: org1Res.members.length,
        org2MembersCount: org2Res.members.length,
        org2MemberOrgIds: org2Res.members.map((m: any) => m.organization_id),
      }
    }, { org1Id, org2Id: arrangedSwitch.org_id })

    expect(isolationTest.org1MembersCount).toBeGreaterThanOrEqual(4)
    expect(isolationTest.org2MembersCount).toBe(1)
    expect(isolationTest.org2MemberOrgIds.every((id: number) => id === arrangedSwitch.org_id)).toBe(true)
  })
})
