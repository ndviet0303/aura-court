import { test, expect, type Page } from '@playwright/test'
import { getCanonicalBackendEnv, getTestManifest, priceCourtPhp } from './runner/test-data.ts'
import { getActorCredentials } from './runner/auth.ts'
import { withE2EApi } from './runner/with-api.ts'
import { execFileSync } from 'node:child_process'
import path from 'node:path'

type ResaleState = {
  role: 'seller' | 'buyer'
  status: string
  lock: {
    locked_until: string
    payment_reported_at: string | null
    reference: string
    held_by_me: boolean
  } | null
  receiver: { bank_code: string; account_number: string; account_name: string } | null
  payment: { amount: number; currency: string; reference: string; qr_url: string } | null
  transfer: {
    status: string
    buyer_amount: number
    marketplace_fee: number
    seller_refund: number
    settlement_mode: string
  } | null
  last_release: { by: string; payment_reported: boolean; reason: string | null } | null
}

type Arranged = {
  listing_id: number
  seller_id: number
  reservation_id: number
  entitlement_id: number
  kept_entitlement_id: number
}

// Peer-to-peer resale (design Decision 5): the buyer pays the seller directly and the seller
// confirms receipt. Aura Court and the venue never collect, hold or refund the money.
test.describe('P1: Chợ nhượng sân P2P: Giữ chỗ, Chuyển tiền thẳng cho người bán, Xác nhận nhận tiền @p1', () => {
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
  const customer = getActorCredentials(manifest, 'customer')
  const customer2 = getActorCredentials(manifest, 'customer2')
  const staff = getActorCredentials(manifest, 'staff1')
  const orgId = manifest.fixtures?.organizations.org1?.id
  const venue1Id = manifest.fixtures?.organizations.org1?.venue1A?.id
  if (!orgId || !venue1Id) throw new Error('Org1 or Venue1A missing from manifest')
  const orgHeader = { 'X-Organization-ID': String(orgId) }

  const bootstrap = `
    require "vendor/autoload.php";
    $app = require_once "bootstrap/app.php";
    $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
    $kernel->bootstrap();
  `
  const runPhp = <T>(script: string): T =>
    JSON.parse(
      execFileSync('php', ['-r', bootstrap + script], {
        cwd: backendDir,
        env: { ...getCanonicalBackendEnv(manifest), ...backendEnv },
        encoding: 'utf-8',
      }),
    ) as T

  // A unique account number per test, so "never disclosed" can be checked by plain text search.
  const uniqueAccount = () =>
    `97${Date.now().toString().slice(-8)}${Math.floor(Math.random() * 90 + 10)}`
  const receiverPhp = (account: string) =>
    `['bank_code' => 'TPB', 'account_number' => '${account}', 'account_name' => 'NGUYEN VAN A']`

  /**
   * A confirmed two-hour booking (two one-hour entitlements) on a dedicated court. The seller lists
   * the second hour; the first hour must stay theirs.
   */
  const arrangeListing = (opts: {
    label: string
    asking: number
    account: string
    sellerEmail?: string
    daysAhead: number
  }) =>
    runPhp<Arranged>(`
      use App\\Models\\Court;
      use App\\Models\\Reservation;
      use App\\Models\\ReservationEntitlement;
      use App\\Models\\ReservationSlot;
      use App\\Models\\User;
      use App\\Services\\Social\\MarketplaceService;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Resale ${opts.label} ' . Str::random(4),
        'court_number' => 'RESALE-${opts.label}-' . Str::random(4),
        'surface_type' => 'vinyl',
        'is_active' => true,
      ]);
      ${priceCourtPhp(200000)}

      $seller = ${opts.sellerEmail ? `User::where('email', '${opts.sellerEmail}')->firstOrFail()` : 'User::factory()->create()'};
      $start = Carbon::now(config('app.timezone'))->addDays(${opts.daysAhead})->setTime(19, 0);
      $res = Reservation::create([
        'organization_id' => ${orgId},
        'user_id' => $seller->id,
        'court_id' => $court->id,
        'code' => 'RSV-P2P-' . Str::upper(Str::random(6)),
        'status' => 'confirmed',
        'currency' => 'VND',
        'total_amount' => 400000,
        'payment_model' => 'direct_owner_v1',
        'confirmed_at' => now(),
      ]);

      $entitlements = [];
      foreach ([0, 1] as $hour) {
        $slot = ReservationSlot::create([
          'organization_id' => ${orgId},
          'reservation_id' => $res->id,
          'court_id' => $court->id,
          'starts_at' => $start->copy()->addHours($hour)->utc(),
          'ends_at' => $start->copy()->addHours($hour + 1)->utc(),
          'unit_price' => 200000,
          'status' => 'confirmed',
        ]);
        $entitlements[] = ReservationEntitlement::create([
          'organization_id' => ${orgId},
          'reservation_id' => $res->id,
          'reservation_slot_id' => $slot->id,
          'court_id' => $court->id,
          'user_id' => $seller->id,
          'entitlement_type' => 'court_reservation',
          'status' => 'active',
          'starts_at' => $slot->starts_at,
          'ends_at' => $slot->ends_at,
          'amount' => 200000,
        ]);
      }

      $listing = app(MarketplaceService::class)->list($entitlements[1], $seller, ${opts.asking}, ${receiverPhp(opts.account)});

      echo json_encode([
        'listing_id' => $listing->id,
        'seller_id' => $seller->id,
        'reservation_id' => $res->id,
        'entitlement_id' => $entitlements[1]->id,
        'kept_entitlement_id' => $entitlements[0]->id,
      ]);
    `)

  const login = (page: Page, user: { email: string; password: string }) =>
    withE2EApi(page, ({ authApi }) => authApi.login({ email: user.email, password: user.password }))

  /** Calls the API as the page's user and returns the HTTP status with the raw body text. */
  const call = (page: Page, method: 'GET' | 'POST', apiPath: string, data?: unknown) =>
    withE2EApi(page, async ({ api }) => {
      try {
        const body =
          method === 'GET'
            ? await api.get(apiPath, { headers: orgHeader })
            : await api.post(apiPath, data ?? {}, { headers: orgHeader })
        return { status: 200, body: body as Record<string, unknown>, text: JSON.stringify(body) }
      } catch (err) {
        const e = err as { status?: number; response?: { data?: unknown } }
        if (typeof e.status !== 'number') throw err
        return {
          status: e.status,
          body: (e.response?.data ?? {}) as Record<string, unknown>,
          text: JSON.stringify(e.response?.data ?? {}),
        }
      }
    })

  const listedIds = (page: Record<string, unknown>) =>
    ((page.data ?? []) as Array<{ id: number }>).map((listing) => listing.id)

  test('7.9.1: Điều kiện đăng pass: không vượt giá gốc, không pass lịch tranh chấp, phí 0, lưu tài khoản người bán đã mã hóa', async ({
    page,
  }) => {
    const account = uniqueAccount()
    const result = runPhp<{
      excess_price_rejected: boolean
      disputed_rejected: boolean
      fee_bps: number
      status: string
      original_price: number
      asking_price: number
      receiver_matches: boolean
      ciphertext_hides_account: boolean
      listing_id: number
    }>(`
      use App\\Models\\Court;
      use App\\Models\\Reservation;
      use App\\Models\\ReservationEntitlement;
      use App\\Models\\ReservationSlot;
      use App\\Models\\User;
      use App\\Models\\VenueBookingPolicy;
      use App\\Services\\Social\\MarketplaceService;
      use Carbon\\Carbon;
      use Illuminate\\Support\\Str;
      use Symfony\\Component\\HttpKernel\\Exception\\HttpException;

      $court = Court::create([
        'venue_id' => ${venue1Id},
        'name' => 'Sân Resale Rules ' . Str::random(4),
        'court_number' => 'RESALE-RULES-' . Str::random(4),
        'surface_type' => 'vinyl',
        'is_active' => true,
      ]);
      ${priceCourtPhp(200000)}

      $policy = VenueBookingPolicy::where('venue_id', ${venue1Id})->latest('version')->first();
      $settings = $policy ? $policy->settings : [];
      $settings['transfer']['transfer_allowed'] = true;
      $settings['transfer']['cutoff_hours'] = 2;
      VenueBookingPolicy::updateOrCreate(
        ['venue_id' => ${venue1Id}, 'version' => ($policy ? $policy->version + 1 : 1)],
        ['settings' => $settings, 'effective_at' => now()->subDay(), 'created_by' => 1]
      );

      $seller = User::where('email', '${customer.email}')->firstOrFail();
      $start = Carbon::now(config('app.timezone'))->addDays(3)->setTime(14, 0);
      $res = Reservation::create([
        'organization_id' => ${orgId}, 'user_id' => $seller->id, 'court_id' => $court->id,
        'code' => 'RSV-P2P-RULES-' . Str::upper(Str::random(6)), 'status' => 'confirmed',
        'currency' => 'VND', 'total_amount' => 200000, 'payment_model' => 'direct_owner_v1', 'confirmed_at' => now(),
      ]);
      $slot = ReservationSlot::create([
        'organization_id' => ${orgId}, 'reservation_id' => $res->id, 'court_id' => $court->id,
        'starts_at' => $start->copy()->utc(), 'ends_at' => $start->copy()->addHour()->utc(),
        'unit_price' => 200000, 'status' => 'confirmed',
      ]);
      $entitlement = ReservationEntitlement::create([
        'organization_id' => ${orgId}, 'reservation_id' => $res->id, 'reservation_slot_id' => $slot->id,
        'court_id' => $court->id, 'user_id' => $seller->id, 'entitlement_type' => 'court_reservation',
        'status' => 'active', 'starts_at' => $slot->starts_at, 'ends_at' => $slot->ends_at, 'amount' => 200000,
      ]);

      $service = app(MarketplaceService::class);
      $receiver = ${receiverPhp(account)};

      $excessPriceRejected = false;
      try {
        $service->list($entitlement, $seller, 250000, $receiver);
      } catch (HttpException $e) {
        $excessPriceRejected = $e->getStatusCode() === 422;
      }

      $disputedRejected = false;
      $res->update(['status' => 'disputed']);
      try {
        $service->list($entitlement->fresh(), $seller, 160000, $receiver);
      } catch (HttpException $e) {
        $disputedRejected = $e->getStatusCode() === 422;
      }
      $res->update(['status' => 'confirmed']);

      $listing = $service->list($entitlement->fresh(), $seller, 160000, $receiver)->fresh();

      echo json_encode([
        'excess_price_rejected' => $excessPriceRejected,
        'disputed_rejected' => $disputedRejected,
        'listing_id' => $listing->id,
        'fee_bps' => $listing->fee_bps,
        'status' => $listing->status,
        'original_price' => $listing->original_price,
        'asking_price' => $listing->asking_price,
        'receiver_matches' => $listing->sellerReceiver() === $receiver,
        'ciphertext_hides_account' => ! str_contains((string) $listing->seller_receiver_ciphertext, '${account}'),
      ]);
    `)

    expect(result.excess_price_rejected).toBe(true)
    expect(result.disputed_rejected).toBe(true)
    expect(result.fee_bps).toBe(0) // No platform fee on new listings
    expect(result.status).toBe('listed')
    expect(result.original_price).toBe(200000)
    expect(result.asking_price).toBe(160000)
    expect(result.receiver_matches).toBe(true)
    expect(result.ciphertext_hides_account).toBe(true)

    // The seller's own listing JSON never carries the receiving account.
    await login(page, customer)
    const mine = await call(page, 'GET', '/marketplace/listings?mine=1')
    expect(mine.status).toBe(200)
    expect(listedIds(mine.body)).toContain(result.listing_id)
    expect(mine.text).not.toContain(account)
    expect(mine.text).not.toContain('seller_receiver')
  })

  test('7.9.2: Giữ chỗ độc quyền: chỉ người giữ chỗ và người bán thấy tài khoản/QR, người thứ hai nhận 409', async ({
    page,
    browser,
  }) => {
    const account = uniqueAccount()
    const arranged = arrangeListing({ label: 'LOCK', asking: 170000, account, daysAhead: 5 })

    // Buyer A locks the listing over HTTP and sees the seller's VietQR for the exact asking price.
    await login(page, customer)
    const lockA = await call(page, 'POST', `/marketplace/listings/${arranged.listing_id}/checkout`)
    expect(lockA.status).toBe(200)
    const stateA = lockA.body as unknown as ResaleState
    expect(stateA.role).toBe('buyer')
    expect(stateA.status).toBe('checkout')
    expect(stateA.lock?.held_by_me).toBe(true)
    expect(stateA.lock?.reference).toMatch(/^RS[A-Z0-9]{10}$/)
    expect(stateA.receiver?.account_number).toBe(account)
    expect(stateA.payment?.amount).toBe(170000)
    expect(stateA.payment?.reference).toBe(stateA.lock?.reference)
    expect(stateA.payment?.qr_url).toBeTruthy()

    // Buyer B gets 409 without the account, cannot read the state, and no longer sees the listing.
    const contextB = await browser.newContext()
    const pageB = await contextB.newPage()
    try {
      await login(pageB, customer2)
      const lockB = await call(
        pageB,
        'POST',
        `/marketplace/listings/${arranged.listing_id}/checkout`,
      )
      expect(lockB.status).toBe(409)
      expect(lockB.text).not.toContain(account)

      const stateB = await call(pageB, 'GET', `/marketplace/listings/${arranged.listing_id}`)
      expect(stateB.status).toBe(404)
      expect(stateB.text).not.toContain(account)

      const listB = await call(pageB, 'GET', '/marketplace/listings')
      expect(listB.status).toBe(200)
      expect(listedIds(listB.body)).not.toContain(arranged.listing_id)
      expect(listB.text).not.toContain(account)
    } finally {
      await contextB.close()
    }

    // The public list shows A their held listing, still without the account.
    const listA = await call(page, 'GET', '/marketplace/listings')
    expect(listedIds(listA.body)).toContain(arranged.listing_id)
    expect(listA.text).not.toContain(account)

    // The seller sees the account and the same payment instructions.
    const seller = runPhp<{
      role: string
      account: string | null
      amount: number | null
      held_by_me: boolean | null
    }>(`
      use App\\Models\\MarketplaceListing;
      use App\\Models\\User;
      use App\\Services\\Social\\PeerToPeerResaleService;
      $state = app(PeerToPeerResaleService::class)->state(MarketplaceListing::find(${arranged.listing_id}), User::find(${arranged.seller_id}));
      echo json_encode([
        'role' => $state['role'],
        'account' => $state['receiver']['account_number'] ?? null,
        'amount' => $state['payment']['amount'] ?? null,
        'held_by_me' => $state['lock']['held_by_me'] ?? null,
      ]);
    `)
    expect(seller).toEqual({ role: 'seller', account, amount: 170000, held_by_me: false })
  })

  test('7.9.3: Người mua báo đã chuyển, người bán xác nhận nhận tiền: chuyển đúng 1 giờ, phí 0, không bút toán', async ({
    page,
  }) => {
    const account = uniqueAccount()
    const arranged = arrangeListing({ label: 'SALE', asking: 150000, account, daysAhead: 6 })
    const buyerId = runPhp<{ id: number }>(
      `echo json_encode(['id' => App\\Models\\User::where('email', '${customer.email}')->value('id')]);`,
    ).id

    // Buyer locks and reports the transfer; the report alone moves nothing.
    await login(page, customer)
    expect(
      (await call(page, 'POST', `/marketplace/listings/${arranged.listing_id}/checkout`)).status,
    ).toBe(200)
    const reported = await call(
      page,
      'POST',
      `/marketplace/listings/${arranged.listing_id}/payment-sent`,
    )
    expect(reported.status).toBe(200)
    const reportedState = reported.body as unknown as ResaleState
    expect(reportedState.lock?.payment_reported_at).toBeTruthy()
    expect(reportedState.transfer).toBeNull()

    // After reporting, only the seller may release the lock.
    expect(
      (await call(page, 'POST', `/marketplace/listings/${arranged.listing_id}/release`)).status,
    ).toBe(409)

    const confirmed = runPhp<{
      before_confirm_holder: number
      listing_status: string
      seller_entitlement_status: string
      kept_entitlement_status: string
      kept_entitlement_user: number
      buyer_entitlement_status: string
      buyer_entitlement_amount: number
      transfer_status: string
      buyer_amount: number
      marketplace_fee: number
      seller_refund: number
      settlement_mode: string
      sale_journal_exists: boolean
      seller_checkin_rejected: boolean
      buyer_attendance_status: string
    }>(`
      use App\\Models\\EntitlementTransfer;
      use App\\Models\\Journal;
      use App\\Models\\MarketplaceListing;
      use App\\Models\\ReservationEntitlement;
      use App\\Models\\User;
      use App\\Services\\Attendance\\AttendanceService;
      use App\\Services\\Social\\PeerToPeerResaleService;
      use Carbon\\Carbon;
      use Symfony\\Component\\HttpKernel\\Exception\\HttpException;

      $listing = MarketplaceListing::find(${arranged.listing_id});
      $seller = User::find(${arranged.seller_id});
      $buyer = User::find(${buyerId});
      $staff = User::where('email', '${staff.email}')->firstOrFail();
      $beforeHolder = ReservationEntitlement::find(${arranged.entitlement_id})->user_id;

      app(PeerToPeerResaleService::class)->confirmReceipt($listing, $seller);

      $sellerEntitlement = ReservationEntitlement::find(${arranged.entitlement_id});
      $kept = ReservationEntitlement::find(${arranged.kept_entitlement_id});
      $buyerEntitlement = ReservationEntitlement::where('user_id', $buyer->id)
        ->where('reservation_slot_id', $sellerEntitlement->reservation_slot_id)
        ->latest('id')->firstOrFail();
      $transfer = EntitlementTransfer::where('marketplace_listing_id', $listing->id)->latest('id')->firstOrFail();

      // The arrival window opens at the reservation's first slot, not at the transferred second hour.
      Carbon::setTestNow($kept->starts_at->copy()->addMinutes(10));
      $attendance = app(AttendanceService::class);
      $sellerCheckinRejected = false;
      try {
        try {
          $attendance->checkIn($staff, ${orgId}, 'AURA_ENTITLEMENT_' . $sellerEntitlement->id, 'Seller after transfer', $seller);
        } catch (HttpException $e) {
          $sellerCheckinRejected = $e->getStatusCode() === 422;
        }
        $buyerCheckin = $attendance->checkIn($staff, ${orgId}, 'AURA_ENTITLEMENT_' . $buyerEntitlement->id, 'Buyer after transfer', $buyer);
      } finally {
        Carbon::setTestNow();
      }

      echo json_encode([
        'before_confirm_holder' => $beforeHolder,
        'listing_status' => $listing->fresh()->status,
        'seller_entitlement_status' => $sellerEntitlement->status,
        'kept_entitlement_status' => $kept->status,
        'kept_entitlement_user' => $kept->user_id,
        'buyer_entitlement_status' => $buyerEntitlement->status,
        'buyer_entitlement_amount' => $buyerEntitlement->amount,
        'transfer_status' => $transfer->status,
        'buyer_amount' => $transfer->buyer_amount,
        'marketplace_fee' => $transfer->marketplace_fee,
        'seller_refund' => $transfer->seller_refund,
        'settlement_mode' => data_get($transfer->metadata, 'settlement_mode'),
        'sale_journal_exists' => Journal::where('event_type', 'marketplace.sale')->where('event_id', 'marketplace:' . $listing->id)->exists(),
        'seller_checkin_rejected' => $sellerCheckinRejected,
        'buyer_attendance_status' => $buyerCheckin['attendance']->status,
      ]);
    `)

    expect(confirmed.before_confirm_holder).toBe(arranged.seller_id) // Report alone did not transfer
    expect(confirmed.listing_status).toBe('sold')
    expect(confirmed.seller_entitlement_status).toBe('transferred')
    expect(confirmed.buyer_entitlement_status).toBe('active')
    expect(confirmed.buyer_entitlement_amount).toBe(200000) // Original price preserved
    expect(confirmed.kept_entitlement_status).toBe('active') // The other hour stays with the seller
    expect(confirmed.kept_entitlement_user).toBe(arranged.seller_id)
    expect(confirmed.transfer_status).toBe('completed')
    expect(confirmed.buyer_amount).toBe(150000)
    expect(confirmed.marketplace_fee).toBe(0)
    expect(confirmed.seller_refund).toBe(0)
    expect(confirmed.settlement_mode).toBe('peer_to_peer')
    expect(confirmed.sale_journal_exists).toBe(false)
    expect(confirmed.seller_checkin_rejected).toBe(true)
    expect(confirmed.buyer_attendance_status).toBe('checked_in')

    // The buyer now reads the completed transfer, with no receiver or payment instructions left.
    const after = await call(page, 'GET', `/marketplace/listings/${arranged.listing_id}`)
    const afterState = after.body as unknown as ResaleState
    expect(afterState.status).toBe('sold')
    expect(afterState.transfer?.marketplace_fee).toBe(0)
    expect(afterState.transfer?.settlement_mode).toBe('peer_to_peer')
    expect(afterState.receiver).toBeNull()
    expect(afterState.payment).toBeNull()
  })

  test('7.9.4: Nhả giữ chỗ: người mua hủy trước khi báo, người bán báo chưa nhận tiền, hết hạn tự nhả, tranh chấp chặn chuyển', async () => {
    const account = uniqueAccount()
    const arranged = arrangeListing({ label: 'REL', asking: 120000, account, daysAhead: 7 })

    const result = runPhp<{
      after_buyer_release: string
      buyer_release_by: string | null
      relocked: string
      disputed_confirm_status: number | null
      disputed_holder: number
      after_seller_release: string
      seller_release: { by: string; payment_reported: boolean; reason: string | null } | null
      buyer_sees_release: { by: string; payment_reported: boolean } | null
      buyer_lock_after_release: unknown
      swept: number
      after_expiry: string
      expiry_release_by: string | null
    }>(`
      use App\\Models\\MarketplaceListing;
      use App\\Models\\Reservation;
      use App\\Models\\ReservationEntitlement;
      use App\\Models\\User;
      use App\\Services\\Social\\PeerToPeerResaleService;
      use Symfony\\Component\\HttpKernel\\Exception\\HttpException;

      $p2p = app(PeerToPeerResaleService::class);
      $listing = MarketplaceListing::find(${arranged.listing_id});
      $seller = User::find(${arranged.seller_id});
      $buyer = User::factory()->create();
      $buyer2 = User::factory()->create();

      // 1. The buyer cancels the hold before reporting payment.
      $p2p->lock($listing, $buyer);
      $released = $p2p->release($listing->fresh(), $buyer);

      // 2. Relock, report, then a dispute blocks confirmation without moving ownership.
      $relocked = $p2p->lock($listing->fresh(), $buyer);
      $p2p->paymentSent($listing->fresh(), $buyer);
      $reservation = Reservation::find(${arranged.reservation_id});
      $reservation->update(['status' => 'disputed']);
      $disputedStatus = null;
      try {
        $p2p->confirmReceipt($listing->fresh(), $seller);
      } catch (HttpException $e) {
        $disputedStatus = $e->getStatusCode();
      }
      $reservation->update(['status' => 'confirmed']);
      $disputedHolder = ReservationEntitlement::find(${arranged.entitlement_id})->user_id;

      // 3. The seller reports non-receipt: the lock is released and the buyer can see why.
      $sellerReleased = $p2p->release($listing->fresh(), $seller, 'Chưa thấy tiền về tài khoản');
      $buyerView = $p2p->state($listing->fresh(), $buyer);

      // 4. An unreported lock that lapses is released once by the sweep.
      $p2p->lock($listing->fresh(), $buyer2);
      $lapsed = $listing->fresh();
      $metadata = (array) $lapsed->metadata;
      $metadata['locked_until'] = now()->subMinute()->utc()->toIso8601String();
      $lapsed->forceFill(['metadata' => $metadata])->save();
      $swept = $p2p->sweep();
      $expired = $listing->fresh();

      echo json_encode([
        'after_buyer_release' => $released['status'],
        'buyer_release_by' => $released['last_release']['by'] ?? null,
        'relocked' => $relocked['status'],
        'disputed_confirm_status' => $disputedStatus,
        'disputed_holder' => $disputedHolder,
        'after_seller_release' => $sellerReleased['status'],
        'seller_release' => $sellerReleased['last_release'],
        'buyer_sees_release' => $buyerView['last_release'],
        'buyer_lock_after_release' => $buyerView['lock'],
        'swept' => $swept,
        'after_expiry' => $expired->effective_status,
        'expiry_release_by' => data_get($expired->metadata, 'last_release.by'),
      ]);
    `)

    expect(result.after_buyer_release).toBe('listed')
    expect(result.buyer_release_by).toBe('buyer')
    expect(result.relocked).toBe('checkout')
    expect(result.disputed_confirm_status).toBe(409)
    expect(result.disputed_holder).toBe(arranged.seller_id) // No ownership change
    expect(result.after_seller_release).toBe('listed')
    expect(result.seller_release).toMatchObject({
      by: 'seller',
      payment_reported: true,
      reason: 'Chưa thấy tiền về tài khoản',
    })
    expect(result.buyer_sees_release).toMatchObject({ by: 'seller', payment_reported: true })
    expect(result.buyer_lock_after_release).toBeNull()
    expect(result.swept).toBeGreaterThanOrEqual(1)
    expect(result.after_expiry).toBe('listed')
    expect(result.expiry_release_by).toBe('expiry')
  })

  test('7.9.5: Giao diện /resale: thẻ tin hiển thị, ghi rõ tiền đi thẳng cho người bán, giữ chỗ hiện QR rồi hủy', async ({
    page,
  }) => {
    const account = uniqueAccount()
    const arranged = arrangeListing({ label: 'UI', asking: 140000, account, daysAhead: 8 })

    await page.context().clearCookies()
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('input[type="email"], input[name="email"]').fill(customer.email)
    await page.locator('input[type="password"], input[name="password"]').fill(customer.password)
    await page.locator('button[type="submit"]').click()
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 })

    await page.goto('/resale', { waitUntil: 'domcontentloaded' })
    await expect(page.locator('.resale-marketplace-view')).toBeVisible({ timeout: 10000 })
    await expect(page.locator('.hero-title')).toContainText('Chợ Chuyển Nhượng & Pass Sân')
    await expect(page.locator('.marketplace-hero')).toContainText(
      'Người mua chuyển tiền thẳng cho người bán',
    )
    await expect(page.locator('.marketplace-hero')).not.toContainText('Escrow')

    const card = page.locator(
      `[data-testid="resale-listing-card"][data-listing-id="${arranged.listing_id}"]`,
    )
    await expect(card).toBeVisible({ timeout: 10000 })
    await expect(card).not.toContainText(account)

    // The buy panel states the direct-payment rule, then shows the seller's QR once held.
    await card.getByRole('button').click()
    const modal = page.locator('[data-testid="resale-buy-modal"]')
    await expect(modal.locator('[data-testid="resale-direct-notice"]')).toContainText(
      'Aura Court và chủ sân không thu, không giữ, không hoàn tiền này',
    )
    await expect(modal).not.toContainText(account)
    await modal.locator('[data-testid="resale-lock-btn"]').click()
    await expect(modal.locator('[data-testid="resale-qr"]')).toBeVisible({ timeout: 10000 })
    await expect(modal.locator('[data-testid="resale-pay-panel"]')).toContainText(account)
    await expect(modal.locator('[data-testid="resale-countdown"]')).toContainText('Giữ chỗ còn')

    await modal.getByRole('button', { name: 'Hủy giữ chỗ' }).click()
    await expect(modal.locator('[data-testid="resale-lock-btn"]')).toBeVisible({ timeout: 10000 })

    const status = runPhp<{ status: string }>(`
      echo json_encode(['status' => App\\Models\\MarketplaceListing::find(${arranged.listing_id})->effective_status]);
    `)
    expect(status.status).toBe('listed')
  })
})
