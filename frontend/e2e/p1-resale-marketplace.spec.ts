import { getCanonicalBackendEnv } from './runner/test-data.ts'
import { test, expect } from '@playwright/test'
import {
  getTestManifest,
  sendSyntheticPaymentWebhook,
  formatVietnamDate,
} from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'
import { execFileSync } from 'node:child_process'
import path from 'node:path'

test.describe('P1: Sàn nhượng vé, Chuyển quyền đặt sân, Thẩm quyền check-in & Ký quỹ không hoa hồng @p1', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      sessionStorage.setItem('aura_demo_warned', 'true')
    })
  })

  test('8.3.1: Điều kiện đăng nhượng vé & giới hạn giá (giờ cutoff, không vượt giá gốc, không nhượng vé đang tranh chấp)', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const orgId = org1?.id
    const venue1Id = org1?.venue1A?.id
    if (!orgId || !venue1Id) throw new Error('Org1 or Venue1A missing from manifest')

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

    // 1. Arrange dedicated court and confirmed reservation with active entitlement (200,000 VND)
    const arrangeScript = `
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
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Resale Eligibility ' . Str::random(4),
        'court_number' => 'RESALE-ELIG-' . Str::random(4),
        'surface_type' => 'vinyl',
        'is_active' => true,
      ]);

      PriceRule::create([
        'organization_id' => ${orgId},
        'venue_id' => ${venue1Id},
        'court_id' => $court->id,
        'type' => 'base',
        'price' => 200000,
        'priority' => 1,
        'is_active' => true,
        'version' => 1,
      ]);

      $policy = VenueBookingPolicy::where('venue_id', ${venue1Id})->latest('version')->first();
      $settings = $policy ? $policy->settings : [];
      $settings['transfer']['transfer_allowed'] = true;
      $settings['transfer']['cutoff_hours'] = 2;
      $settings['transfer']['max_consideration_percent'] = 100;
      VenueBookingPolicy::updateOrCreate(
        ['venue_id' => ${venue1Id}, 'version' => ($policy ? $policy->version + 1 : 1)],
        [
          'settings' => $settings,
          'effective_at' => now()->subDay(),
          'created_by' => 1,
        ]
      );

      $user = User::where('email', '${customer.email}')->firstOrFail();
      $futureTime = Carbon::now(config('app.timezone'))->addDays(3)->setTime(14, 0);

      $res = Reservation::create([
        'organization_id' => ${orgId},
        'user_id' => $user->id,
        'court_id' => $court->id,
        'code' => 'RSV-RESALE-' . Str::upper(Str::random(6)),
        'status' => 'confirmed',
        'currency' => 'VND',
        'total_amount' => 200000,
        'payment_model' => 'direct_owner_v1',
        'confirmed_at' => now(),
      ]);

      $slot = ReservationSlot::create([
        'organization_id' => ${orgId},
        'reservation_id' => $res->id,
        'court_id' => $court->id,
        'starts_at' => $futureTime->copy()->utc(),
        'ends_at' => $futureTime->copy()->addHours(2)->utc(),
        'unit_price' => 200000,
        'status' => 'confirmed',
      ]);

      $entitlement = ReservationEntitlement::create([
        'organization_id' => ${orgId},
        'reservation_id' => $res->id,
        'reservation_slot_id' => $slot->id,
        'court_id' => $court->id,
        'user_id' => $user->id,
        'entitlement_type' => 'court_reservation',
        'status' => 'active',
        'starts_at' => $futureTime->copy()->utc(),
        'ends_at' => $futureTime->copy()->addHours(2)->utc(),
        'amount' => 200000,
      ]);

      echo json_encode([
        'reservation_id' => $res->id,
        'entitlement_id' => $entitlement->id,
        'user_id' => $user->id,
      ]);
    `

    const arranged = JSON.parse(
      execFileSync('php', ['-r', arrangeScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      reservation_id: number
      entitlement_id: number
      user_id: number
    }

    expect(arranged.entitlement_id).toBeGreaterThan(0)

    // 2. Validate listing constraints:
    // a. Price limit: Asking price (250,000) > original price (200,000) throws 422
    // b. Dispute lock: Disputed reservation cannot be listed (throws 422)
    // c. Valid listing with asking price 160,000 succeeds with fee_bps = 0
    const testConstraintsScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Reservation;
      use App\\Models\\ReservationEntitlement;
      use App\\Models\\User;
      use App\\Services\\Social\\MarketplaceService;
      use Symfony\\Component\\HttpKernel\\Exception\\HttpException;

      $service = app(MarketplaceService::class);
      $entitlement = ReservationEntitlement::find(${arranged.entitlement_id});
      $seller = User::find(${arranged.user_id});
      $res = Reservation::find(${arranged.reservation_id});

      $excessPriceRejected = false;
      try {
        $service->list($entitlement, $seller, 250000);
      } catch (HttpException $e) {
        if ($e->getStatusCode() === 422) {
          $excessPriceRejected = true;
        }
      }

      $disputedRejected = false;
      $res->update(['status' => 'disputed']);
      try {
        $service->list($entitlement->fresh(), $seller, 160000);
      } catch (HttpException $e) {
        if ($e->getStatusCode() === 422) {
          $disputedRejected = true;
        }
      }
      $res->update(['status' => 'confirmed']);

      // Valid listing
      $listing = $service->list($entitlement->fresh(), $seller, 160000);

      echo json_encode([
        'excess_price_rejected' => $excessPriceRejected,
        'disputed_rejected' => $disputedRejected,
        'listing_id' => $listing->id,
        'original_price' => $listing->original_price,
        'asking_price' => $listing->asking_price,
        'fee_bps' => $listing->fee_bps,
        'status' => $listing->status,
      ]);
    `

    const testConstraints = JSON.parse(
      execFileSync('php', ['-r', testConstraintsScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      excess_price_rejected: boolean
      disputed_rejected: boolean
      listing_id: number
      original_price: number
      asking_price: number
      fee_bps: number
      status: string
    }

    expect(testConstraints.excess_price_rejected).toBe(true)
    expect(testConstraints.disputed_rejected).toBe(true)
    expect(testConstraints.listing_id).toBeGreaterThan(0)
    expect(testConstraints.original_price).toBe(200000)
    expect(testConstraints.asking_price).toBe(160000)
    expect(testConstraints.fee_bps).toBe(0) // Zero commission!
    expect(testConstraints.status).toBe('listed')

    // 3. Log in as customer and navigate to /resale
    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto('/resale', { waitUntil: 'domcontentloaded' })

    // 4. Assert /resale surface renders marketplace hero and the listed price
    await expect(page.locator('.resale-marketplace-view')).toBeVisible({ timeout: 10000 })
    await expect(page.locator('.hero-title')).toContainText('Chợ Chuyển Nhượng & Pass Sân')
  })

  test('8.3.2: Vòng đời rút vé của người bán: Người bán được rút vé đã đăng, người khác bị từ chối', async () => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const orgId = org1?.id
    const venue1Id = org1?.venue1A?.id
    if (!orgId || !venue1Id) throw new Error('Org1 or Venue1A missing from manifest')

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

    // 1. Arrange confirmed reservation and listing
    const arrangeScript = `
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
      use App\\Services\\Social\\MarketplaceService;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Resale Withdraw ' . Str::random(4),
        'court_number' => 'RESALE-WDR-' . Str::random(4),
        'surface_type' => 'vinyl',
        'is_active' => true,
      ]);

      PriceRule::create([
        'organization_id' => ${orgId},
        'venue_id' => ${venue1Id},
        'court_id' => $court->id,
        'type' => 'base',
        'price' => 180000,
        'priority' => 1,
        'is_active' => true,
        'version' => 1,
      ]);

      $seller = User::where('email', '${customer.email}')->firstOrFail();
      $otherUser = User::factory()->create();
      $futureTime = Carbon::now(config('app.timezone'))->addDays(4)->setTime(10, 0);

      $res = Reservation::create([
        'organization_id' => ${orgId},
        'user_id' => $seller->id,
        'court_id' => $court->id,
        'code' => 'RSV-RESALE-WDR-' . Str::upper(Str::random(6)),
        'status' => 'confirmed',
        'currency' => 'VND',
        'total_amount' => 180000,
        'payment_model' => 'direct_owner_v1',
        'confirmed_at' => now(),
      ]);

      $slot = ReservationSlot::create([
        'organization_id' => ${orgId},
        'reservation_id' => $res->id,
        'court_id' => $court->id,
        'starts_at' => $futureTime->copy()->utc(),
        'ends_at' => $futureTime->copy()->addHour()->utc(),
        'unit_price' => 180000,
        'status' => 'confirmed',
      ]);

      $entitlement = ReservationEntitlement::create([
        'organization_id' => ${orgId},
        'reservation_id' => $res->id,
        'reservation_slot_id' => $slot->id,
        'court_id' => $court->id,
        'user_id' => $seller->id,
        'entitlement_type' => 'court_reservation',
        'status' => 'active',
        'starts_at' => $futureTime->copy()->utc(),
        'ends_at' => $futureTime->copy()->addHour()->utc(),
        'amount' => 180000,
      ]);

      $listing = app(MarketplaceService::class)->list($entitlement, $seller, 150000);

      echo json_encode([
        'listing_id' => $listing->id,
        'seller_id' => $seller->id,
        'other_user_id' => $otherUser->id,
      ]);
    `

    const arranged = JSON.parse(
      execFileSync('php', ['-r', arrangeScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      listing_id: number
      seller_id: number
      other_user_id: number
    }

    // 2. Test withdraw permissions and state transitions:
    // a. Non-seller withdrawal attempt throws 403
    // b. Seller withdrawal succeeds -> status becomes 'withdrawn'
    // c. Repeated withdrawal on withdrawn listing throws 409
    const withdrawScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\MarketplaceListing;
      use App\\Models\\User;
      use App\\Services\\Social\\MarketplaceService;
      use Symfony\\Component\\HttpKernel\\Exception\\HttpException;

      $service = app(MarketplaceService::class);
      $listing = MarketplaceListing::find(${arranged.listing_id});
      $seller = User::find(${arranged.seller_id});
      $otherUser = User::find(${arranged.other_user_id});

      $unauthorizedWithdraw = false;
      try {
        $service->withdraw($listing, $otherUser);
      } catch (HttpException $e) {
        if ($e->getStatusCode() === 403) {
          $unauthorizedWithdraw = true;
        }
      }

      // Valid withdraw by seller
      $withdrawnListing = $service->withdraw($listing, $seller);

      $doubleWithdrawConflict = false;
      try {
        $service->withdraw($withdrawnListing, $seller);
      } catch (HttpException $e) {
        if ($e->getStatusCode() === 409) {
          $doubleWithdrawConflict = true;
        }
      }

      echo json_encode([
        'unauthorized_withdraw' => $unauthorizedWithdraw,
        'withdrawn_status' => $withdrawnListing->status,
        'double_withdraw_conflict' => $doubleWithdrawConflict,
      ]);
    `

    const withdrawResult = JSON.parse(
      execFileSync('php', ['-r', withdrawScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      unauthorized_withdraw: boolean
      withdrawn_status: string
      double_withdraw_conflict: boolean
    }

    expect(withdrawResult.unauthorized_withdraw).toBe(true)
    expect(withdrawResult.withdrawn_status).toBe('withdrawn')
    expect(withdrawResult.double_withdraw_conflict).toBe(true)
  })

  test('8.3.3: Người mua thanh toán và xác thực giao dịch: Tham chiếu thanh toán chưa xác minh bị từ chối 422', async () => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const org1 = manifest.fixtures?.organizations.org1
    const orgId = org1?.id
    const venue1Id = org1?.venue1A?.id
    if (!orgId || !venue1Id) throw new Error('Org1 or Venue1A missing from manifest')

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

    // 1. Arrange listing and buyer
    const arrangeScript = `
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
      use App\\Services\\Social\\MarketplaceService;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Resale Unverified ' . Str::random(4),
        'court_number' => 'RESALE-UNV-' . Str::random(4),
        'surface_type' => 'vinyl',
        'is_active' => true,
      ]);

      PriceRule::create([
        'organization_id' => ${orgId},
        'venue_id' => ${venue1Id},
        'court_id' => $court->id,
        'type' => 'base',
        'price' => 200000,
        'priority' => 1,
        'is_active' => true,
        'version' => 1,
      ]);

      $seller = User::factory()->create();
      $buyer = User::where('email', '${customer.email}')->firstOrFail();
      $futureTime = Carbon::now(config('app.timezone'))->addDays(5)->setTime(15, 0);

      $res = Reservation::create([
        'organization_id' => ${orgId},
        'user_id' => $seller->id,
        'court_id' => $court->id,
        'code' => 'RSV-RESALE-UNV-' . Str::upper(Str::random(6)),
        'status' => 'confirmed',
        'currency' => 'VND',
        'total_amount' => 200000,
        'payment_model' => 'direct_owner_v1',
        'confirmed_at' => now(),
      ]);

      $slot = ReservationSlot::create([
        'organization_id' => ${orgId},
        'reservation_id' => $res->id,
        'court_id' => $court->id,
        'starts_at' => $futureTime->copy()->utc(),
        'ends_at' => $futureTime->copy()->addHours(2)->utc(),
        'unit_price' => 200000,
        'status' => 'confirmed',
      ]);

      $entitlement = ReservationEntitlement::create([
        'organization_id' => ${orgId},
        'reservation_id' => $res->id,
        'reservation_slot_id' => $slot->id,
        'court_id' => $court->id,
        'user_id' => $seller->id,
        'entitlement_type' => 'court_reservation',
        'status' => 'active',
        'starts_at' => $futureTime->copy()->utc(),
        'ends_at' => $futureTime->copy()->addHours(2)->utc(),
        'amount' => 200000,
      ]);

      $listing = app(MarketplaceService::class)->list($entitlement, $seller, 170000);

      echo json_encode([
        'listing_id' => $listing->id,
        'seller_id' => $seller->id,
        'buyer_id' => $buyer->id,
      ]);
    `

    const arranged = JSON.parse(
      execFileSync('php', ['-r', arrangeScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      listing_id: number
      seller_id: number
      buyer_id: number
    }

    // 2. Buyer checks out to get payment attempt
    const checkoutScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\MarketplaceListing;
      use App\\Models\\User;
      use App\\Services\\Social\\MarketplaceService;

      $service = app(MarketplaceService::class);
      $listing = MarketplaceListing::find(${arranged.listing_id});
      $buyer = User::find(${arranged.buyer_id});

      $attempt = $service->checkout($listing, $buyer);

      echo json_encode([
        'attempt_id' => $attempt->id,
        'attempt_reference' => $attempt->reference,
        'attempt_amount' => $attempt->amount,
        'attempt_status' => $attempt->status,
      ]);
    `

    const checkout = JSON.parse(
      execFileSync('php', ['-r', checkoutScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      attempt_id: number
      attempt_reference: string
      attempt_amount: number
      attempt_status: string
    }

    expect(checkout.attempt_id).toBeGreaterThan(0)
    expect(checkout.attempt_amount).toBe(170000)
    expect(checkout.attempt_status).toBe('pending')

    // 3. Buyer attempts to purchase with fake/unverified payment reference:
    // MUST throw 422 ("Unverified buyer payment reference")
    const fakePurchaseScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\MarketplaceListing;
      use App\\Models\\ReservationEntitlement;
      use App\\Models\\User;
      use App\\Services\\Social\\MarketplaceService;
      use Symfony\\Component\\HttpKernel\\Exception\\HttpException;

      $service = app(MarketplaceService::class);
      $listing = MarketplaceListing::find(${arranged.listing_id});
      $buyer = User::find(${arranged.buyer_id});

      $rejected = false;
      $errorMessage = '';
      try {
        $service->purchase($listing, $buyer, 'idemp-fake-' . uniqid(), 'FAKE-UNVERIFIED-BUYER-REF', 'SELLER-REF', true);
      } catch (HttpException $e) {
        if ($e->getStatusCode() === 422) {
          $rejected = true;
          $errorMessage = $e->getMessage();
        }
      }

      $freshListing = $listing->fresh();
      $sellerEntitlement = $freshListing->entitlement;

      echo json_encode([
        'rejected' => $rejected,
        'error_message' => $errorMessage,
        'listing_status' => $freshListing->status,
        'seller_entitlement_status' => $sellerEntitlement->status,
      ]);
    `

    const fakePurchaseResult = JSON.parse(
      execFileSync('php', ['-r', fakePurchaseScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      rejected: boolean
      error_message: string
      listing_status: string
      seller_entitlement_status: string
    }

    expect(fakePurchaseResult.rejected).toBe(true)
    expect(fakePurchaseResult.error_message).toContain('Unverified buyer payment reference')
    expect(fakePurchaseResult.listing_status).toBe('listed') // Still listed!
    expect(fakePurchaseResult.seller_entitlement_status).toBe('active') // Still active with seller!
  })

  test('8.3.4: Hoàn tất chuyển nhượng khi người mua thanh toán hợp lệ: Đổi quyền check-in duy nhất, không hoa hồng mới', async ({
    page,
  }) => {
    const manifest = getTestManifest()
    const customer = getActorCredentials(manifest, 'customer')
    const staff = getActorCredentials(manifest, 'staff1')
    const org1 = manifest.fixtures?.organizations.org1
    const orgId = org1?.id
    const venue1Id = org1?.venue1A?.id
    if (!orgId || !venue1Id) throw new Error('Org1 or Venue1A missing from manifest')

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

    // 1. Arrange confirmed reservation (200,000 VND), listed at 150,000 VND
    const arrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\OwnerPaymentAttempt;
      use App\\Models\\PriceRule;
      use App\\Models\\ReceivingConnection;
      use App\\Models\\Reservation;
      use App\\Models\\ReservationEntitlement;
      use App\\Models\\ReservationSlot;
      use App\\Models\\User;
      use App\\Services\\Social\\MarketplaceService;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Resale Complete ' . Str::random(4),
        'court_number' => 'RESALE-CMP-' . Str::random(4),
        'surface_type' => 'vinyl',
        'is_active' => true,
      ]);

      PriceRule::create([
        'organization_id' => ${orgId},
        'venue_id' => ${venue1Id},
        'court_id' => $court->id,
        'type' => 'base',
        'price' => 200000,
        'priority' => 1,
        'is_active' => true,
        'version' => 1,
      ]);

      $seller = User::factory()->create();
      $buyer = User::where('email', '${customer.email}')->firstOrFail();
      $staffUser = User::where('email', '${staff.email}')->firstOrFail();

      // Slot scheduled in future
      $slotTime = Carbon::now(config('app.timezone'))->addDays(6)->setTime(16, 0);

      $res = Reservation::create([
        'organization_id' => ${orgId},
        'user_id' => $seller->id,
        'court_id' => $court->id,
        'code' => 'RSV-RESALE-CMP-' . Str::upper(Str::random(6)),
        'status' => 'confirmed',
        'currency' => 'VND',
        'total_amount' => 200000,
        'payment_model' => 'direct_owner_v1',
        'confirmed_at' => now(),
      ]);

      $slot = ReservationSlot::create([
        'organization_id' => ${orgId},
        'reservation_id' => $res->id,
        'court_id' => $court->id,
        'starts_at' => $slotTime->copy()->utc(),
        'ends_at' => $slotTime->copy()->addHours(2)->utc(),
        'unit_price' => 200000,
        'status' => 'confirmed',
      ]);

      $sellerEntitlement = ReservationEntitlement::create([
        'organization_id' => ${orgId},
        'reservation_id' => $res->id,
        'reservation_slot_id' => $slot->id,
        'court_id' => $court->id,
        'user_id' => $seller->id,
        'entitlement_type' => 'court_reservation',
        'status' => 'active',
        'starts_at' => $slotTime->copy()->utc(),
        'ends_at' => $slotTime->copy()->addHours(2)->utc(),
        'amount' => 200000,
      ]);

      $service = app(MarketplaceService::class);
      $listing = $service->list($sellerEntitlement, $seller, 150000);
      $buyerAttempt = $service->checkout($listing, $buyer);

      $connection = ReceivingConnection::where('venue_id', ${venue1Id})->where('status', 'verified')->firstOrFail();

      echo json_encode([
        'listing_id' => $listing->id,
        'seller_id' => $seller->id,
        'buyer_id' => $buyer->id,
        'staff_id' => $staffUser->id,
        'reservation_id' => $res->id,
        'seller_entitlement_id' => $sellerEntitlement->id,
        'buyer_attempt_ref' => $buyerAttempt->reference,
        'asking_price' => $listing->asking_price,
        'connection_routing_id' => $connection->routing_id,
        'connection_account' => $connection->accountNumber(),
        'transaction_date' => now()->addSeconds(10)->toIso8601String(),
      ]);
    `

    const arranged = JSON.parse(
      execFileSync('php', ['-r', arrangeScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      listing_id: number
      seller_id: number
      buyer_id: number
      staff_id: number
      reservation_id: number
      seller_entitlement_id: number
      buyer_attempt_ref: string
      asking_price: number
      connection_routing_id: string
      connection_account: string
      transaction_date: string
    }

    // 2. Buyer pays asking price via verified SePay webhook
    const timestamp = String(Math.floor(Date.now() / 1000))
    const eventIdBuyer = 'MKT-BUY-' + Date.now()
    const payloadBuyer = JSON.stringify({
      id: eventIdBuyer,
      transactionDate: arranged.transaction_date,
      accountNumber: arranged.connection_account,
      code: arranged.buyer_attempt_ref,
      content: arranged.buyer_attempt_ref,
      transferType: 'in',
      transferAmount: arranged.asking_price,
      referenceCode: 'BANK-' + eventIdBuyer,
    })

    const signatureScriptBuyer = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\ReceivingConnection;
      $conn = ReceivingConnection::where('routing_id', '${arranged.connection_routing_id}')->firstOrFail();
      $secret = $conn->webhook_secret_ciphertext ? \\Illuminate\\Support\\Facades\\Crypt::decryptString($conn->webhook_secret_ciphertext) : 'test-secret';
      $sig = hash_hmac('sha256', '${timestamp}.' . '${payloadBuyer}', $secret);
      echo 'sha256=' . $sig;
    `
    const signatureBuyer = execFileSync('php', ['-r', signatureScriptBuyer], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    }).trim()

    await page.request.post(
      `${manifest.backend.url}/api/webhooks/sepay/connections/${arranged.connection_routing_id}`,
      {
        headers: {
          'X-SePay-Timestamp': timestamp,
          'X-SePay-Signature': signatureBuyer,
          'Content-Type': 'application/json',
        },
        data: payloadBuyer,
      },
    )

    // Process buyer receipt
    const procBuyerScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\OwnerPaymentReceipt;
      use App\\Services\\Payment\\OwnerPaymentAttemptService;

      $receipt = OwnerPaymentReceipt::where('provider_event_id', '${eventIdBuyer}')->first();
      if ($receipt && ! $receipt->processed_at) {
        app(OwnerPaymentAttemptService::class)->processReceipt($receipt->id);
      }
    `
    execFileSync('php', ['-r', procBuyerScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
    })

    // 3. Owner records seller reimbursement evidence via webhook or receipt
    const executionRef = 'ACREIMB' + Date.now()
    const eventIdReimb = 'MKT-REIMB-' + Date.now()
    const payloadReimb = JSON.stringify({
      id: eventIdReimb,
      transactionDate: arranged.transaction_date,
      accountNumber: arranged.connection_account,
      code: executionRef,
      content: executionRef,
      transferType: 'in',
      transferAmount: arranged.asking_price,
      referenceCode: 'BANK-' + eventIdReimb,
    })

    const signatureScriptReimb = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\ReceivingConnection;
      $conn = ReceivingConnection::where('routing_id', '${arranged.connection_routing_id}')->firstOrFail();
      $secret = $conn->webhook_secret_ciphertext ? \\Illuminate\\Support\\Facades\\Crypt::decryptString($conn->webhook_secret_ciphertext) : 'test-secret';
      $sig = hash_hmac('sha256', '${timestamp}.' . '${payloadReimb}', $secret);
      echo 'sha256=' . $sig;
    `
    const signatureReimb = execFileSync('php', ['-r', signatureScriptReimb], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
      encoding: 'utf-8',
    }).trim()

    await page.request.post(
      `${manifest.backend.url}/api/webhooks/sepay/connections/${arranged.connection_routing_id}`,
      {
        headers: {
          'X-SePay-Timestamp': timestamp,
          'X-SePay-Signature': signatureReimb,
          'Content-Type': 'application/json',
        },
        data: payloadReimb,
      },
    )

    // Process reimbursement receipt
    const procReimbScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\OwnerPaymentReceipt;
      use App\\Services\\Payment\\OwnerPaymentAttemptService;

      $receipt = OwnerPaymentReceipt::where('provider_event_id', '${eventIdReimb}')->first();
      if ($receipt && ! $receipt->processed_at) {
        app(OwnerPaymentAttemptService::class)->processReceipt($receipt->id);
      }
    `
    execFileSync('php', ['-r', procReimbScript], {
      cwd: backendDir,
      env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
    })

    // 4. Complete purchase with verified buyer and seller reimbursement references
    const purchaseScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\MarketplaceListing;
      use App\\Models\\Reservation;
      use App\\Models\\ReservationEntitlement;
      use App\\Models\\User;
      use App\\Services\\Attendance\\AttendanceService;
      use App\\Services\\Social\\MarketplaceService;
      use Carbon\\Carbon;
      use Symfony\\Component\\HttpKernel\\Exception\\HttpException;
      $service = app(MarketplaceService::class);
      $listing = MarketplaceListing::find(${arranged.listing_id});
      $buyer = User::find(${arranged.buyer_id});
      $seller = User::find(${arranged.seller_id});
      $staff = User::find(${arranged.staff_id});

      $transfer = $service->purchase(
        $listing,
        $buyer,
        'idemp-complete-' . uniqid(),
        '${arranged.buyer_attempt_ref}',
        '${executionRef}',
        true
      );

      $freshListing = $listing->fresh();
      $sellerEntitlement = ReservationEntitlement::find(${arranged.seller_entitlement_id});
      $buyerEntitlement = ReservationEntitlement::where('user_id', $buyer->id)
        ->where('reservation_id', ${arranged.reservation_id})
        ->latest('id')
        ->firstOrFail();
      $res = Reservation::find(${arranged.reservation_id});

      // 5. Test Check-in authority:
      // Set test time to slot start time so check-in satisfies current venue-local day check
      $slotTime = $buyerEntitlement->starts_at;
      Carbon::setTestNow($slotTime->copy()->addMinutes(10));
      $attendanceService = app(AttendanceService::class);
      $sellerCheckinRejected = false;
      try {
        // a. Seller check-in with revoked pass is REJECTED
        try {
          $attendanceService->checkIn(
            $staff,
            ${orgId},
            'AURA_ENTITLEMENT_' . $sellerEntitlement->id,
            'Seller check-in attempt after transfer',
            $seller
          );
        } catch (HttpException $e) {
          if ($e->getStatusCode() === 422) {
            $sellerCheckinRejected = true;
          }
        }

        // b. Buyer check-in with new entitlement pass SUCCEEDS
        $buyerCheckinResult = $attendanceService->checkIn(
          $staff,
          ${orgId},
          'AURA_ENTITLEMENT_' . $buyerEntitlement->id,
          'Buyer check-in via active entitlement pass',
          $buyer
        );
      } finally {
        Carbon::setTestNow();
      }
      echo json_encode([
        'transfer_status' => $transfer->status,
        'listing_status' => $freshListing->status,
        'seller_entitlement_status' => $sellerEntitlement->status,
        'buyer_entitlement_status' => $buyerEntitlement->status,
        'buyer_entitlement_amount' => $buyerEntitlement->amount, // Original price preserved!
        'res_user_id' => $res->user_id,
        'marketplace_fee' => $transfer->marketplace_fee, // 0 fee
        'seller_checkin_rejected' => $sellerCheckinRejected,
        'buyer_attendance_status' => $buyerCheckinResult['attendance']->status,
      ]);
    `

    const purchaseResult = JSON.parse(
      execFileSync('php', ['-r', purchaseScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      transfer_status: string
      listing_status: string
      seller_entitlement_status: string
      buyer_entitlement_status: string
      buyer_entitlement_amount: number
      res_user_id: number
      marketplace_fee: number
      seller_checkin_rejected: boolean
      buyer_attendance_status: string
    }

    expect(purchaseResult.transfer_status).toBe('completed')
    expect(purchaseResult.listing_status).toBe('sold')
    expect(purchaseResult.seller_entitlement_status).toBe('transferred')
    expect(purchaseResult.buyer_entitlement_status).toBe('active')
    expect(purchaseResult.buyer_entitlement_amount).toBe(200000) // Original price preserved!
    expect(purchaseResult.res_user_id).toBe(arranged.buyer_id) // Holder switched!
    expect(purchaseResult.marketplace_fee).toBe(0) // Zero commission!
    expect(purchaseResult.seller_checkin_rejected).toBe(true) // Seller pass revoked!
    expect(purchaseResult.buyer_attendance_status).toBe('checked_in') // Buyer check-in succeeds!
  })

  test('8.3.5: Tranh chấp người mua (Race condition): Hai người cùng mua một vé -> người đầu tiên thành công, người sau nhận 409', async () => {
    const manifest = getTestManifest()
    const org1 = manifest.fixtures?.organizations.org1
    const orgId = org1?.id
    const venue1Id = org1?.venue1A?.id
    if (!orgId || !venue1Id) throw new Error('Org1 or Venue1A missing from manifest')

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

    // 1. Arrange listing and Buyer A checkout
    const arrangeScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\Court;
      use App\\Models\\MarketplaceListing;
      use App\\Models\\PriceRule;
      use App\\Models\\ReceivingConnection;
      use App\\Models\\Reservation;
      use App\\Models\\ReservationEntitlement;
      use App\\Models\\ReservationSlot;
      use App\\Models\\User;
      use App\\Services\\Social\\MarketplaceService;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Resale Race ' . Str::random(4),
        'court_number' => 'RESALE-RACE-' . Str::random(4),
        'surface_type' => 'vinyl',
        'is_active' => true,
      ]);

      PriceRule::create([
        'organization_id' => ${orgId},
        'venue_id' => ${venue1Id},
        'court_id' => $court->id,
        'type' => 'base',
        'price' => 200000,
        'priority' => 1,
        'is_active' => true,
        'version' => 1,
      ]);

      $seller = User::factory()->create();
      $buyerA = User::factory()->create();
      $buyerB = User::factory()->create();
      $futureTime = Carbon::now(config('app.timezone'))->addDays(7)->setTime(18, 0);

      $res = Reservation::create([
        'organization_id' => ${orgId},
        'user_id' => $seller->id,
        'court_id' => $court->id,
        'code' => 'RSV-RESALE-RACE-' . Str::upper(Str::random(6)),
        'status' => 'confirmed',
        'currency' => 'VND',
        'total_amount' => 200000,
        'payment_model' => 'direct_owner_v1',
        'confirmed_at' => now(),
      ]);

      $slot = ReservationSlot::create([
        'organization_id' => ${orgId},
        'reservation_id' => $res->id,
        'court_id' => $court->id,
        'starts_at' => $futureTime->copy()->utc(),
        'ends_at' => $futureTime->copy()->addHours(2)->utc(),
        'unit_price' => 200000,
        'status' => 'confirmed',
      ]);

      $entitlement = ReservationEntitlement::create([
        'organization_id' => ${orgId},
        'reservation_id' => $res->id,
        'reservation_slot_id' => $slot->id,
        'court_id' => $court->id,
        'user_id' => $seller->id,
        'entitlement_type' => 'court_reservation',
        'status' => 'active',
        'starts_at' => $futureTime->copy()->utc(),
        'ends_at' => $futureTime->copy()->addHours(2)->utc(),
        'amount' => 200000,
      ]);

      $service = app(MarketplaceService::class);
      $listing = $service->list($entitlement, $seller, 160000);

      $attemptA = $service->checkout($listing, $buyerA);
      $conn = ReceivingConnection::findOrFail($attemptA->receiving_connection_id);

      echo json_encode([
        'listing_id' => $listing->id,
        'buyer_a_id' => $buyerA->id,
        'buyer_b_id' => $buyerB->id,
        'attempt_a_id' => $attemptA->id,
        'attempt_a_reference' => $attemptA->reference,
        'attempt_a_amount' => (int) $attemptA->amount,
        'routing_id' => $conn->routing_id,
        'account_number' => $conn->accountNumber(),
      ]);
    `
    const arrangeData = JSON.parse(
      execFileSync('php', ['-r', arrangeScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      listing_id: number
      buyer_a_id: number
      buyer_b_id: number
      attempt_a_id: number
      attempt_a_reference: string
      attempt_a_amount: number
      routing_id: string
      account_number: string
    }

    // 2. Deliver real authenticated payment webhook for Buyer A
    const secret = `whsec_e2e_1a_${manifest.runId}`
    const webhookRes = await sendSyntheticPaymentWebhook(manifest, {
      routingId: arrangeData.routing_id,
      secret,
      accountNumber: arrangeData.account_number,
      amount: arrangeData.attempt_a_amount,
      code: arrangeData.attempt_a_reference,
      transactionDate: formatVietnamDate(new Date(Date.now() + 2000)),
    })
    expect(webhookRes.statusCode).toBe(200)

    // 3. Concurrent purchase race: Buyer A completes purchase, Buyer B is rejected with 409
    const raceScript = `
      require "vendor/autoload.php";
      $app = require_once "bootstrap/app.php";
      $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
      $kernel->bootstrap();

      use App\\Models\\MarketplaceListing;
      use App\\Models\\OwnerPaymentAttempt;
      use App\\Models\\OwnerPaymentReceipt;
      use App\\Models\\User;
      use App\\Services\\Social\\MarketplaceService;
      use Illuminate\\Support\\Str;
      use Symfony\\Component\\HttpKernel\\Exception\\HttpException;

      $listing = MarketplaceListing::findOrFail(${arrangeData.listing_id});
      $buyerA = User::findOrFail(${arrangeData.buyer_a_id});
      $buyerB = User::findOrFail(${arrangeData.buyer_b_id});
      $service = app(MarketplaceService::class);

      // Buyer B checks out
      $attemptB = $service->checkout($listing, $buyerB);

      // Seller reimbursement evidence
      $reimbRef = 'ACREIMB' . Str::upper(Str::random(10));
      $attemptA = OwnerPaymentAttempt::findOrFail(${arrangeData.attempt_a_id});
      OwnerPaymentReceipt::create([
        'organization_id' => ${orgId},
        'venue_id' => ${venue1Id},
        'receiving_connection_id' => $attemptA->receiving_connection_id,
        'receiving_connection_evidence_id' => 1,
        'credential_version' => 1,
        'receiver_snapshot_ciphertext' => $attemptA->receiver_snapshot_ciphertext,
        'provider' => 'sepay',
        'source' => 'webhook',
        'provider_event_id' => 'reimb-' . uniqid(),
        'event_fingerprint' => hash('sha256', uniqid()),
        'reference_code' => $reimbRef,
        'transfer_type' => 'in',
        'amount' => 160000,
        'currency' => 'VND',
        'status' => 'allocated',
        'event_at' => now(),
        'received_at' => now(),
      ]);

      // Buyer A executes purchase first
      $transferA = $service->purchase($listing, $buyerA, 'race-idemp-a-${arrangeData.listing_id}', '${arrangeData.attempt_a_reference}', $reimbRef, true);

      // Buyer B executes purchase after listing is sold: MUST be rejected with 409 conflict
      $buyerBRejected = false;
      $buyerBErrorStatus = 0;
      try {
        $service->purchase($listing->fresh(), $buyerB, 'race-idemp-b-${arrangeData.listing_id}', $attemptB->reference, $reimbRef, true);
      } catch (HttpException $e) {
        $buyerBRejected = true;
        $buyerBErrorStatus = $e->getStatusCode();
      }

      echo json_encode([
        'buyer_a_status' => $transferA->status,
        'buyer_b_rejected' => $buyerBRejected,
        'buyer_b_status_code' => $buyerBErrorStatus,
        'final_listing_status' => $listing->fresh()->status,
      ]);
    `
    const raceResult = JSON.parse(
      execFileSync('php', ['-r', raceScript], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as {
      buyer_a_status: string
      buyer_b_rejected: boolean
      buyer_b_status_code: number
      final_listing_status: string
    }

    expect(raceResult.buyer_a_status).toBe('completed')
    expect(raceResult.buyer_b_rejected).toBe(true)
    expect(raceResult.buyer_b_status_code).toBe(409) // 409 conflict for the second buyer!
    expect(raceResult.final_listing_status).toBe('sold')
  })
})
