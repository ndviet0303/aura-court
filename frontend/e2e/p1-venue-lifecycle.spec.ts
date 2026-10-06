import { arrangeCourt, executeGuardedBackendPhp, getCanonicalBackendEnv, getFuturePlayDate } from './runner/test-data.ts'
import { withE2EApi } from './runner/with-api.ts'
import { test, expect } from '@playwright/test'
import { getTestManifest } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'
import { execFileSync } from 'node:child_process'
import path from 'node:path'

function getBackendEnv(manifest: any) {
  return getCanonicalBackendEnv(manifest)
}

test.describe('P1: Vòng đời cơ sở, Điều kiện sẵn sàng, Hàng đợi công khai & Lịch ngoại lệ @p1', () => {
  test.beforeEach(async ({ page }) => {
    const manifest = getTestManifest()

    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  test('7.4.1: Tạo nháp cơ sở, cập nhật thông tin giới thiệu, hình ảnh và tiện ích', async ({ page }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const orgId = manifest.fixtures?.organizations.org1?.id
    if (!orgId) throw new Error('Org1 ID missing from manifest')

    // 1. Log in as owner
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Create draft venue via partner API
    const uniqueSlug = `venue-draft-${Date.now()}`
    const draftVenueRes = await withE2EApi(page, async ({ api }, { orgId, slug }) => {
      return await api.post(`/partner/organizations/${orgId}/venues`, {
        name: `Sân Cầu Lông Thử Nghiệm ${slug}`,
        address: '789 Đường Thể Thao Mới, Phường 1, Quận 3',
        city: 'Hồ Chí Minh',
        district: 'Quận 3',
        phone: '0901234567',
        description: 'Bản nháp cơ sở thể thao để kiểm tra quy trình xuất bản.',
      })
    }, { orgId, slug: uniqueSlug }) as { data: { id: number; name: string; status: string; is_active: boolean } }

    expect(draftVenueRes.data?.id).toBeDefined()
    expect(draftVenueRes.data?.status).toBe('draft')
    expect(draftVenueRes.data?.is_active).toBe(false)
    const venueId = draftVenueRes.data.id

    // 3. Update profile with amenities, images, and map url
    const updateRes = await withE2EApi(page, async ({ api }, { orgId, venueId }) => {
      return await api.put(`/partner/organizations/${orgId}/venues/${venueId}`, {
        name: 'Sân Cầu Lông Thử Nghiệm Hoàn Thiện',
        address: '789 Đường Thể Thao Mới, Phường 1, Quận 3, TP.HCM',
        description: 'Mô tả cập nhật đầy đủ tiện ích và hình ảnh thực tế.',
        amenities: ['wifi', 'parking', 'shower', 'canteen'],
        images: ['https://images.unsplash.com/photo-1541534741688-6078c6bfb5c5?auto=format&fit=crop&w=1200&q=80'],
        google_maps_url: 'https://maps.google.com/?q=10.7769,106.7009',
      })
    }, { orgId, venueId }) as { data: { id: number; amenities: string[]; images: string[] } }

    expect(updateRes.data.amenities).toContain('wifi')
    expect(updateRes.data.amenities).toContain('parking')
    expect(updateRes.data.images.length).toBeGreaterThan(0)
  })

  test('7.4.2: Đánh giá điều kiện sẵn sàng, thiết lập sân, giá, chính sách & tài khoản nhận tiền', async ({ page }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const orgId = manifest.fixtures?.organizations.org1?.id
    if (!orgId) throw new Error('Org1 ID missing from manifest')

    // 1. Log in as owner
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Create draft venue
    const uniqueSlug = `readiness-${Date.now()}`
    const draftVenueRes = await withE2EApi(page, async ({ api }, { orgId, slug }) => {
      return await api.post(`/partner/organizations/${orgId}/venues`, {
        name: `Sân Đủ Điều Kiện ${slug}`,
        address: '100 Đường Cộng Hòa, Phường 4, Quận Tân Bình',
        city: 'Hồ Chí Minh',
        district: 'Quận Tân Bình',
        phone: '0901112233',
      })
    }, { orgId, slug: uniqueSlug }) as { data: { id: number } }

    const venueId = draftVenueRes.data.id

    // 3. Check readiness initially: MUST fail with missing items
    const initialReadiness = await withE2EApi(page, async ({ api }, { orgId, venueId }) => {
      return await api.get(`/partner/organizations/${orgId}/venues/${venueId}/readiness`)
    }, { orgId, venueId }) as {
      data: {
        is_ready: boolean
        missing_items: Array<{ code: string; message: string }>
        checklist: Record<string, boolean>
      }
    }

    expect(initialReadiness.data.is_ready).toBe(false)
    expect(initialReadiness.data.missing_items.some(i => i.code === 'NO_ACTIVE_COURTS')).toBe(true)
    expect(initialReadiness.data.missing_items.some(i => i.code === 'BOOKING_POLICY_REQUIRED')).toBe(true)
    expect(initialReadiness.data.missing_items.some(i => i.code === 'OPERATING_CALENDAR_REQUIRED')).toBe(true)

    // 4. Configure Booking Policy
    const policyRes = await withE2EApi(page, async ({ api }, { orgId, venueId }) => {
      return await api.put(`/partner/organizations/${orgId}/venues/${venueId}/booking-policy`, {
        settings: {
          booking: {
            hold_ttl_minutes: 15,
            max_days_ahead: 30,
            min_duration_minutes: 60,
            max_duration_minutes: 180,
            require_continuity: true,
          },
          cancellation: {
            deadline_hours: 12,
            fee_type: 'none',
            fee_value: 0,
            refund_due_hours: 24,
          },
          change: {
            deadline_hours: 6,
            fee_type: 'none',
            fee_value: 0,
          },
          arrival: {
            early_minutes: 15,
            late_grace_minutes: 15,
            no_show_after_minutes: 30,
          },
          recurring: {
            payment_policy: 'full_series',
            conflict_policy: 'all_or_nothing',
            overdue_release_hours: 24,
            guarantee_confirm: true,
          },
          transfer: {
            cutoff_hours: 2,
            max_consideration_percent: 100,
            free_transfer_allowed: true,
            seller_reimbursement_required: false,
          },
          ancillary_credit_policy: 'refund',
        },
      })
    }, { orgId, venueId }) as { data: any }
    expect(policyRes.data).toBeDefined()

    // 5. Configure Operating Schedule (Daily 06:00 to 22:00)
    const scheduleRes = await withE2EApi(page, async ({ api }, { orgId, venueId }) => {
      const weeklyHours: Record<string, any> = {}
      for (let day = 1; day <= 7; day++) {
        weeklyHours[String(day)] = {
          closed: false,
          intervals: [{ start: '06:00', end: '22:00' }],
        }
      }
      return await api.put(`/partner/organizations/${orgId}/venues/${venueId}/operating-schedule`, {
        timezone: 'Asia/Ho_Chi_Minh',
        weekly_hours: weeklyHours,
      })
    }, { orgId, venueId }) as { data: any }
    expect(scheduleRes.data).toBeDefined()

    // 6. Seed active court with prices and verified receiving connection for this venue
    const backendDir = path.resolve(import.meta.dirname, '../../backend')
    const backendEnv = getBackendEnv(manifest)
    const seedCourtScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\CourtPriceSlot;
      use App\\Models\\ReceivingConnection;
      use App\\Models\\Venue;
      use Illuminate\\Support\\Facades\\Crypt;
      use Illuminate\\Support\\Str;
      $venue = Venue::findOrFail(${venueId});

      // 1. Create active court
      $court = Court::create([
        'venue_id' => $venue->id,
        'name' => ['vi' => 'Sân 1 VIP', 'en' => 'Court 1 VIP'],
        'description' => ['vi' => 'Mặt sân thi đấu BWF', 'en' => 'BWF standard'],
        'surface_type' => 'synthetic',
        'is_active' => true,
      ]);

      // 2. Add court price slots for all 7 weekdays
      for ($w = 0; $w <= 6; $w++) {
        CourtPriceSlot::create([
          'court_id' => $court->id,
          'weekday' => $w,
          'starts_at' => '06:00',
          'ends_at' => '22:00',
          'price' => 120000,
          'is_active' => true,
        ]);
      }

      // 3. Add verified receiving connection
      $rc = ReceivingConnection::create([
        'organization_id' => $venue->organization_id,
        'venue_id' => $venue->id,
        'owner_user_id' => ${owner.id || 1},
        'provider' => 'payos',
        'routing_id' => (string) Str::uuid(),
        'bank_code' => '970415',
        'account_name_ciphertext' => Crypt::encryptString('CTY TNHH AURA COURT'),
        'account_number_ciphertext' => Crypt::encryptString('1029384756'),
        'account_last_four' => '4756',
        'webhook_secret_ciphertext' => Crypt::encryptString('mock_wh_secret'),
        'api_token_ciphertext' => Crypt::encryptString('mock_api_token'),
        'credential_version' => 1,
        'owner_attested_at' => now(),
        'verified_at' => now(),
        'effective_from' => now()->subDay(),
        'effective_until' => now()->addYear(),
        'status' => 'verified',
        'is_selected' => true,
      ]);

      $venue->update(['current_receiving_connection_id' => $rc->id]);

      echo json_encode([
        'court_id' => $court->id,
        'rc_id' => $rc->id,
      ]);
    `

    const seedRes = JSON.parse(execFileSync('php', ['-r', seedCourtScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as { court_id: number; rc_id: number }

    expect(seedRes.court_id).toBeGreaterThan(0)

    // 7. Re-evaluate readiness: MUST now be is_ready: true
    const completeReadiness = await withE2EApi(page, async ({ api }, { orgId, venueId }) => {
      return await api.get(`/partner/organizations/${orgId}/venues/${venueId}/readiness`)
    }, { orgId, venueId }) as {
      data: {
        is_ready: boolean
        missing_items: any[]
        checklist: Record<string, boolean>
      }
    }

    expect(completeReadiness.data.is_ready).toBe(true)
    expect(completeReadiness.data.missing_items.length).toBe(0)
    expect(completeReadiness.data.checklist.active_courts).toBe(true)
    expect(completeReadiness.data.checklist.court_pricing).toBe(true)
    expect(completeReadiness.data.checklist.policies_configured).toBe(true)
    expect(completeReadiness.data.checklist.operating_calendar_configured).toBe(true)
  })

  test('7.4.3: Gửi duyệt công khai, chống gửi lặp & Admin xét duyệt đưa lên công khai', async ({ page }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const admin = getActorCredentials(manifest, 'admin')
    const orgId = manifest.fixtures?.organizations.org1?.id
    if (!orgId) throw new Error('Org1 ID missing from manifest')
    const uniqueSlug = `pub-test-${Date.now()}`
    // 1. Arrange ready draft venue via PHP script
    const backendDir = path.resolve(import.meta.dirname, '../../backend')
    const backendEnv = getBackendEnv(manifest)
    const arrangeVenueScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\CourtPriceSlot;
      use App\\Models\\ReceivingConnection;
      use App\\Models\\Venue;
      use App\\Models\\VenueBookingPolicy;
      use App\\Models\\VenueOperatingSchedule;
      use Illuminate\\Support\\Facades\\Crypt;
      use Illuminate\\Support\\Str;
      $venue = Venue::create([
        'organization_id' => ${orgId},
        'name' => 'Sân Xuất Bản ${uniqueSlug}',
        'slug' => '${uniqueSlug}',
        'address' => '456 Đường Lê Duẩn, Phường Bến Nghé, Quận 1',
        'city' => 'Hồ Chí Minh',
        'district' => 'Quận 1',
        'phone' => '0901998877',
        'status' => 'draft',
        'is_active' => false,
      ]);

      VenueBookingPolicy::create([
        'venue_id' => $venue->id,
        'version' => 1,
        'effective_at' => now(),
        'settings' => [
          'booking' => [
            'hold_ttl_minutes' => 15,
            'max_days_ahead' => 30,
            'min_duration_minutes' => 60,
            'max_duration_minutes' => 180,
            'require_continuity' => true,
          ],
          'cancellation' => [
            'deadline_hours' => 12,
            'fee_type' => 'none',
            'fee_value' => 0,
            'refund_due_hours' => 24,
          ],
          'change' => [
            'deadline_hours' => 6,
            'fee_type' => 'none',
            'fee_value' => 0,
          ],
          'arrival' => [
            'early_minutes' => 15,
            'late_grace_minutes' => 15,
            'no_show_after_minutes' => 30,
          ],
          'recurring' => [
            'payment_policy' => 'full_series',
            'conflict_policy' => 'all_or_nothing',
            'overdue_release_hours' => 24,
            'guarantee_confirm' => true,
          ],
          'transfer' => [
            'cutoff_hours' => 2,
            'max_consideration_percent' => 100,
            'free_transfer_allowed' => true,
            'seller_reimbursement_required' => false,
          ],
          'ancillary_credit_policy' => 'refund',
        ],
      ]);

      $weeklyHours = [];
      for ($d = 1; $d <= 7; $d++) {
        $weeklyHours[(string) $d] = [
          'closed' => false,
          'intervals' => [['start' => '06:00', 'end' => '22:00']],
        ];
      }
      VenueOperatingSchedule::create([
        'venue_id' => $venue->id,
        'scope_key' => 'venue',
        'timezone' => 'Asia/Ho_Chi_Minh',
        'weekly_hours' => $weeklyHours,
      ]);

      $court = Court::create([
        'venue_id' => $venue->id,
        'name' => ['vi' => 'Sân Đấu Chính', 'en' => 'Main Court'],
        'is_active' => true,
      ]);

      for ($w = 0; $w <= 6; $w++) {
        CourtPriceSlot::create([
          'court_id' => $court->id,
          'weekday' => $w,
          'starts_at' => '06:00',
          'ends_at' => '22:00',
          'price' => 150000,
          'is_active' => true,
        ]);
      }

      $rc = ReceivingConnection::create([
        'organization_id' => ${orgId},
        'venue_id' => $venue->id,
        'owner_user_id' => ${owner.id || 1},
        'provider' => 'payos',
        'routing_id' => (string) Str::uuid(),
        'bank_code' => '970415',
        'account_name_ciphertext' => Crypt::encryptString('CTY XUAT BAN'),
        'account_number_ciphertext' => Crypt::encryptString('9988776655'),
        'account_last_four' => '6655',
        'webhook_secret_ciphertext' => Crypt::encryptString('mock_secret'),
        'credential_version' => 1,
        'owner_attested_at' => now(),
        'verified_at' => now(),
        'effective_from' => now()->subDay(),
        'status' => 'verified',
        'is_selected' => true,
      ]);

      $venue->update(['current_receiving_connection_id' => $rc->id]);

      echo json_encode([
        'venue_id' => $venue->id,
        'venue_slug' => $venue->slug,
      ]);
    `

    const arranged = JSON.parse(execFileSync('php', ['-r', arrangeVenueScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as { venue_id: number; venue_slug: string }

    const venueId = arranged.venue_id
    const venueSlug = arranged.venue_slug

    // 2. Owner logs in and submits publication
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    const submitRes = await withE2EApi(page, async ({ api }, { orgId, venueId }) => {
      return await api.post(`/partner/organizations/${orgId}/venues/${venueId}/submit-publication`, {})
    }, { orgId, venueId }) as { data: { id: number; revision_number: number; status: string } }

    expect(submitRes.data?.id).toBeDefined()
    expect(submitRes.data?.status).toBe('pending')
    const revisionId = submitRes.data.id

    // 3. Duplicate submit guard: MUST return 409 Conflict
    const dupSubmitBlocked = await withE2EApi(page, async ({ api }, { orgId, venueId }) => {
      try {
        await api.post(`/partner/organizations/${orgId}/venues/${venueId}/submit-publication`, {})
        return false
      } catch (err: any) {
        return err.status === 409 || err.response?.status === 409
      }
    }, { orgId, venueId })

    expect(dupSubmitBlocked).toBe(true)

    // 4. Log out and log in as Platform Admin
    await withE2EApi(page, async ({ api }) => {
      try {
        await api.post('/auth/logout')
      } catch {}
      await page.evaluate(() => { localStorage.clear(); sessionStorage.clear() })
    })
    await page.context().clearCookies()

    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(admin.email)
    await page.locator('input[type="password"], input[name="password"]').fill(admin.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 5. Navigate to Admin Publication Queue UI
    await page.goto('/admin/venues/publication-queue', { waitUntil: 'domcontentloaded' })

    // 6. Admin approves publication revision
    const approveRes = await withE2EApi(page, async ({ api }, { revId }) => {
      return await api.post(`/admin/venues/revisions/${revId}/review`, {
        decision: 'approve',
        reason: 'Cơ sở đáp ứng đầy đủ tiêu chuẩn kiểm định cơ sở vật chất và an toàn.',
      })
    }, { revId: revisionId }) as { data: { status: string } }

    expect(approveRes.data?.status).toBe('approved')

    // 7. Verify venue is now public and accessible
    const publicVenueRes = await withE2EApi(page, async ({ api }, { slug }) => {
      return await api.get(`/venues/${slug}`)
    }, { slug: venueSlug }) as any

    const resolvedId = publicVenueRes?.data?.id ?? publicVenueRes?.id
    const resolvedActive = publicVenueRes?.data?.is_active ?? publicVenueRes?.is_active
    expect(resolvedId).toBe(venueId)
    expect(resolvedActive).toBe(true)

    // 8. Re-fetch publication revision from history to verify retained audit trail
    const historyRevision = await withE2EApi(page, async ({ api }, { revId }) => {
      return await api.get(`/admin/venues/revisions/${revId}`)
    }, { revId: revisionId }) as {
      data: {
        id: number
        venue_id: number
        revision_number: number
        status: string
        reviewed_by: number
        reviewed_at: string
        decision_reason: string
        configuration_snapshot: any
      }
    }

    expect(historyRevision.data?.id).toBe(revisionId)
    expect(historyRevision.data?.venue_id).toBe(venueId)
    expect(historyRevision.data?.revision_number).toBe(1)
    expect(historyRevision.data?.status).toBe('approved')
    expect(historyRevision.data?.reviewed_by).toBeDefined()
    expect(historyRevision.data?.reviewed_at).toBeDefined()
    expect(historyRevision.data?.decision_reason).toContain('tiêu chuẩn')
    expect(historyRevision.data?.configuration_snapshot).toBeDefined()
    expect(historyRevision.data?.configuration_snapshot?.courts?.length).toBeGreaterThan(0)
  })

  test('7.4.4: Lịch ngoại lệ (bảo trì / ngày nghỉ / đóng cửa) và mất & phục hồi trạng thái khả dụng', async ({ page }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const orgId = manifest.fixtures?.organizations.org1?.id
    const venue = manifest.fixtures?.organizations.org1?.venue1A
    if (!orgId || !venue?.id) throw new Error('Fixture missing from manifest')
    // A venue-wide closure needs its own venue: earlier scenarios reserve sibling courts.
    const venueId = Number(executeGuardedBackendPhp(manifest, `
      $source = App\\Models\\Venue::where('organization_id', ${orgId})->findOrFail(${venue.id});
      $venue = $source->replicate(['slug', 'current_receiving_connection_id']);
      $venue->slug = 'schedule-exception-' . Illuminate\\Support\\Str::uuid();
      $venue->name = 'Schedule exception ' . $venue->slug;
      $venue->save();
      $schedule = App\\Models\\VenueOperatingSchedule::where('venue_id', $source->id)->whereNull('court_id')->firstOrFail()->replicate();
      $schedule->venue_id = $venue->id;
      $schedule->save();
      echo $venue->id;
    `))
    const courtId = arrangeCourt(manifest, { testKey: 'schedule_exception', venueId }).court_id
    // 1. Clear previous session and log in as owner
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    const targetDateStr = getFuturePlayDate(12).dateString
    // 2. Query availability before exception: court has slots available
    const initialAvail = await withE2EApi(page, async ({ api }, { courtId, date }) => {
      return await api.get(`/availability`, {
        params: {
          court_id: courtId,
          date,
        },
      })
    }, { courtId, date: targetDateStr }) as Array<{ startsAt: string; endsAt: string; price: number; available: boolean }>

    expect(initialAvail?.length).toBeGreaterThan(0)
    const initialOpenSlots = initialAvail.filter(s => s.available).length
    expect(initialOpenSlots).toBeGreaterThan(0)
    // 3. Add schedule exception: Full day closure (Bảo trì cơ sở vật chất)
    const createExceptionRes = await withE2EApi(page, async ({ api }, { orgId, venueId, date }) => {
      try {
        return await api.put(`/partner/organizations/${orgId}/venues/${venueId}/schedule-exceptions`, {
          exception_date: date,
          name: 'Bảo trì hệ thống chiếu sáng và thảm sàn',
          type: 'closed',
          timezone: 'Asia/Ho_Chi_Minh',
        })
      } catch (err: any) {
        return { __error: true, error: err.response?.data || err.message || String(err), status: err.response?.status }
      }
    }, { orgId, venueId, date: targetDateStr }) as any

    if (createExceptionRes.__error) {
      throw new Error(`Schedule exception failed with status ${createExceptionRes.status}: ${JSON.stringify(createExceptionRes.error)}`)
    }
    expect(createExceptionRes.data?.id).toBeDefined()
    const exceptionId = createExceptionRes.data.id

    // 4. Query availability during exception: ALL slots MUST be closed/lost!
    const closedAvail = await withE2EApi(page, async ({ api }, { courtId, date }) => {
      return await api.get(`/availability`, {
        params: {
          court_id: courtId,
          date,
        },
      })
    }, { courtId, date: targetDateStr }) as Array<{ startsAt: string; endsAt: string; price: number; available: boolean }>

    const closedOpenSlots = closedAvail.filter(s => s.available).length
    expect(closedOpenSlots).toBe(0)
    // 5. Delete schedule exception: restoration of operational availability
    const deleteRes = await withE2EApi(page, async ({ api }, { orgId, venueId, excId }) => {
      return await api.delete(`/partner/organizations/${orgId}/venues/${venueId}/schedule-exceptions/${excId}`)
    }, { orgId, venueId, excId: exceptionId }) as { message: string }

    expect(deleteRes.message).toBeDefined()

    // 6. Query availability after exception removal: slots are restored!
    const restoredAvail = await withE2EApi(page, async ({ api }, { courtId, date }) => {
      return await api.get(`/availability`, {
        params: {
          court_id: courtId,
          date,
        },
      })
    }, { courtId, date: targetDateStr }) as Array<{ startsAt: string; endsAt: string; price: number; available: boolean }>

    const restoredOpenSlots = restoredAvail.filter(s => s.available).length
    expect(restoredOpenSlots).toBe(initialOpenSlots)
  })

  test('7.4.5: Bảo mật: Người không có quyền bị từ chối gửi duyệt và cơ sở chưa sẵn sàng bị chặn', async ({ page }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const orgId = manifest.fixtures?.organizations.org1?.id
    const venueId = manifest.fixtures?.organizations.org1?.venue1A?.id
    if (!orgId || !venueId) throw new Error('Fixture missing from manifest')
    // 1. Clear previous session cookies and log in as regular customer (non-manager, non-owner)
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.evaluate(() => { localStorage.clear(); sessionStorage.clear() }).catch(() => {})
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })
    // 2. Customer attempts to submit publication: 403 Forbidden
    const customerDenied = await withE2EApi(page, async ({ api }, { orgId, venueId }) => {
      try {
        await api.post(`/partner/organizations/${orgId}/venues/${venueId}/submit-publication`, {})
        return false
      } catch (err: any) {
        return err.status === 403 || err.response?.status === 403
      }
    }, { orgId, venueId })

    expect(customerDenied).toBe(true)

    // 3. Customer attempts to modify schedule exceptions: 403 Forbidden
    const excDenied = await withE2EApi(page, async ({ api }, { orgId, venueId }) => {
      try {
        await api.put(`/partner/organizations/${orgId}/venues/${venueId}/schedule-exceptions`, {
          exception_date: '2026-10-15',
          name: 'Unauthorized closure',
          type: 'closed',
          timezone: 'Asia/Ho_Chi_Minh',
        })
        return false
      } catch (err: any) {
        return err.status === 403 || err.response?.status === 403
      }
    }, { orgId, venueId })

    expect(excDenied).toBe(true)

    // 4. Authorized owner attempts to submit an unready draft venue: MUST fail closed with 422
    await withE2EApi(page, async ({ api }) => {
      try {
        await api.post('/auth/logout')
      } catch {}
      await page.evaluate(() => { localStorage.clear(); sessionStorage.clear() })
    })
    await page.context().clearCookies()

    const owner = getActorCredentials(manifest, 'owner1')
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(owner.email)
    await page.locator('input[type="password"], input[name="password"]').fill(owner.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // Create an unready draft venue (name and address only, missing courts, pricing, policies, schedule)
    const unreadySlug = `unready-draft-${Date.now()}`
    const unreadyDraftRes = await withE2EApi(page, async ({ api }, { orgId, slug }) => {
      return await api.post(`/partner/organizations/${orgId}/venues`, {
        name: `Sân Chưa Đủ Điều Kiện ${slug}`,
        address: '123 Đường Chưa Hoàn Thiện, Quận 7',
      })
    }, { orgId, slug: unreadySlug }) as { data: { id: number; status: string } }

    const unreadyVenueId = unreadyDraftRes.data.id

    // Attempt publication submit on unready venue:
    const unreadySubmitResult = await withE2EApi(page, async ({ api }, { orgId, venueId }) => {
      try {
        await api.post(`/partner/organizations/${orgId}/venues/${venueId}/submit-publication`, {})
        return { blocked: false, status: 200, missing: [] }
      } catch (err: any) {
        return {
          blocked: true,
          status: err.status || err.response?.status,
          missing: err.response?.data?.errors?.missing_items || err.response?.data?.missing_items || err.errors?.missing_items || err.missing_items || [],
        }
      }
    }, { orgId, venueId: unreadyVenueId })

    expect(unreadySubmitResult.blocked).toBe(true)
    expect(unreadySubmitResult.status).toBe(422)
    expect(unreadySubmitResult.missing.length).toBeGreaterThan(0)
  })
})
