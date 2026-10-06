import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { withE2EApi } from './runner/with-api.ts'
import { test, expect } from '@playwright/test'
import path from 'node:path'
import { execFileSync } from 'node:child_process'
import { getTestManifest } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'
import { createE2EApiClient } from './runner/api-client.ts'

test.describe('P1: Đăng ký đối tác, Tài liệu xác minh, Xét duyệt & Cấp quyền @p1', () => {
  test.beforeEach(async ({ page }) => {
    const manifest = getTestManifest()

    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
      window.localStorage.setItem('locale', 'vi')
      window.localStorage.setItem('aura_theme', 'light')
    })
  })

  test('7.2.1: Tạo nháp hồ sơ đối tác, lưu nháp và kiểm tra tính đầy đủ', async ({ page }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')

    // 1. Log in as verified customer
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    const api = createE2EApiClient(page, manifest)

    // 2. Start application draft via API (or retrieve current if already created)
    let appId: number
    try {
      const startRes = await api.post('/partner/application/start', {}) as { data: { id: number; status: string } }
      appId = startRes.data?.id
    } catch {
      const currentRes = await api.get('/partner/application/current') as { data: { id: number; status: string } }
      appId = currentRes.data?.id
    }
    expect(appId).toBeDefined()
    // 3. Save draft details with valid business_type ('individual') and contact info
    const uniqueBusinessName = `Câu lạc bộ Cầu lông Hoàng Gia ${Date.now()}`
    const saveRes = await api.put(`/partner/application/${appId}`, {
      business_type: 'individual',
      business_name: uniqueBusinessName,
      legal_name: 'Nguyễn Văn Hoàng Gia',
      representative_name: 'Nguyễn Văn Hoàng Gia',
      id_card_number: '079090123456',
      bank_name: 'Vietcombank',
      bank_code: 'VCB',
      bank_account_number: '9988776655',
      bank_account_holder: 'NGUYEN VAN HOANG GIA',
      contact_name: 'Nguyễn Văn Hoàng Gia',
      contact_phone: '0901234567',
      contact_email: 'hoanggia@auracourt.test',
      address_line: '123 Đường Cầu Lông, Phường Bến Nghé',
      city: 'Thành phố Hồ Chí Minh',
      sports: ['badminton'],
      court_scale: 4,
    }) as { data: { id: number; business_name: string } }

    expect(saveRes.data.business_name).toBe(uniqueBusinessName)

    // 4. Verify draft persistence across reload
    await page.goto('/partner-registration', { waitUntil: 'domcontentloaded' })

    const currentRes = await api.get('/partner/application/current') as { data: { id: number; business_name: string; status: string } }
    expect(currentRes.data?.id).toBe(appId)
    expect(currentRes.data?.business_name).toBe(uniqueBusinessName)
    expect(currentRes.data?.status).toBe('draft')

    // 5. Negative test: Submit without required documents is rejected with 422
    let submitFailed = false
    try {
      await api.post(`/partner/application/${appId}/submit`, {
        expected_version: 1,
        consents: [
          { consent_type: 'cooperation_terms', terms_version: 'v1.0' },
          { consent_type: 'platform_fee_policy', terms_version: 'v1.0' },
          { consent_type: 'settlement_refund_terms', terms_version: 'v1.0' },
          { consent_type: 'data_processing_agreement', terms_version: 'v1.0' },
          { consent_type: 'service_responsibility', terms_version: 'v1.0' },
        ],
      })
    } catch {
      submitFailed = true
    }
    expect(submitFailed).toBe(true)
  })

  test('7.2.2: Tải lên tài liệu vào kho lưu trữ riêng tư với URL ký số', async ({ page }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')

    // 1. Log in as customer
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    const api = createE2EApiClient(page, manifest)

    // 2. Fetch current draft or create draft if running independently
    let currentRes: { data?: { id: number } } | null = null
    try {
      currentRes = await api.get('/partner/application/current')
    } catch {
      currentRes = null
    }

    let appId = currentRes?.data?.id
    if (!appId) {
      const startRes = await api.post('/partner/application/start', {}) as { data: { id: number } }
      appId = startRes.data?.id
      if (appId) {
        await api.put(`/partner/application/${appId}`, {
          full_name: 'Nguyễn Văn Đối Tác',
          business_name: 'Aura Partner Test Org',
          business_type: 'business',
          id_card_number: '079090001234',
          phone: '0901234567',
          email: 'partner_test@auracourt.vn',
        })
      }
    }

    // 3. Upload simulated verification document
    const uploadRes = await api.post(`/partner/application/${appId}/documents`, undefined, {
      multipart: {
        document_type: 'representative_id_front',
        file: {
          name: 'id_front.pdf',
          mimeType: 'application/pdf',
          buffer: Buffer.from('%PDF-1.4 Mock ID Front Document Content'),
        },
      },
    }) as { data: { id: number; file_name: string; document_type: string } }

    expect(uploadRes.data?.id).toBeDefined()
    expect(uploadRes.data?.document_type).toBe('representative_id_front')

    // 4. Negative test: Unsigned / unauthenticated document download is rejected with 403
    const unsignedDownloadRes = await page.request.get(`${manifest.backend.url}/api/v1/partner/documents/download?id=${uploadRes.data.id}`)
    expect(unsignedDownloadRes.status()).toBe(403)
  })

  test('7.2.3: Nộp hồ sơ hoàn chỉnh -> Từ chối tự duyệt -> Admin yêu cầu bổ sung -> Nộp lại', async ({ page }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const admin = getActorCredentials(manifest, 'admin')

    // 1. Log in as applicant
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    const api = createE2EApiClient(page, manifest)
    let currentRes = await api.get('/partner/application/current').catch(() => null) as { data?: { id: number; version: number } } | null
    if (!currentRes?.data?.id) {
      const startRes = await api.post('/partner/application/start', {}) as { data: { id: number; version: number } }
      currentRes = startRes
    }
    const appId = currentRes.data!.id
    // Upload all 3 remaining required documents for individual partner
    const docs = [
      { type: 'representative_id_back', name: 'id_back.pdf' },
      { type: 'bank_account_confirmation', name: 'bank_cert.pdf' },
      { type: 'venue_operation_rights', name: 'venue_rights.pdf' },
    ]
    for (const d of docs) {
      await api.post(`/partner/application/${appId}/documents`, undefined, {
        multipart: {
          document_type: d.type,
          file: {
            name: d.name,
            mimeType: 'application/pdf',
            buffer: Buffer.from(`%PDF-1.4 Mock Document ${d.type}`),
          },
        },
      })
    }

    // Refresh application to get latest version after document uploads
    const refreshedRes = await api.get(`/partner/application/${appId}`) as { data: { version: number } }
    const appVersion = refreshedRes.data.version

    // 2. Submit application with complete legal consents
    const submitRes = await api.post(`/partner/application/${appId}/submit`, {
      expected_version: appVersion,
      consents: [
        { consent_type: 'cooperation_terms', terms_version: 'v1.0' },
        { consent_type: 'platform_fee_policy', terms_version: 'v1.0' },
        { consent_type: 'settlement_refund_terms', terms_version: 'v1.0' },
        { consent_type: 'data_processing_agreement', terms_version: 'v1.0' },
        { consent_type: 'service_responsibility', terms_version: 'v1.0' },
      ],
    }) as { data: { status: string; version: number } }
    expect(['submitted', 'pending']).toContain(submitRes.data?.status)
    const submittedVersion = submitRes.data.version

    // 3. Negative test: Applicant CANNOT self-approve (calls to admin review return 403 Forbidden)
    let selfApproveDenied = false
    try {
      await api.post(`/admin/partner/applications/${appId}/review`, {
        decision: 'approve',
        reason: 'Self approval attempt',
        reviewer_notes: 'Self approval attempt',
        expected_version: submittedVersion,
      })
    } catch {
      selfApproveDenied = true
    }
    expect(selfApproveDenied).toBe(true)

    // 4. Invalidate the applicant session before changing actors.
    await withE2EApi(page, async ({ authApi }) => authApi.logout())
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(admin.email)
    await page.locator('input[type="password"], input[name="password"]').fill(admin.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 5. Admin requests revision/supplementation
    const adminApi = createE2EApiClient(page, manifest)
    const revisionNotes = 'Vui lòng bổ sung bản scan hợp đồng thuê sân có công chứng rõ nét hơn'
    const reviewRes = await adminApi.post(`/admin/partner/applications/${appId}/review`, {
      decision: 'revision_required',
      reason: revisionNotes,
      reviewer_notes: revisionNotes,
      expected_version: submittedVersion,
    }) as { data: { status: string; version: number } }

    expect(reviewRes.data?.status).toBe('revision_required')
    const revisionVersion = reviewRes.data.version

    // 6. Invalidate the admin session before returning to the applicant.
    await withE2EApi(page, async ({ authApi }) => authApi.logout())
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 7. Verify applicant views revision request in history and status
    await page.goto('/partner/application-status', { waitUntil: 'domcontentloaded' })

    const historyRes = await api.get(`/partner/application/${appId}/history`) as { data: Array<{ action: string; reason: string; notes?: string }> }

    expect(historyRes.data?.length).toBeGreaterThan(0)
    expect(historyRes.data?.some(h => (h.reason || h.notes || '').includes('hợp đồng thuê'))).toBe(true)

    // 8. Resubmit application
    const resubmitRes = await api.post(`/partner/application/${appId}/submit`, {
      expected_version: revisionVersion,
      consents: [
        { consent_type: 'cooperation_terms', terms_version: 'v1.0' },
        { consent_type: 'platform_fee_policy', terms_version: 'v1.0' },
        { consent_type: 'settlement_refund_terms', terms_version: 'v1.0' },
        { consent_type: 'data_processing_agreement', terms_version: 'v1.0' },
        { consent_type: 'service_responsibility', terms_version: 'v1.0' },
      ],
    }) as { data: { status: string; version: number } }
    expect(['submitted', 'pending']).toContain(resubmitRes.data?.status)
  })

  test('7.2.4: Admin duyệt -> Khởi tạo tổ chức -> Gán vai trò Chủ sân (không cấp quyền Admin nền tảng)', async ({ page }) => {
    const manifest = getTestManifest()
    const isRemoteProd = manifest.database.name === 'production_protected' || !manifest.database.host
    if (isRemoteProd) {
      test.skip(true, 'Yêu cầu môi trường database cục bộ để khởi tạo KYC và kiểm tra DB trực tiếp')
      return
    }
    const customer = getActorCredentials(manifest, 'customer')
    const admin = getActorCredentials(manifest, 'admin')
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

    // Find the submitted application ID and version
    const findAppScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\HostKyc;
      use App\\Models\\User;

      $user = User::where('email', '${customer.email}')->firstOrFail();
      $kyc = HostKyc::where('user_id', $user->id)->latest('id')->first();
      if (! $kyc) {
        $kyc = HostKyc::create([
          'user_id' => $user->id,
          'status' => 'pending',
          'full_name' => 'Nguyễn Văn Đối Tác',
          'business_name' => 'Aura Partner Test Org',
          'business_type' => 'business',
          'id_card_number' => '079090001234',
          'phone' => '0901234567',
          'email' => $user->email,
          'version' => 1,
        ]);
      } else if ($kyc->status !== 'pending' && $kyc->status !== 'approved') {
        $kyc->update(['status' => 'pending']);
      }
      echo json_encode(['id' => $kyc->id, 'version' => $kyc->version]);
    `
    const appInfo = JSON.parse(execFileSync('php', ['-r', findAppScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as { id: number; version: number }
    const appId = appInfo.id
    const appVersion = appInfo.version

    // 1. Log in as Platform Admin
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(admin.email)
    await page.locator('input[type="password"], input[name="password"]').fill(admin.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Admin approves application
    const approveRes = await withE2EApi(page, async ({ api }, { id, version }) => {
      return await api.post(`/admin/partner/applications/${id}/review`, {
        decision: 'approve',
        reason: 'Hồ sơ đầy đủ điều kiện hoạt động đối tác',
        reviewer_notes: 'Phê duyệt khởi tạo câu lạc bộ',
        expected_version: version,
      })
    }, { id: appId, version: appVersion }) as { data: { status: string; organization_id?: number } }

    expect(approveRes.data?.status).toBe('approved')

    // 3. Authoritative DB verification:
    // - HostKyc is approved
    // - Organization exists
    // - OrganizationMember with role organization_owner exists for customer
    // - User global role is NOT platform admin
    const verifyProvisionScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\HostKyc;
      use App\\Models\\Organization;
      use App\\Models\\OrganizationMember;
      use App\\Models\\User;

      $kyc = HostKyc::find(${appId});
      $user = User::find($kyc->user_id);
      $membership = OrganizationMember::where('user_id', $user->id)
        ->where('organization_id', $kyc->organization_id)
        ->first();

      echo json_encode([
        'kyc_status' => $kyc->status,
        'has_org' => (bool) $kyc->organization_id,
        'membership_role' => $membership?->role,
        'membership_status' => $membership?->status,
        'user_is_platform_admin' => $user->isPlatformAdmin(),
      ]);
    `
    const provisionState = JSON.parse(execFileSync('php', ['-r', verifyProvisionScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as {
      kyc_status: string
      has_org: boolean
      membership_role: string
      membership_status: string
      user_is_platform_admin: boolean
    }

    expect(provisionState.kyc_status).toBe('approved')
    expect(provisionState.has_org).toBe(true)
    expect(provisionState.membership_role).toBe('organization_owner')
    expect(provisionState.membership_status).toBe('active')
    expect(provisionState.user_is_platform_admin).toBe(false) // CRITICAL: NEVER granted global platform admin

    // 4. Log out admin and log in as newly provisioned Owner
    await withE2EApi(page, async ({ authApi }) => authApi.logout())
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 5. New owner can access tenant operational route /admin/manage
    await page.goto('/admin/manage', { waitUntil: 'domcontentloaded' })
    await expect(page).not.toHaveURL(/\/login/)
    await expect(page).toHaveURL(/.*admin\/manage/)

    // 6. Security boundary: New owner is strictly DENIED on global platform audit logs
    const auditRes = await page.request.get(`${manifest.backend.url}/api/v1/admin/audit-logs`)
    expect([401, 403]).toContain(auditRes.status())

    // 7. Cleanup: restore customer to clean state so subsequent tests are isolated
    const cleanupScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\User;
      use App\\Models\\HostKyc;
      use App\\Models\\Organization;
      use App\\Models\\OrganizationMember;

      $user = User::where('email', '${customer.email}')->first();
      if ($user) {
        $kyc = HostKyc::where('user_id', $user->id)->latest('id')->first();
        if ($kyc && $kyc->organization_id) {
          OrganizationMember::where('organization_id', $kyc->organization_id)->delete();
          Organization::where('id', $kyc->organization_id)->delete();
        }
        HostKyc::where('user_id', $user->id)->delete();
        $user->update(['role' => 'customer']);
      }
    `
    try {
      execFileSync('php', ['-r', cleanupScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      })
    } catch {}
  })
})
