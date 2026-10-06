import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { test, expect } from '@playwright/test'
import { getTestManifest } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'
import { execFileSync } from 'node:child_process'
import path from 'node:path'

test.describe('P1: Giải đấu trọn gói, Sức chứa, Phân quyền trọng tài & Khóa kết quả @p1', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  test('9.4.1: Tạo giải đấu, đăng ký & Tranh chấp vị trí cuối cùng khi gần hết chỗ (FR-COM-09, FR-COM-10)', async () => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const customer1 = getActorCredentials(manifest, 'customer')
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

    const capacityScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Tournament;
      use App\\Models\\User;
      use App\\Services\\Tournament\\TournamentService;

      $host = User::where('email', '${owner.email}')->firstOrFail();
      $p1 = User::where('email', '${customer1.email}')->firstOrFail();
      $p2 = User::where('email', '${customer2.email}')->firstOrFail();
      $p3 = User::factory()->create(['name' => 'Player Three', 'email' => 'p3_' . uniqid() . '@auratest.vn']);

      $tourney = Tournament::create([
        'organization_id' => ${org1Id},
        'created_by' => $host->id,
        'name' => 'Giải Đấu 2 Người Giới Hạn Sức Chứa ' . uniqid(),
        'format' => 'single_elimination',
        'status' => 'open',
        'settings' => [
          'entry_fee' => '0 ₫',
          'max_participants' => 2,
        ],
      ]);

      $service = app(TournamentService::class);

      // Player 1 registers for slot 1
      $part1 = $service->register($tourney, $p1);

      // Player 2 registers for the LAST PLACE (slot 2/2)
      $part2 = $service->register($tourney, $p2);

      // Player 3 attempts to register when capacity reached -> MUST throw 409 conflict
      $p3Rejected = false;
      try {
        $service->register($tourney, $p3);
      } catch (\\Throwable $e) {
        $p3Rejected = true;
      }

      echo json_encode([
        'tourney_id' => $tourney->id,
        'capacity' => 2,
        'p1_registered' => $part1 !== null,
        'p2_registered' => $part2 !== null,
        'p3_rejected' => $p3Rejected,
        'total_participants' => $tourney->participants()->count(),
      ]);
    `

    const capacityResult = JSON.parse(execFileSync('php', ['-r', capacityScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as {
      tourney_id: number
      capacity: number
      p1_registered: boolean
      p2_registered: boolean
      p3_rejected: boolean
      total_participants: number
    }

    expect(capacityResult.p1_registered).toBe(true)
    expect(capacityResult.p2_registered).toBe(true)
    expect(capacityResult.p3_rejected).toBe(true)
    expect(capacityResult.total_participants).toBe(2)
  })

  test('9.4.2: Điểm danh giải đấu & Phân quyền trọng tài theo phạm vi giải (FR-COM-12)', async () => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const staff = getActorCredentials(manifest, 'staff1')
    const customer1 = getActorCredentials(manifest, 'customer')
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

    const checkInScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Tournament;
      use App\\Models\\TournamentGrant;
      use App\\Models\\User;
      use App\\Services\\Tournament\\TournamentService;

      $host = User::where('email', '${owner.email}')->firstOrFail();
      $official = User::where('email', '${staff.email}')->firstOrFail();
      $player = User::where('email', '${customer1.email}')->firstOrFail();
      $stranger = User::factory()->create(['name' => 'Stranger Athlete', 'email' => 'stranger_' . uniqid() . '@auratest.vn']);

      $tourney = Tournament::create([
        'organization_id' => ${org1Id},
        'created_by' => $host->id,
        'name' => 'Giải Điểm Danh VĐV ' . uniqid(),
        'format' => 'single_elimination',
        'status' => 'open',
        'settings' => ['entry_fee' => '0 ₫', 'max_participants' => 4],
      ]);

      $service = app(TournamentService::class);
      $part = $service->register($tourney, $player);
      $participant = $part['participant'];

      // Grant official role to staff for this tournament
      TournamentGrant::create([
        'organization_id' => ${org1Id},
        'tournament_id' => $tourney->id,
        'user_id' => $official->id,
        'role' => 'official',
        'granted_by' => $host->id,
      ]);

      // 1. Official checks in player as 'present'
      $checkedPart = $service->checkIn($participant, $official, 'present');

      // 2. Stranger attempts check-in -> MUST throw 403
      $strangerDenied = false;
      try {
        $service->checkIn($participant, $stranger, 'absent');
      } catch (\\Throwable $e) {
        $strangerDenied = true;
      }

      echo json_encode([
        'participant_status' => $checkedPart->status,
        'stranger_denied' => $strangerDenied,
      ]);
    `

    const checkInResult = JSON.parse(execFileSync('php', ['-r', checkInScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as { participant_status: string; stranger_denied: boolean }

    expect(checkInResult.participant_status).toBe('present')
    expect(checkInResult.stranger_denied).toBe(true)
  })

  test('9.4.3: Bốc thăm nhánh đấu, phân quyền nhập điểm & Bảo vệ sửa đổi kết quả (FR-COM-11, FR-COM-13, FR-COM-14)', async () => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const staff = getActorCredentials(manifest, 'staff1')
    const customer1 = getActorCredentials(manifest, 'customer')
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

    const bracketScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Tournament;
      use App\\Models\\TournamentGrant;
      use App\\Models\\User;
      use App\\Services\\Tournament\\TournamentService;

      $host = User::where('email', '${owner.email}')->firstOrFail();
      $official = User::where('email', '${staff.email}')->firstOrFail();
      $p1 = User::where('email', '${customer1.email}')->firstOrFail();
      $p2 = User::where('email', '${customer2.email}')->firstOrFail();
      $p3 = User::factory()->create(['name' => 'Player 3', 'email' => 'p3_' . uniqid() . '@auratest.vn']);
      $unauthorized = User::factory()->create(['name' => 'Unauthorized Player', 'email' => 'unauth_' . uniqid() . '@auratest.vn']);

      $service = app(TournamentService::class);
      $tourney = $service->createAndGenerate(
        $host,
        ${org1Id},
        'Giải Thi Đấu Nhánh Đơn 3 VĐV ' . uniqid(),
        'single_elimination',
        [$p1->id, $p2->id, $p3->id]
      );

      // Deterministic bye check for Seed 1 (Player 1)
      $fixtures = $tourney->fixtures()->orderBy('round')->orderBy('position')->get();
      $r1f1 = $fixtures->where('round', 1)->where('position', 1)->first(); // Seed 1 bye
      $r1f2 = $fixtures->where('round', 1)->where('position', 2)->first(); // Seed 2 vs Seed 3
      $r2f1 = $fixtures->where('round', 2)->where('position', 1)->first(); // Final

      $seed1HasBye = ($r1f1->status === 'bye');
      $seed1AdvancedToFinal = ($r2f1->participant_one_id === $r1f1->winner_id);

      // Grant official role to staff
      TournamentGrant::create([
        'organization_id' => ${org1Id},
        'tournament_id' => $tourney->id,
        'user_id' => $official->id,
        'role' => 'official',
        'granted_by' => $host->id,
      ]);

      // 1. Unauthorized user is rejected by official authorization check
      $unauthDenied = ! $tourney->isAuthorizedOfficial($unauthorized);
      $officialAllowed = $tourney->isAuthorizedOfficial($official);

      // 2. Official submits score for Round 1 match: Player 2 wins over Player 3
      $scored = $service->submitScore($tourney, $r1f2, $official, $tourney->version, ['p1' => 21, 'p2' => 15], $r1f2->participant_one_id);

      // Winner (Player 2) automatically advances to Final (Round 2 Fixture 1 slot 2)!
      $winnerAdvancedToFinal = ($r2f1->fresh()->participant_two_id === $r1f2->participant_one_id);

      $tourney = $tourney->fresh();
      $r1f2 = $r1f2->fresh();

      // 3. Attempt score correction without reason -> MUST throw 422
      $correctionWithoutReasonDenied = false;
      try {
        $service->submitScore($tourney, $r1f2, $official, $tourney->version, ['p1' => 15, 'p2' => 21], $r1f2->participant_two_id);
      } catch (\\Throwable $e) {
        $correctionWithoutReasonDenied = true;
      }

      $tourney = $tourney->fresh();
      $r1f2 = $r1f2->fresh();

      // 4. Correction with valid reason succeeds and updates downstream slot 2 to Player 3!
      $corrected = $service->submitScore($tourney, $r1f2, $official, $tourney->version, ['p1' => 15, 'p2' => 21], $r1f2->participant_two_id, 'Trọng tài ghi nhầm thứ tự điểm số của hai bên');
      $downstreamUpdated = ($r2f1->fresh()->participant_two_id === $r1f2->participant_two_id);

      echo json_encode([
        'seed1_has_bye' => $seed1HasBye,
        'seed1_advanced_to_final' => $seed1AdvancedToFinal,
        'unauth_denied' => $unauthDenied,
        'first_winner' => $scored->winner_id,
        'winner_advanced_to_final' => $winnerAdvancedToFinal,
        'no_reason_denied' => $correctionWithoutReasonDenied,
        'corrected_winner' => $corrected->winner_id,
        'downstream_updated' => $downstreamUpdated,
      ]);
    `

    const bracketResult = JSON.parse(execFileSync('php', ['-r', bracketScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as {
      seed1_has_bye: boolean
      seed1_advanced_to_final: boolean
      unauth_denied: boolean
      first_winner: number
      winner_advanced_to_final: boolean
      no_reason_denied: boolean
      corrected_winner: number
      downstream_updated: boolean
    }

    expect(bracketResult.seed1_has_bye).toBe(true)
    expect(bracketResult.seed1_advanced_to_final).toBe(true)
    expect(bracketResult.unauth_denied).toBe(true)
    expect(bracketResult.winner_advanced_to_final).toBe(true)
    expect(bracketResult.no_reason_denied).toBe(true)
    expect(bracketResult.downstream_updated).toBe(true)
  })

  test('9.4.4: Hoàn tất & Khóa bất biến: cấm tuyệt đối chỉnh sửa sau khi đã chốt giải (FR-COM-15)', async () => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const customer1 = getActorCredentials(manifest, 'customer')
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

    const finalizeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Tournament;
      use App\\Models\\User;
      use App\\Services\\Tournament\\TournamentService;

      $host = User::where('email', '${owner.email}')->firstOrFail();
      $p1 = User::where('email', '${customer1.email}')->firstOrFail();
      $p2 = User::where('email', '${customer2.email}')->firstOrFail();

      $service = app(TournamentService::class);
      $tourney = $service->createAndGenerate(
        $host,
        ${org1Id},
        'Giải Đấu Khóa Kết Quả ' . uniqid(),
        'single_elimination',
        [$p1->id, $p2->id]
      );

      $fixture = $tourney->fixtures()->first();

      // Complete match
      $service->submitScore($tourney, $fixture, $host, $tourney->version, ['p1' => 21, 'p2' => 18], $fixture->participant_one_id);

      // Finalize tournament
      $finalized = $service->finalize($tourney->fresh(), $host);

      // Attempt to modify score after finalization -> MUST throw 409 Conflict
      $scoreBlocked = false;
      try {
        $service->submitScore($tourney->fresh(), $fixture->fresh(), $host, $tourney->fresh()->version, ['p1' => 18, 'p2' => 21], $fixture->participant_two_id);
      } catch (\\Throwable $e) {
        $scoreBlocked = true;
      }

      // Attempt to regenerate bracket after finalization -> MUST throw 409 Conflict
      $regenBlocked = false;
      try {
        $service->regenerateBracket($tourney->fresh(), $host);
      } catch (\\Throwable $e) {
        $regenBlocked = true;
      }

      echo json_encode([
        'status' => $finalized->status,
        'score_blocked' => $scoreBlocked,
        'regen_blocked' => $regenBlocked,
      ]);
    `

    const finalizeResult = JSON.parse(execFileSync('php', ['-r', finalizeScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as {
      status: string
      score_blocked: boolean
      regen_blocked: boolean
    }

    expect(finalizeResult.status).toBe('finalized')
    expect(finalizeResult.score_blocked).toBe(true)
    expect(finalizeResult.regen_blocked).toBe(true)
  })

  test('9.4.5: Giao diện trình duyệt /tournaments và /tournaments/:id/bracket & Duy trì sau F5', async ({ page }) => {
    const manifest = getTestManifest()
    const owner = getActorCredentials(manifest, 'owner1')
    const customer1 = getActorCredentials(manifest, 'customer')
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

    // 1. Arrange a tournament with fixtures and finalization in Postgres
    const arrangeTourneyScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Tournament;
      use App\\Models\\User;
      use App\\Services\\Tournament\\TournamentService;

      $host = User::where('email', '${owner.email}')->firstOrFail();
      $p1 = User::where('email', '${customer1.email}')->firstOrFail();
      $p2 = User::where('email', '${customer2.email}')->firstOrFail();

      $tourneyName = 'Aura Championship E2E ' . uniqid();
      $service = app(TournamentService::class);
      $tourney = $service->createAndGenerate(
        $host,
        ${org1Id},
        $tourneyName,
        'single_elimination',
        [$p1->id, $p2->id]
      );

      $fixture = $tourney->fixtures()->first();
      $service->submitScore($tourney, $fixture, $host, $tourney->version, ['p1' => 21, 'p2' => 19], $fixture->participant_one_id);

      $service->finalize($tourney->fresh(), $host);

      echo json_encode([
        'tourney_id' => $tourney->id,
        'tourney_name' => $tourneyName,
      ]);
    `

    const tourneyData = JSON.parse(execFileSync('php', ['-r', arrangeTourneyScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    })) as { tourney_id: number; tourney_name: string }

    // 2. Customer navigates to /tournaments
    await page.context().clearCookies()
    await page.goto('/tournaments', { waitUntil: 'domcontentloaded' })

    // 3. Verify tournament card appears in the list
    const card = page.locator('.tournament-card', { hasText: tourneyData.tourney_name })
    await expect(card).toBeVisible({ timeout: 15000 })
    await expect(card).toContainText(tourneyData.tourney_name)

    // 4. Open /tournaments/:id/bracket
    await page.goto(`/tournaments/${tourneyData.tourney_id}/bracket`, { waitUntil: 'domcontentloaded' })

    // 5. Verify bracket header and matches render correctly
    await expect(page.locator('.bracket-header-view, .tournament-bracket-view')).toBeVisible({ timeout: 15000 })
    await expect(page.locator('text=' + tourneyData.tourney_name).first()).toBeVisible({ timeout: 15000 })

    // 6. Reload confirms durable persistence from Postgres database
    await page.reload({ waitUntil: 'domcontentloaded' })
    await expect(page.locator('text=' + tourneyData.tourney_name).first()).toBeVisible({ timeout: 15000 })
  })
})
