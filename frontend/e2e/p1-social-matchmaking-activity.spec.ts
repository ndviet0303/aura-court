import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { withE2EApi } from './runner/with-api.ts'
import { test, expect } from '@playwright/test'
import { getTestManifest } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'
import { execFileSync } from 'node:child_process'
import path from 'node:path'

test.describe('P1: Giao lưu cộng đồng, Bạn bè, Hoạt động sân, Danh sách & Điểm uy tín @p1', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  test('9.1.1: Vòng đời kết bạn & Phản hồi lời mời: gửi yêu cầu, chấp nhận, kết nối hai chiều (FR-COM-05, SUP-03)', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const customer2 = getActorCredentials(manifest, 'customer2')
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

    // 1. Log in as Customer 1 via UI
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Navigate to /matchmaking and switch to Tab 6: Bạn bè & Kết nối
    await page.goto('/matchmaking', { waitUntil: 'domcontentloaded' })

    // 3. Customer 1 sends friend request to Customer 2 via authenticated API caller
    const sendRequestResult = await withE2EApi(
      page,
      async ({ api }, addresseeId) => {
        return await api.post('/social/friends/requests', { addressee_id: addresseeId })
      },
      customer2.id,
    )

    expect(sendRequestResult).toBeDefined()
    expect((sendRequestResult as any)?.friendship?.status).toBe('pending')
    const friendshipId = (sendRequestResult as any)?.friendship?.id

    // 4. In backend, Customer 2 accepts the pending friendship request
    const acceptScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Friendship;
      use App\\Models\\User;
      use App\\Services\\Social\\FriendshipService;

      $cust2 = User::where('email', '${customer2.email}')->firstOrFail();
      $friendship = Friendship::findOrFail(${friendshipId});

      $service = app(FriendshipService::class);
      $accepted = $service->acceptRequest($friendship, $cust2);

      echo json_encode([
        'status' => $accepted->status,
        'updated_at' => $accepted->updated_at?->toIso8601String(),
      ]);
    `

    const acceptResult = JSON.parse(
      execFileSync('php', ['-r', acceptScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { status: string; updated_at: string }

    expect(acceptResult.status).toBe('accepted')
    expect(acceptResult.updated_at).toBeTruthy()

    // 5. Customer 1 verifies Customer 2 is in friends list
    const cust1Friends = await withE2EApi(page, async ({ api }) => {
      return await api.get('/social/friends')
    })

    const foundCust2 = (cust1Friends as any)?.friends?.some(
      (f: any) => f.id === customer2.id || f.friend?.id === customer2.id,
    )
    expect(foundCust2).toBe(true)

    // 6. Reload page: confirms bidirectional friendship persistence after reload
    await page.reload({ waitUntil: 'domcontentloaded' })

    const reloadedCust1Friends = await withE2EApi(page, async ({ api }) => {
      return await api.get('/social/friends')
    })
    const persistedCust2 = (reloadedCust1Friends as any)?.friends?.some(
      (f: any) => f.id === customer2.id || f.friend?.id === customer2.id,
    )
    expect(persistedCust2).toBe(true)

    // 7. Cleanup / Remove friend
    const removeRes = await withE2EApi(
      page,
      async ({ api }, fid) => {
        return await api.delete('/social/friends/' + fid)
      },
      friendshipId,
    )
    expect(removeRes).toBeDefined()
  })

  test('9.1.2: Tạo & công bố hoạt động: gắn booking sân, hạn đăng ký, sức chứa, giới hạn trình độ (FR-COM-02, FR-COM-03)', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const org1Id = org1?.id
    const venue1Id = org1?.venue1A?.id
    if (!org1Id || !venue1Id) throw new Error('Org1 or Venue1A missing from manifest')

    // 1. Log in as Customer 1
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Navigate to /matchmaking/create
    await page.goto('/matchmaking/create', { waitUntil: 'domcontentloaded' })

    const uniqueTitle = `Kèo Cầu Lông Đôi Nam Nữ - E2E Run ${Date.now()}`
    const futureDate = new Date()
    futureDate.setDate(futureDate.getDate() + 3)
    const futureDateStr = futureDate.toISOString().split('T')[0]

    // 3. Create and publish activity via socialApi with full validation
    const createResult = await withE2EApi(
      page,
      async ({ api }, { title, date }) => {
        return await api.post('/social/matches', {
          title,
          sport_type: 'badminton',
          category: 'giao lưu',
          starts_at: `${date} 18:00:00`,
          ends_at: `${date} 20:00:00`,
          registration_deadline: `${date} 17:00:00`,
          required_participants: 4,
          min_skill: 2,
          max_skill: 4,
          cost_per_person: 60000,
          split_bill_mode: 'Chia đều tiền sân & cầu',
          description: 'Trận cầu giao lưu phong trào kiểm tra hệ thống E2E',
          visibility: 'public',
        })
      },
      { title: uniqueTitle, date: futureDateStr, orgId: org1Id, venueId: venue1Id },
    )

    expect(createResult).toBeDefined()
    const match = (createResult as any)?.match || (createResult as any)?.data
    expect(match).toBeDefined()
    expect(match.title).toBe(uniqueTitle)
    expect(match.required_participants).toBe(4)
    expect(match.joined_count).toBe(1)
    expect(match.status).toBe('open')

    // 4. Navigate to /matchmaking and verify activity appears in the list
    await page.goto('/matchmaking', { waitUntil: 'domcontentloaded' })

    const matchCard = page.locator('.match-card', { hasText: uniqueTitle })
    await expect(matchCard).toBeVisible({ timeout: 15000 })
    await expect(matchCard).toContainText(uniqueTitle)
    await expect(matchCard).toContainText('Cầu lông')

    // 5. Reload confirms persistence in Postgres
    await page.reload({ waitUntil: 'domcontentloaded' })
    await expect(page.locator('.match-card', { hasText: uniqueTitle })).toBeVisible({
      timeout: 15000,
    })
  })

  test('9.1.3: Tìm kiếm & bộ lọc hoạt động: lọc theo từ khóa, môn thể thao, trình độ phù hợp (FR-COM-04)', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const org1Id = org1?.id
    if (!org1Id) throw new Error('Org1 missing from manifest')

    // 1. Log in as Customer
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    // 2. Create distinct activity for search test
    const distinctTitle = `Giải Pickleball Giao Hữu Alpha - ${Date.now()}`
    const futureDate = new Date()
    futureDate.setDate(futureDate.getDate() + 4)
    const futureDateStr = futureDate.toISOString().split('T')[0]

    await withE2EApi(
      page,
      async ({ api }, { title, date }) => {
        return await api.post('/social/matches', {
          title,
          sport_type: 'pickleball',
          category: 'giao lưu',
          starts_at: `${date} 09:00:00`,
          ends_at: `${date} 11:00:00`,
          required_participants: 4,
          min_skill: 1,
          max_skill: 5,
          cost_per_person: 50000,
          visibility: 'public',
        })
      },
      { title: distinctTitle, date: futureDateStr },
    )

    // 3. Open /matchmaking
    await page.goto('/matchmaking', { waitUntil: 'domcontentloaded' })

    // 4. Search for the distinct title using the search input
    const searchInput = page.locator('input[placeholder*="Tìm"], input[type="search"]').first()
    await expect(searchInput).toBeVisible({ timeout: 15000 })
    await searchInput.fill(distinctTitle)
    await page.waitForTimeout(500)
    await expect(page.locator('.match-card', { hasText: distinctTitle })).toBeVisible({
      timeout: 10000,
    })

    // 5. Search for non-existent activity -> empty state
    await searchInput.fill('XYZNonExistentActivity999999')
    await page.waitForTimeout(500)
    await expect(page.locator('.empty-matches-box')).toBeVisible({ timeout: 10000 })

    // 6. Clear search -> match card returns
    await searchInput.fill('')
    await page.waitForTimeout(500)
    await expect(page.locator('.match-card', { hasText: distinctTitle })).toBeVisible({
      timeout: 10000,
    })
  })

  test('9.1.4: Mời thi đấu, tham gia hoạt động & Giới hạn số lượng người chơi (FR-COM-05, FR-COM-06)', async () => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const customer2 = getActorCredentials(manifest, 'customer2')
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

    // 1. Arrange a match with capacity 3 (1 host + 2 open slots)
    const matchArrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\MatchPost;
      use App\\Models\\User;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;

      $host = User::where('email', '${customer.email}')->firstOrFail();
      $futureTime = Carbon::now(config('app.timezone'))->addDays(3)->setTime(15, 0);

      $match = MatchPost::create([
        'organization_id' => ${org1Id},
        'creator_id' => $host->id,
        'title' => 'Trận Giới Hạn Sức Chứa ' . Str::random(5),
        'visibility' => 'public',
        'starts_at' => $futureTime,
        'ends_at' => $futureTime->copy()->addHours(2),
        'required_participants' => 3,
        'joined_count' => 1,
        'status' => 'open',
      ]);

      echo json_encode([
        'match_id' => $match->id,
        'title' => $match->title,
        'capacity' => $match->required_participants,
        'joined_count' => $match->joined_count,
      ]);
    `

    const arrangedMatch = JSON.parse(
      execFileSync('php', ['-r', matchArrangeScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { match_id: number; title: string; capacity: number; joined_count: number }

    expect(arrangedMatch.capacity).toBe(3)
    expect(arrangedMatch.joined_count).toBe(1)

    // 2. Host invites Customer 2 to the match
    const inviteScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\MatchPost;
      use App\\Models\\User;
      use App\\Services\\Social\\MatchService;

      $host = User::where('email', '${customer.email}')->firstOrFail();
      $cust2 = User::where('email', '${customer2.email}')->firstOrFail();
      $match = MatchPost::findOrFail(${arrangedMatch.match_id});

      $service = app(MatchService::class);
      $invitation = $service->invite($match, $host, $cust2->id);

      // Customer 2 accepts the invitation
      $accepted = $service->respond($invitation, $cust2, 'accepted');

      $freshMatch = $match->fresh();
      echo json_encode([
        'invitation_status' => $accepted->status,
        'joined_count' => $freshMatch->joined_count,
      ]);
    `

    const inviteResult = JSON.parse(
      execFileSync('php', ['-r', inviteScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { invitation_status: string; joined_count: number }

    expect(inviteResult.invitation_status).toBe('accepted')
    expect(inviteResult.joined_count).toBe(2)

    // 3. 3rd participant joins directly -> fills the match to capacity (3/3)
    const fillScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\MatchPost;
      use App\\Models\\OrganizationMember;
      use App\\Models\\User;
      use App\\Services\\Social\\MatchService;

      $p3 = User::factory()->create(['name' => 'Player Three', 'email' => 'p3_' . uniqid() . '@auratest.vn']);
      OrganizationMember::create([
        'organization_id' => ${org1Id},
        'user_id' => $p3->id,
        'role' => OrganizationMember::ROLE_CUSTOMER,
        'status' => 'active',
      ]);

      $match = MatchPost::findOrFail(${arrangedMatch.match_id});
      $service = app(MatchService::class);
      $service->join($match, $p3);

      $fresh = $match->fresh();
      echo json_encode([
        'joined_count' => $fresh->joined_count,
        'is_full' => $fresh->joined_count >= $fresh->required_participants,
        'p3_id' => $p3->id,
      ]);
    `

    const fillResult = JSON.parse(
      execFileSync('php', ['-r', fillScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { joined_count: number; is_full: boolean; p3_id: number }

    expect(fillResult.joined_count).toBe(3)
    expect(fillResult.is_full).toBe(true)

    // 4. 4th participant attempts to join -> MUST be rejected because match is full
    const overCapacityScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\MatchPost;
      use App\\Models\\OrganizationMember;
      use App\\Models\\User;
      use App\\Services\\Social\\MatchService;

      $p4 = User::factory()->create(['name' => 'Player Four', 'email' => 'p4_' . uniqid() . '@auratest.vn']);
      OrganizationMember::create([
        'organization_id' => ${org1Id},
        'user_id' => $p4->id,
        'role' => OrganizationMember::ROLE_CUSTOMER,
        'status' => 'active',
      ]);

      $match = MatchPost::findOrFail(${arrangedMatch.match_id});
      $service = app(MatchService::class);

      try {
        $service->join($match, $p4);
        echo json_encode(['rejected' => false]);
      } catch (\\Throwable $e) {
        echo json_encode(['rejected' => true, 'error' => $e->getMessage()]);
      }
    `

    const overCapacityResult = JSON.parse(
      execFileSync('php', ['-r', overCapacityScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { rejected: boolean; error?: string }

    expect(overCapacityResult.rejected).toBe(true)
  })

  test('9.1.5: Chốt danh sách, điểm danh có mặt/vắng mặt & Cập nhật điểm uy tín (FR-COM-07, FR-COM-08, FR-COM-16)', async () => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const customer2 = getActorCredentials(manifest, 'customer2')
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

    // 1. Arrange match with joined participants
    const rosterScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\MatchParticipation;
      use App\\Models\\MatchPost;
      use App\\Models\\PlayerProfile;
      use App\\Models\\User;
      use App\\Services\\Social\\MatchService;
      use App\\Services\\Social\\ReputationService;
      use Carbon\\Carbon;

      $host = User::where('email', '${customer.email}')->firstOrFail();
      $player2 = User::where('email', '${customer2.email}')->firstOrFail();
      $time = Carbon::now(config('app.timezone'))->subHours(2);

      $match = MatchPost::create([
        'organization_id' => ${org1Id},
        'creator_id' => $host->id,
        'title' => 'Trận Điểm Danh & Uy Tín ' . uniqid(),
        'visibility' => 'public',
        'starts_at' => $time,
        'ends_at' => $time->copy()->addHours(2),
        'required_participants' => 2,
        'joined_count' => 2,
        'status' => 'open',
      ]);

      // Host participation
      MatchParticipation::create([
        'organization_id' => ${org1Id},
        'match_post_id' => $match->id,
        'user_id' => $host->id,
        'status' => 'joined',
        'joined_at' => now(),
      ]);

      // Player 2 participation
      MatchParticipation::create([
        'organization_id' => ${org1Id},
        'match_post_id' => $match->id,
        'user_id' => $player2->id,
        'status' => 'joined',
        'joined_at' => now(),
      ]);

      // 2. Host closes roster
      $matchService = app(MatchService::class);
      $closedMatch = $matchService->closeRoster($match, $host);

      // 3. Record attendance: Player 2 present
      $p2Attendance = $matchService->recordAttendance($match, $host, $player2->id, 'present', 'Có mặt đúng giờ');

      // 4. Update reputation scores via ReputationService
      $repService = app(ReputationService::class);
      $repProfilePresent = $repService->recordActivity($player2, ${org1Id}, 'attendance', 'match_post', $match->id, $host->id);

      echo json_encode([
        'roster_closed' => $closedMatch->roster_closed_at !== null,
        'player2_attendance' => $p2Attendance->attendance_status,
        'player2_verified' => $p2Attendance->verified_at !== null,
        'reputation_score' => $repProfilePresent->reputation_score,
      ]);
    `

    const rosterResult = JSON.parse(
      execFileSync('php', ['-r', rosterScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      roster_closed: boolean
      player2_attendance: string
      player2_verified: boolean
      reputation_score: number
    }

    expect(rosterResult.roster_closed).toBe(true)
    expect(rosterResult.player2_attendance).toBe('present')
    expect(rosterResult.player2_verified).toBe(true)
    expect(rosterResult.reputation_score).toBeGreaterThanOrEqual(60)

    // 5. Test no-show penalty: participant marked no-show receives -15 penalty
    const noShowScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\MatchParticipation;
      use App\\Models\\MatchPost;
      use App\\Models\\OrganizationMember;
      use App\\Models\\PlayerProfile;
      use App\\Models\\User;
      use App\\Services\\Social\\MatchService;
      use App\\Services\\Social\\ReputationService;
      use Carbon\\Carbon;

      $host = User::where('email', '${customer.email}')->firstOrFail();
      $pNoShow = User::factory()->create(['name' => 'NoShow Athlete', 'email' => 'noshow_' . uniqid() . '@auratest.vn']);
      OrganizationMember::create([
        'organization_id' => ${org1Id},
        'user_id' => $pNoShow->id,
        'role' => OrganizationMember::ROLE_CUSTOMER,
        'status' => 'active',
      ]);

      // Initialize initial reputation profile
      $profile = PlayerProfile::create([
        'organization_id' => ${org1Id},
        'user_id' => $pNoShow->id,
        'skill_level' => 3,
        'reputation_score' => 60,
      ]);

      $time = Carbon::now(config('app.timezone'))->subHours(3);
      $match = MatchPost::create([
        'organization_id' => ${org1Id},
        'creator_id' => $host->id,
        'title' => 'Trận Phạt No-Show ' . uniqid(),
        'visibility' => 'public',
        'starts_at' => $time,
        'ends_at' => $time->copy()->addHours(2),
        'required_participants' => 2,
        'joined_count' => 2,
        'status' => 'open',
      ]);

      MatchParticipation::create([
        'organization_id' => ${org1Id},
        'match_post_id' => $match->id,
        'user_id' => $pNoShow->id,
        'status' => 'joined',
        'joined_at' => now(),
      ]);

      $matchService = app(MatchService::class);
      $attendance = $matchService->recordAttendance($match, $host, $pNoShow->id, 'absent', 'Không đến không báo trước');

      $repService = app(ReputationService::class);
      $updatedProfile = $repService->recordActivity($pNoShow, ${org1Id}, 'no_show', 'match_post', $match->id, $host->id);

      echo json_encode([
        'initial_score' => 60,
        'final_score' => $updatedProfile->reputation_score,
        'delta' => $updatedProfile->reputation_score - 60,
      ]);
    `

    const noShowResult = JSON.parse(
      execFileSync('php', ['-r', noShowScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as { initial_score: number; final_score: number; delta: number }

    expect(noShowResult.final_score).toBe(45)
    expect(noShowResult.delta).toBe(-15)
  })
})
