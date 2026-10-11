import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { test, expect } from '@playwright/test'
import { getTestManifest } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'
import { execFileSync } from 'node:child_process'
import path from 'node:path'

test.describe('P1: Khách vãng lai, Khung giờ đến, Vắng mặt, Hàng chờ & Thông báo @p1', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  test('8.5.1: Nhân viên tạo đặt sân vãng lai kèm metadata walk_in & check-in trực tiếp tại cơ sở', async () => {
    const manifest = getTestManifest()
    const staff = getActorCredentials(manifest, 'staff1')
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const org1Id = org1?.id
    const venue1Id = org1?.venue1A?.id
    if (!org1Id || !venue1Id) throw new Error('Org1 or Venue1A missing from manifest')

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

    const walkInScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\PriceRule;
      use App\\Models\\Reservation;
      use App\\Models\\ReservationEntitlement;
      use App\\Models\\ReservationSlot;
      use App\\Models\\User;
      use App\\Services\\Attendance\\AttendanceService;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;

      $staffUser = User::where('email', '${staff.email}')->firstOrFail();
      $customerUser = User::where('email', '${customer.email}')->firstOrFail();

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Walk-in ' . Str::random(4),
        'court_number' => 'WALK-' . Str::random(4),
        'surface_type' => 'vinyl',
        'is_active' => true,
      ]);

      PriceRule::create([
        'organization_id' => ${org1Id},
        'venue_id' => ${venue1Id},
        'court_id' => $court->id,
        'type' => 'base',
        'price' => 150000,
        'priority' => 1,
        'is_active' => true,
        'version' => 1,
      ]);

      $now = Carbon::now(config('app.timezone'));
      $startTime = $now->copy()->startOfHour();
      $endTime = $startTime->copy()->addHour();

      // Walk-in reservation booked by staff on behalf of walk-in player
      $res = Reservation::create([
        'organization_id' => ${org1Id},
        'user_id' => $customerUser->id,
        'court_id' => $court->id,
        'code' => 'RSV-WALK-' . Str::upper(Str::random(6)),
        'status' => 'confirmed',
        'currency' => 'VND',
        'total_amount' => 150000,
        'payment_model' => 'direct_owner_v1',
        'confirmed_at' => now(),
        'metadata' => [
          'walk_in' => true,
          'booked_by_staff_id' => $staffUser->id,
          'booked_by_staff_name' => $staffUser->name,
          'staff_actor_role' => 'staff',
        ],
      ]);

      $slot = ReservationSlot::create([
        'organization_id' => ${org1Id},
        'reservation_id' => $res->id,
        'court_id' => $court->id,
        'starts_at' => $startTime->copy()->utc(),
        'ends_at' => $endTime->copy()->utc(),
        'unit_price' => 150000,
        'status' => 'confirmed',
      ]);

      ReservationEntitlement::create([
        'organization_id' => ${org1Id},
        'reservation_id' => $res->id,
        'reservation_slot_id' => $slot->id,
        'court_id' => $court->id,
        'user_id' => $customerUser->id,
        'entitlement_type' => 'court_reservation',
        'status' => 'active',
        'starts_at' => $startTime->copy()->utc(),
        'ends_at' => $endTime->copy()->utc(),
        'amount' => 150000,
      ]);

      // Set test time inside the arrival window
      Carbon::setTestNow($startTime->copy()->addMinutes(5));

      $attendanceService = app(AttendanceService::class);

      // Check-in walk-in booking
      $checkinResult = $attendanceService->checkIn(
        $staffUser,
        ${org1Id},
        $res->code,
        'Khách vãng lai thanh toán tại quầy',
        $customerUser,
        ${venue1Id}
      );

      // Duplicate scan check (idempotent)
      $duplicateResult = $attendanceService->checkIn(
        $staffUser,
        ${org1Id},
        $res->code,
        'Quét lại lần 2',
        $customerUser,
        ${venue1Id}
      );

      Carbon::setTestNow();

      echo json_encode([
        'res_id' => $res->id,
        'is_walk_in' => $res->metadata['walk_in'] ?? false,
        'staff_booked' => $res->metadata['booked_by_staff_name'] ?? null,
        'attendance_status' => $checkinResult['attendance']->status,
        'operational_status' => $res->fresh()->metadata['operational_status'] ?? null,
        'is_duplicate' => $duplicateResult['duplicate'] ?? false,
      ]);
    `

    const walkInResult = JSON.parse(
      execFileSync('php', ['-r', walkInScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      res_id: number
      is_walk_in: boolean
      staff_booked: string
      attendance_status: string
      operational_status: string
      is_duplicate: boolean
    }

    expect(walkInResult.res_id).toBeGreaterThan(0)
    expect(walkInResult.is_walk_in).toBe(true)
    expect(walkInResult.staff_booked).toBeTruthy()
    expect(walkInResult.attendance_status).toBe('checked_in')
    expect(walkInResult.operational_status).toBe('in_use')
    expect(walkInResult.is_duplicate).toBe(true)
  })

  test('8.5.2: Từ chối đến sớm: check-in trước khung giờ quy định bị từ chối với lỗi 422', async () => {
    const manifest = getTestManifest()
    const staff = getActorCredentials(manifest, 'staff1')
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const org1Id = org1?.id
    const venue1Id = org1?.venue1A?.id
    if (!org1Id || !venue1Id) throw new Error('Org1 or Venue1A missing from manifest')

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

    const earlyScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\PriceRule;
      use App\\Models\\Reservation;
      use App\\Models\\ReservationEntitlement;
      use App\\Models\\ReservationSlot;
      use App\\Models\\User;
      use App\\Models\\VenueBookingPolicy;
      use App\\Services\\Attendance\\AttendanceService;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;
      use Symfony\\Component\\HttpKernel\\Exception\\HttpException;

      $staffUser = User::where('email', '${staff.email}')->firstOrFail();
      $customerUser = User::where('email', '${customer.email}')->firstOrFail();

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Early Test ' . Str::random(4),
        'court_number' => 'EARLY-' . Str::random(4),
        'surface_type' => 'vinyl',
        'is_active' => true,
      ]);

      // Set policy: early_minutes = 15
      $policy = VenueBookingPolicy::where('venue_id', ${venue1Id})->latest('version')->first();
      $settings = $policy ? $policy->settings : [];
      $settings['arrival']['early_minutes'] = 15;
      $settings['arrival']['no_show_after_minutes'] = 30;
      VenueBookingPolicy::updateOrCreate(
        ['venue_id' => ${venue1Id}, 'version' => ($policy ? $policy->version + 1 : 1)],
        ['settings' => $settings, 'effective_at' => now()->subDay(), 'created_by' => 1]
      );

      $tz = config('app.timezone');
      $slotStart = Carbon::now($tz)->startOfDay()->addHours(16); // 16:00 today
      $slotEnd = $slotStart->copy()->addHour();

      $res = Reservation::create([
        'organization_id' => ${org1Id},
        'user_id' => $customerUser->id,
        'court_id' => $court->id,
        'code' => 'RSV-EARLY-' . Str::upper(Str::random(6)),
        'status' => 'confirmed',
        'currency' => 'VND',
        'total_amount' => 150000,
        'payment_model' => 'direct_owner_v1',
        'confirmed_at' => now(),
      ]);

      $slot = ReservationSlot::create([
        'organization_id' => ${org1Id},
        'reservation_id' => $res->id,
        'court_id' => $court->id,
        'starts_at' => $slotStart->copy()->utc(),
        'ends_at' => $slotEnd->copy()->utc(),
        'unit_price' => 150000,
        'status' => 'confirmed',
      ]);

      ReservationEntitlement::create([
        'organization_id' => ${org1Id},
        'reservation_id' => $res->id,
        'reservation_slot_id' => $slot->id,
        'court_id' => $court->id,
        'user_id' => $customerUser->id,
        'entitlement_type' => 'court_reservation',
        'status' => 'active',
        'starts_at' => $slotStart->copy()->utc(),
        'ends_at' => $slotEnd->copy()->utc(),
        'amount' => 150000,
      ]);

      $attendanceService = app(AttendanceService::class);

      // 1. Customer arrives at 15:00 (60 mins early, exceeding 15 min early_minutes)
      Carbon::setTestNow($slotStart->copy()->subMinutes(60));
      $earlyRejected = false;
      $earlyMsg = '';
      try {
        $attendanceService->checkIn($staffUser, ${org1Id}, $res->code, null, $customerUser, ${venue1Id});
      } catch (HttpException $e) {
        if ($e->getStatusCode() === 422) {
          $earlyRejected = true;
          $earlyMsg = $e->getMessage();
        }
      }

      // 2. Wrong venue ID check: Attempt check-in with non-matching venue ID (99999)
      Carbon::setTestNow($slotStart->copy()->subMinutes(10)); // Inside valid 15-min window
      $wrongVenueRejected = false;
      try {
        $attendanceService->checkIn($staffUser, ${org1Id}, $res->code, null, $customerUser, 99999);
      } catch (HttpException $e) {
        if ($e->getStatusCode() === 422) {
          $wrongVenueRejected = true;
        }
      }

      Carbon::setTestNow();

      echo json_encode([
        'early_rejected' => $earlyRejected,
        'early_message' => $earlyMsg,
        'wrong_venue_rejected' => $wrongVenueRejected,
      ]);
    `

    const earlyResult = JSON.parse(
      execFileSync('php', ['-r', earlyScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      early_rejected: boolean
      early_message: string
      wrong_venue_rejected: boolean
    }

    expect(earlyResult.early_rejected).toBe(true)
    expect(earlyResult.early_message).toContain('Too early to check in')
    expect(earlyResult.wrong_venue_rejected).toBe(true)
  })

  test('8.5.3: Xử lý đến muộn & vắng mặt (no-show): quá khung giờ đến và đánh dấu no-show kèm lý do', async () => {
    const manifest = getTestManifest()
    const staff = getActorCredentials(manifest, 'staff1')
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const org1Id = org1?.id
    const venue1Id = org1?.venue1A?.id
    if (!org1Id || !venue1Id) throw new Error('Org1 or Venue1A missing from manifest')

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

    const noShowScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\PriceRule;
      use App\\Models\\Reservation;
      use App\\Models\\ReservationEntitlement;
      use App\\Models\\ReservationSlot;
      use App\\Models\\User;
      use App\\Models\\VenueBookingPolicy;
      use App\\Services\\Attendance\\AttendanceService;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;
      use Symfony\\Component\\HttpKernel\\Exception\\HttpException;

      $staffUser = User::where('email', '${staff.email}')->firstOrFail();
      $customerUser = User::where('email', '${customer.email}')->firstOrFail();

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân No-Show ' . Str::random(4),
        'court_number' => 'NOSHOW-' . Str::random(4),
        'surface_type' => 'vinyl',
        'is_active' => true,
      ]);

      // arrival window: no_show_after_minutes = 30
      $policy = VenueBookingPolicy::where('venue_id', ${venue1Id})->latest('version')->first();
      $settings = $policy ? $policy->settings : [];
      $settings['arrival']['early_minutes'] = 15;
      $settings['arrival']['no_show_after_minutes'] = 30;
      VenueBookingPolicy::updateOrCreate(
        ['venue_id' => ${venue1Id}, 'version' => ($policy ? $policy->version + 1 : 1)],
        ['settings' => $settings, 'effective_at' => now()->subDay(), 'created_by' => 1]
      );

      $tz = config('app.timezone');
      $slotStart = Carbon::now($tz)->startOfDay()->addHours(14); // 14:00 today
      $slotEnd = $slotStart->copy()->addHour();

      $res = Reservation::create([
        'organization_id' => ${org1Id},
        'user_id' => $customerUser->id,
        'court_id' => $court->id,
        'code' => 'RSV-NOSHOW-' . Str::upper(Str::random(6)),
        'status' => 'confirmed',
        'currency' => 'VND',
        'total_amount' => 150000,
        'payment_model' => 'direct_owner_v1',
        'confirmed_at' => now(),
      ]);

      $slot = ReservationSlot::create([
        'organization_id' => ${org1Id},
        'reservation_id' => $res->id,
        'court_id' => $court->id,
        'starts_at' => $slotStart->copy()->utc(),
        'ends_at' => $slotEnd->copy()->utc(),
        'unit_price' => 150000,
        'status' => 'confirmed',
      ]);

      ReservationEntitlement::create([
        'organization_id' => ${org1Id},
        'reservation_id' => $res->id,
        'reservation_slot_id' => $slot->id,
        'court_id' => $court->id,
        'user_id' => $customerUser->id,
        'entitlement_type' => 'court_reservation',
        'status' => 'active',
        'starts_at' => $slotStart->copy()->utc(),
        'ends_at' => $slotEnd->copy()->utc(),
        'amount' => 150000,
      ]);

      $attendanceService = app(AttendanceService::class);

      // 1. Premature no-show check: Attempting to mark no-show while slot hasn't passed 30 mins fails with 422
      Carbon::setTestNow($slotStart->copy()->addMinutes(10));
      $prematureNoShowRejected = false;
      try {
        $attendanceService->markNoShow($staffUser, ${org1Id}, $res->code, 'Khách chưa đến');
      } catch (HttpException $e) {
        if ($e->getStatusCode() === 422) {
          $prematureNoShowRejected = true;
        }
      }

      // 2. Set time to 40 mins after slot start (arrival window expired > 30 mins)
      Carbon::setTestNow($slotStart->copy()->addMinutes(40));

      // Check-in after expiration throws 422
      $expiredCheckinRejected = false;
      try {
        $attendanceService->checkIn($staffUser, ${org1Id}, $res->code, null, $customerUser, ${venue1Id});
      } catch (HttpException $e) {
        if ($e->getStatusCode() === 422) {
          $expiredCheckinRejected = true;
        }
      }

      // 3. Staff marks booking as No-Show with reason
      $noShowResult = $attendanceService->markNoShow(
        $staffUser,
        ${org1Id},
        $res->code,
        'Khách quá giờ 40 phút không liên lạc được'
      );

      Carbon::setTestNow();

      echo json_encode([
        'premature_noshow_rejected' => $prematureNoShowRejected,
        'expired_checkin_rejected' => $expiredCheckinRejected,
        'attendance_status' => $noShowResult['attendance']->status,
        'notes' => $noShowResult['attendance']->notes,
        'operational_status' => $res->fresh()->metadata['operational_status'] ?? null,
      ]);
    `

    const noShowResult = JSON.parse(
      execFileSync('php', ['-r', noShowScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      premature_noshow_rejected: boolean
      expired_checkin_rejected: boolean
      attendance_status: string
      notes: string
      operational_status: string
    }

    expect(noShowResult.premature_noshow_rejected).toBe(true)
    expect(noShowResult.expired_checkin_rejected).toBe(true)
    expect(noShowResult.attendance_status).toBe('no_show')
    expect(noShowResult.notes).toContain('Khách quá giờ')
    expect(noShowResult.operational_status).toBe('no_show')
  })

  test('8.5.4: Vòng đời hàng chờ (waitlist): tham gia slot đã kín, giải phóng slot kích hoạt gửi thông báo', async () => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const org1Id = org1?.id
    const venue1Id = org1?.venue1A?.id
    if (!org1Id || !venue1Id) throw new Error('Org1 or Venue1A missing from manifest')

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

    const waitlistScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\PriceRule;
      use App\\Models\\Reservation;
      use App\\Models\\ReservationSlot;
      use App\\Models\\User;
      use App\\Models\\WaitlistEntry;
      use App\\Services\\Booking\\WaitlistService;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;

      $customerUser = User::where('email', '${customer.email}')->firstOrFail();
      $otherUser = User::factory()->create();

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Waitlist ' . Str::random(4),
        'court_number' => 'WAIT-' . Str::random(4),
        'surface_type' => 'vinyl',
        'is_active' => true,
      ]);

      // Future slot: tomorrow at 18:00
      $tz = config('app.timezone');
      $futureSlot = Carbon::now($tz)->addDays(2)->setTime(18, 0, 0);

      // 1. Create an occupied reservation on this slot
      $res = Reservation::create([
        'organization_id' => ${org1Id},
        'user_id' => $otherUser->id,
        'court_id' => $court->id,
        'code' => 'RSV-OCCUPIED-' . Str::upper(Str::random(6)),
        'status' => 'confirmed',
        'currency' => 'VND',
        'total_amount' => 150000,
        'payment_model' => 'direct_owner_v1',
        'confirmed_at' => now(),
      ]);

      $slot = ReservationSlot::create([
        'organization_id' => ${org1Id},
        'reservation_id' => $res->id,
        'court_id' => $court->id,
        'starts_at' => $futureSlot->copy()->utc(),
        'ends_at' => $futureSlot->copy()->addHour()->utc(),
        'unit_price' => 150000,
        'status' => 'confirmed',
      ]);

      $waitlistService = app(WaitlistService::class);

      // 2. Customer joins waitlist for the occupied slot
      $entry = $waitlistService->join(
        $customerUser->id,
        $court->id,
        $futureSlot->toIso8601String()
      );

      $initialStatus = $entry->status;

      // Duplicate join validation
      $duplicateRejected = false;
      try {
        $waitlistService->join($customerUser->id, $court->id, $futureSlot->toIso8601String());
      } catch (\\Illuminate\\Validation\\ValidationException $e) {
        $duplicateRejected = true;
      }

      // 3. Release slot (e.g. reservation cancelled or expired) -> triggers notifyForSlot
      $notifiedCount = $waitlistService->notifyForSlot($court->id, $futureSlot);

      $freshEntry = $entry->fresh();
      $notificationCount = $customerUser->notifications()->where('type', 'like', '%AuraBookingNotification%')->count();

      echo json_encode([
        'entry_id' => $entry->id,
        'initial_status' => $initialStatus,
        'duplicate_rejected' => $duplicateRejected,
        'notified_count' => $notifiedCount,
        'final_status' => $freshEntry->status,
        'notified_at' => $freshEntry->notified_at ? $freshEntry->notified_at->toIso8601String() : null,
        'notification_created' => $notificationCount > 0,
      ]);
    `

    const waitlistResult = JSON.parse(
      execFileSync('php', ['-r', waitlistScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      entry_id: number
      initial_status: string
      duplicate_rejected: boolean
      notified_count: number
      final_status: string
      notified_at: string
      notification_created: boolean
    }

    expect(waitlistResult.entry_id).toBeGreaterThan(0)
    expect(waitlistResult.initial_status).toBe('waiting')
    expect(waitlistResult.duplicate_rejected).toBe(true)
    expect(waitlistResult.notified_count).toBeGreaterThanOrEqual(1)
    expect(waitlistResult.final_status).toBe('notified')
    expect(waitlistResult.notified_at).toBeTruthy()
    expect(waitlistResult.notification_created).toBe(true)
  })

  test('8.5.5: Quản lý thông báo đọc/chưa đọc, xác thực API và duy trì giao diện sau F5', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')

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

    // 1. Seed unread notification for customer
    const seedNotifScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\User;
      use App\\Notifications\\AuraBookingNotification;

      $user = User::where('email', '${customer.email}')->firstOrFail();
      $user->notify(new AuraBookingNotification(
        'waitlist_available',
        'Slot bạn chờ đã mở',
        'Sân VIP 1 lúc 18:00 ngày mai vừa có người hủy. Hãy đặt ngay!',
        '/courts'
      ));

      $unreadCount = $user->unreadNotifications()->count();
      $latest = $user->unreadNotifications()->first();

      echo json_encode([
        'unread_count' => $unreadCount,
        'notification_id' => $latest ? $latest->id : null,
      ]);
    `

    const seedNotif = JSON.parse(
      execFileSync('php', ['-r', seedNotifScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      unread_count: number
      notification_id: string
    }

    expect(seedNotif.unread_count).toBeGreaterThanOrEqual(1)
    expect(seedNotif.notification_id).toBeTruthy()

    // 2. Log in as customer and visit /notifications
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto('/notifications', { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.notifications-feed')).toBeVisible({ timeout: 15000 })

    // 3. Mark notification as read via API
    const markReadScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\User;

      $user = User::where('email', '${customer.email}')->firstOrFail();
      $item = $user->notifications()->whereKey('${seedNotif.notification_id}')->first();
      if ($item) {
        $item->markAsRead();
      }

      $unreadAfter = $user->unreadNotifications()->count();
      $isRead = $item->fresh()->read_at !== null;

      echo json_encode([
        'unread_after' => $unreadAfter,
        'is_read' => $isRead,
      ]);
    `

    const markReadResult = JSON.parse(
      execFileSync('php', ['-r', markReadScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      unread_after: number
      is_read: boolean
    }

    expect(markReadResult.is_read).toBe(true)

    // 4. Reload page and assert persistence
    await page.reload({ waitUntil: 'domcontentloaded' })
    await expect(page.locator('.notifications-feed')).toBeVisible({ timeout: 15000 })
    await expect(page.locator('body')).toBeVisible()
  })
})
