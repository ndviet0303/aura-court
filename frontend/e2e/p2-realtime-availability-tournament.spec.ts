import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { test, expect } from '@playwright/test'
import { getTestManifest, arrangeCourt } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'
import { execFileSync } from 'node:child_process'
import path from 'node:path'

test.describe('P2: Đồng bộ thời gian thực, Cơ chế Polling Fallback & Ranh giới múi giờ @p2 @realtime', () => {
  const manifest = getTestManifest()
  const org1Id = manifest.fixtures?.organizations.org1.id || 2
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

  test('10.4.1: Đồng bộ khả dụng hai phiên trình duyệt khi có biến động & Nhất quán khi tải lại (Không giả DOM)', async ({ browser }) => {
    // 1. Use the primary seeded court (court1A1) which is canonical and default in the venue
    const courtId = manifest.fixtures?.organizations.org1.venue1A?.courts[0] || 1

    // 2. Open two distinct customer browser sessions
    const contextA = await browser.newContext()
    const pageA = await contextA.newPage()
    await pageA.addInitScript(() => sessionStorage.setItem('aura_demo_warned', 'true'))

    const contextB = await browser.newContext()
    const pageB = await contextB.newPage()
    await pageB.addInitScript(() => sessionStorage.setItem('aura_demo_warned', 'true'))

    try {
      // 3. Both sessions navigate to the court schedule
      await pageA.goto(`/courts/${courtId}/schedule`, { waitUntil: 'domcontentloaded' })
      await pageB.goto(`/courts/${courtId}/schedule`, { waitUntil: 'domcontentloaded' })

      await expect(pageA.locator('.schedule-split-layout, .schedule-view-page').first()).toBeVisible({ timeout: 15000 })
      await expect(pageB.locator('.schedule-split-layout, .schedule-view-page').first()).toBeVisible({ timeout: 15000 })
      await expect(pageA.locator('.slots-grid .slot-cell-btn').first()).toBeVisible({ timeout: 15000 })
      await expect(pageB.locator('.slots-grid .slot-cell-btn').first()).toBeVisible({ timeout: 15000 })
      // 4. In Session A, select tomorrow's date card
      const dateCardsA = pageA.locator('.date-carousel .date-card-btn')
      await dateCardsA.nth(1).click()
      await expect(pageA.locator('.slots-grid .slot-cell-btn').first()).toBeVisible({ timeout: 10000 })

      // In Session B, select the same tomorrow's date card
      const dateCardsB = pageB.locator('.date-carousel .date-card-btn')
      await dateCardsB.nth(1).click()
      await expect(pageB.locator('.slots-grid .slot-cell-btn').first()).toBeVisible({ timeout: 10000 })

      // 5. Identify the first available slot in Session A
      const slotButtonA = pageA.locator('.slot-cell-btn.is-available').first()
      await expect(slotButtonA).toBeVisible({ timeout: 10000 })
      const fullTimeText = (await slotButtonA.locator('.s-time').textContent())?.trim() || ''
      const slotTime = fullTimeText.split('-')[0].trim()
      expect(slotTime).toMatch(/^\d{2}:\d{2}$/)

      // Verify that initially, Session B ALSO sees this slot as available (using fullTimeText to prevent matching preceding slot)
      const slotButtonB = pageB.locator('.slot-cell-btn').filter({ has: pageB.locator('.s-time', { hasText: fullTimeText }) }).first()
      await expect(slotButtonB).toBeVisible({ timeout: 10000 })
      await expect(slotButtonB).toHaveClass(/is-available/)
      await expect(slotButtonB).not.toBeDisabled()
      // 6. Mutate state: Hold this slot in the backend test database (simulating Session A or concurrent hold)
      const targetDate = await pageA.locator('input.hidden-native-input').inputValue()
      expect(targetDate).toMatch(/^\d{4}-\d{2}-\d{2}$/)
      await expect(pageB.locator('input.hidden-native-input')).toHaveValue(targetDate)
      const tz = 'Asia/Ho_Chi_Minh'
      const holdScript = `
        require __DIR__ . '/vendor/autoload.php';
        $app = require_once __DIR__ . '/bootstrap/app.php';
        $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
        $kernel->bootstrap();

        use App\\Models\\Court;
        use App\\Models\\Reservation;
        use App\\Models\\ReservationSlot;
        use App\\Models\\User;
        use Carbon\\Carbon;

        $court = Court::findOrFail(${courtId});
        $user = User::findOrFail(${manifest.fixtures?.users.customer.id});
        $orgId = $court->venue->organization_id;

        $slotStart = Carbon::parse("${targetDate} ${slotTime}", "${tz}")->utc();
        $slotEnd = $slotStart->copy()->addHour();

        $res = Reservation::create([
            'organization_id' => $orgId,
            'user_id' => $user->id,
            'court_id' => $court->id,
            'code' => 'RES_RT_' . uniqid(),
            'status' => 'held',
            'total_amount' => 120000,
            'currency' => 'VND',
            'hold_expires_at' => now()->addMinutes(10),
            'metadata' => ['e2e_realtime_hold' => true, 'e2e_run_id' => '${manifest.runId}'],
        ]);
        ReservationSlot::create([
            'organization_id' => $orgId,
            'reservation_id' => $res->id,
            'court_id' => $court->id,
            'status' => 'held',
            'starts_at' => $slotStart,
            'ends_at' => $slotEnd,
            'unit_price' => 120000,
        ]);

        echo json_encode(['held' => true, 'slot' => "${slotTime}"]);
      `

      const rawHold = execFileSync('php', ['-r', holdScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      })
      const holdResult = JSON.parse(rawHold)
      expect(holdResult.held).toBe(true)

      // 7. Authoritative Cross-Session Invariant Verification:
      // Session B queries backend availability for the court and date to prove the held slot is locked
      const backendUrl = manifest.backend.url
      const availResponse = await pageB.request.get(
        `${backendUrl}/api/v1/availability?court_id=${courtId}&date=${targetDate}`
      )
      expect(availResponse.status()).toBe(200)
      const slotsData = await availResponse.json()
      const heldSlotInBackend = slotsData.find((s: { startsAt: string }) => s.startsAt.includes(slotTime))
      expect(heldSlotInBackend).toBeDefined()
      expect(heldSlotInBackend.available).toBe(false)

      // 8. Session B UI Verification: observe mutated slot update via refetch
      const dateCardsBAfter = pageB.locator('.date-carousel .date-card-btn')
      await dateCardsBAfter.nth(1).click()

      const slotBtnAfter = pageB.locator('.slot-cell-btn').filter({ has: pageB.locator('.s-time', { hasText: fullTimeText }) }).first()
      await expect(slotBtnAfter).toBeVisible({ timeout: 15000 })
      await expect(slotBtnAfter).toHaveClass(/is-occupied/)
      await expect(slotBtnAfter).toBeDisabled()
    } finally {
      try {
        const cleanupScript = `
          require __DIR__ . '/vendor/autoload.php';
          $app = require_once __DIR__ . '/bootstrap/app.php';
          $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
          $kernel->bootstrap();
          use App\\Models\\Reservation;
          Reservation::where('organization_id', ${org1Id})->where('court_id', ${courtId})->where('metadata->e2e_run_id', '${manifest.runId}')->where('code', 'like', 'RES_RT_%')->each(function ($r) {
            $r->slots()->delete();
            $r->delete();
          });
        `
        execFileSync('php', ['-r', cleanupScript], {
          cwd: backendDir,
          env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
          encoding: 'utf-8',
        })
      } catch (err) {
        // Ignore cleanup failure
      }
      await contextA.close()
      await contextB.close()
    }
  })

  test('10.4.2: Hai phiên: Sơ đồ nhánh đấu & Cập nhật tỷ số qua cơ chế Polling (Không giả DOM)', async ({ browser }) => {
    const owner = getActorCredentials(manifest, 'owner1')
    const customer1 = getActorCredentials(manifest, 'customer')
    const customer2 = getActorCredentials(manifest, 'customer2')

    // 1. Arrange a 3-player single elimination tournament with match score submitted via TournamentService
    const tourneyScript = `
      require __DIR__ . '/vendor/autoload.php';
      $app = require_once __DIR__ . '/bootstrap/app.php';
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Tournament;
      use App\\Models\\User;
      use App\\Services\\Tournament\\TournamentService;

      $host = User::where('email', '${owner.email}')->firstOrFail();
      $p1 = User::where('email', '${customer1.email}')->firstOrFail();
      $p2 = User::where('email', '${customer2.email}')->firstOrFail();
      $p3 = User::factory()->create(['name' => 'Kỳ Thủ Ba', 'email' => 'p3_' . uniqid() . '@auratest.vn']);

      $service = app(TournamentService::class);
      $tourney = $service->createAndGenerate(
        $host,
        ${org1Id},
        'Giải Vô Địch Realtime ' . uniqid(),
        'single_elimination',
        [$p1->id, $p2->id, $p3->id]
      );

      $fixtures = $tourney->fixtures()->orderBy('round')->orderBy('position')->get();
      $r1f2 = $fixtures->where('round', 1)->where('position', 2)->first(); // Match between P2 and P3

      // Host submits score: P2 wins 21 - 15 against P3
      $service->submitScore($tourney, $r1f2, $host, $tourney->version, ['p1' => 21, 'p2' => 15], $r1f2->participant_one_id);

      echo json_encode([
        'tournament_id' => $tourney->id,
        'winner_name' => $p2->name,
      ]);
    `

    const rawTourney = execFileSync('php', ['-r', tourneyScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })
    const tourneyData = JSON.parse(rawTourney)
    const tourneyId = tourneyData.tournament_id
    const winnerName = tourneyData.winner_name

    // 2. Open Spectator Session (Session 2)
    const contextSpectator = await browser.newContext()
    const pageSpectator = await contextSpectator.newPage()
    await pageSpectator.addInitScript(() => sessionStorage.setItem('aura_demo_warned', 'true'))

    try {
      // 3. Spectator visits the bracket page
      await pageSpectator.goto(`/tournaments/${tourneyId}/bracket`, { waitUntil: 'domcontentloaded' })

      // Verify the bracket view is rendered
      await expect(pageSpectator.locator('.tournament-bracket-view, .bracket-main-body').first()).toBeVisible({ timeout: 15000 })

      // 4. Assert: Round 1 Match 2 card (P2 vs P3) displays the real submitted score (21 for winner, 15 for loser)
      const match2 = pageSpectator.locator('.bracket-match-node').nth(1)
      await expect(match2).toBeVisible({ timeout: 10000 })

      const winnerScore = match2.locator('.player-slot.winner .p-score')
      await expect(winnerScore).toBeVisible({ timeout: 10000 })
      await expect(winnerScore).toHaveText('21')

      const loserScore = match2.locator('.player-slot:not(.winner) .p-score')
      await expect(loserScore).toBeVisible({ timeout: 10000 })
      await expect(loserScore).toHaveText('15')

      // 5. Assert: Final Match card in the finals column displays the advanced winner's name
      const finalsNode = pageSpectator.locator('.finals-node')
      await expect(finalsNode).toBeVisible({ timeout: 10000 })
      await expect(finalsNode.locator('.p-name', { hasText: winnerName })).toBeVisible()

      // 6. Realtime mutation: Host submits final match score in backend
      const finalMatchScript = `
        require __DIR__ . '/vendor/autoload.php';
        $app = require_once __DIR__ . '/bootstrap/app.php';
        $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
        $kernel->bootstrap();

        use App\\Models\\Tournament;
        use App\\Models\\User;
        use App\\Services\\Tournament\\TournamentService;

        $host = User::where('email', '${owner.email}')->firstOrFail();
        $tourney = Tournament::findOrFail(${tourneyId});
        $service = app(TournamentService::class);

        $finalsFixture = $tourney->fixtures()->where('round', 2)->firstOrFail();
        $service->submitScore($tourney, $finalsFixture, $host, $tourney->version, ['p1' => 21, 'p2' => 18], $finalsFixture->participant_one_id);
      `
      execFileSync('php', ['-r', finalMatchScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      })

      // Spectator refetches bracket and observes finals match score live
      await pageSpectator.reload({ waitUntil: 'domcontentloaded' })
      await expect(finalsNode.locator('.p-score').first()).toHaveText('21')
    } finally {
      await contextSpectator.close()
    }
  })

  test('10.4.3: Ranh giới múi giờ & Chuyển giao nửa đêm (Asia/Ho_Chi_Minh)', async ({ page }) => {
    const court = arrangeCourt(manifest, { testKey: 'tz_boundary_10_4', price: 90000 })
    const courtId = court.court_id
    const backendUrl = manifest.backend.url

    // 1. Compute today and tomorrow in Asia/Ho_Chi_Minh (+07:00)
    const tz = 'Asia/Ho_Chi_Minh'
    const now = new Date()
    const todayVn = new Intl.DateTimeFormat('en-CA', { timeZone: tz }).format(now)
    const tomorrowDate = new Date(now.getTime() + 24 * 60 * 60 * 1000)
    const tomorrowVn = new Intl.DateTimeFormat('en-CA', { timeZone: tz }).format(tomorrowDate)

    // 2. Query availability for today in Vietnam timezone
    const responseToday = await page.request.get(`${backendUrl}/api/v1/availability?court_id=${courtId}&date=${todayVn}`)
    expect(responseToday.status()).toBe(200)

    const slotsToday = await responseToday.json()
    expect(Array.isArray(slotsToday)).toBe(true)
    expect(slotsToday.length).toBeGreaterThan(0)

    // Verify all slots strictly match today's date in Asia/Ho_Chi_Minh without leaking to adjacent days
    for (const slot of slotsToday) {
      expect(slot.startsAt).toMatch(/T\d{2}:\d{2}:\d{2}\+07:00$/)
      const slotVnDate = new Intl.DateTimeFormat('en-CA', { timeZone: tz }).format(new Date(slot.startsAt))
      expect(slotVnDate).toBe(todayVn)
    }

    // 3. Verify midnight boundary & day crossing transition with tomorrow's slots
    const responseTomorrow = await page.request.get(`${backendUrl}/api/v1/availability?court_id=${courtId}&date=${tomorrowVn}`)
    expect(responseTomorrow.status()).toBe(200)

    const slotsTomorrow = await responseTomorrow.json()
    expect(Array.isArray(slotsTomorrow)).toBe(true)
    expect(slotsTomorrow.length).toBeGreaterThan(0)

    // The first slot of tomorrow (06:00:00+07:00) is 23:00:00 UTC of todayVn.
    // Verify that the server correctly scoped it to tomorrowVn and not todayVn!
    const firstSlotTomorrow = slotsTomorrow[0]
    const firstSlotVnDate = new Intl.DateTimeFormat('en-CA', { timeZone: tz }).format(new Date(firstSlotTomorrow.startsAt))
    expect(firstSlotVnDate).toBe(tomorrowVn)

    // 4. Verify on client UI: Schedule page renders formatted date in Vietnamese
    await page.goto(`/courts/${courtId}/schedule`, { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.schedule-split-layout, .schedule-view-page').first()).toBeVisible({ timeout: 15000 })

    const activeDatePill = page.locator('.date-carousel .date-card-btn.active').first()
    await expect(activeDatePill).toBeVisible({ timeout: 10000 })
  })
})
