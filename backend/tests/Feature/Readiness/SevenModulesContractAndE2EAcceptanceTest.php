<?php

namespace Tests\Feature\Readiness;

use App\Models\Booking;
use App\Models\CashShift;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\DisputeCase;
use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\FeedReport;
use App\Models\FinanceException;
use App\Models\ModerationRecord;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\OwnerRefundObligation;
use App\Models\PosProduct;
use App\Models\PriceRule;
use App\Models\ReceivingConnection;
use App\Models\ReconciliationRun;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\SplitGroup;
use App\Models\SplitShare;
use App\Models\SportZone;
use App\Models\Tournament;
use App\Models\TournamentFixture;
use App\Models\TournamentGrant;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Models\WalletAccount;
use App\Services\Booking\BookingService;
use App\Services\Payment\SplitPaymentService;
use App\Services\Payment\WalletPaymentService;
use App\Services\POS\PosService;
use App\Services\Tournament\TournamentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task 13.1 Acceptance Test:
 * Comprehensive contract & real operational E2E assertions for 7 modules:
 * 1. Core Booking & Availability (401, 422, 409, 200/201 with reload, 404)
 * 2. Advanced Booking & Split/Recurring (422 unverified receipt, 409 expired group, 200 preview calculation)
 * 3. POS Shift & Orders (422 invalid items/negative price, 409 shift close with pending orders, 200 cash in/out and balanced close)
 * 4. Social Feed & Moderation (422 past time/invalid skill, 401/403 unauthorized hide, 200 reasoned report dismissal)
 * 5. Tournaments (403 unauthorized score, 409 score modify on finalized tournament, 200 official score progression)
 * 6. Support & Dispute Cases (422 expired window > 30 days, 409 duplicate dispute, 409 close with unsettled refund obligation)
 * 7. Financial Ledger & Reconciliation (422 note-only resolve without evidence, 403 unauthorized venue export, 200 streamed CSV)
 */
class SevenModulesContractAndE2EAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'features.pilot_only' => [],
            'features.flags.tenant_pos_and_wallet' => true,
            'features.flags.social_matchmaking_tournaments' => true,
            'features.flags.direct_owner_payments' => false,
            'features.flags.marketplace_split_payments' => true,
            'features.flags.marketplace_payouts' => true,
            'services.payments.simulate_qr_provider' => true,
        ]);
    }

    // =========================================================================
    // Module 1 - Core Booking & Availability
    // =========================================================================

    public function test_module_1_core_booking_401_when_unauthenticated(): void
    {
        $response = $this->postJson('/api/v1/bookings', [
            'court_id' => 1,
            'slots' => [now()->addDay()->setTime(10, 0)->toIso8601String()],
        ]);

        $response->assertUnauthorized();
    }

    public function test_module_1_core_booking_422_when_invalid_date_or_court_format(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Malformed court_id and slots
        $res1 = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'court_id' => 'not-a-valid-id',
            'slots' => 'not-an-array',
        ]);
        $res1->assertStatus(422)
            ->assertJsonValidationErrors(['court_id', 'slots']);

        // Malformed date strings inside slots array
        $res2 = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'court_id' => 999999,
            'slots' => ['invalid-datetime-string'],
        ]);
        $res2->assertStatus(422);
    }

    public function test_module_1_core_booking_409_when_slot_is_already_booked_or_held(): void
    {
        [$org, $venue, $court] = $this->createTenantWithCourt('Module 1 Org');
        $user1 = User::factory()->create(['email_verified_at' => now()]);
        $user2 = User::factory()->create(['email_verified_at' => now()]);

        $targetDate = now(config('app.timezone'))->addDays(3)->setTime(14, 0)->startOfHour();
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => $targetDate->dayOfWeekIso,
            'starts_at' => '14:00',
            'ends_at' => '15:00',
            'price' => 150000,
            'is_active' => true,
        ]);

        $walletService = app(WalletPaymentService::class);
        $wallet1 = $walletService->getOrCreateWallet($user1->id, $org->id);
        $walletService->credit($wallet1, 500000, 'test_topup', 1, 'Topup 1');

        $wallet2 = $walletService->getOrCreateWallet($user2->id, $org->id);
        $walletService->credit($wallet2, 500000, 'test_topup', 2, 'Topup 2');

        // User 1 books the slot successfully
        $res1 = $this->actingAs($user1)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$targetDate->toIso8601String()],
        ]);
        $res1->assertCreated();

        // User 2 attempts to book the exact same slot -> 409 Conflict
        $res2 = $this->actingAs($user2)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$targetDate->toIso8601String()],
        ]);
        $res2->assertStatus(409);
    }

    public function test_module_1_core_booking_200_on_valid_booking_with_reload_and_404_checks(): void
    {
        [$org, $venue, $court] = $this->createTenantWithCourt('Module 1 Success Org');
        $user = User::factory()->create(['email_verified_at' => now()]);

        $targetDate = now(config('app.timezone'))->addDays(4)->setTime(16, 0)->startOfHour();
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => $targetDate->dayOfWeekIso,
            'starts_at' => '16:00',
            'ends_at' => '17:00',
            'price' => 200000,
            'is_active' => true,
        ]);

        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id, $org->id);
        $walletService->credit($wallet, 600000, 'test_topup', 1, 'Topup');

        $initialBalance = (int) $wallet->fresh()->balance;

        $res = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$targetDate->toIso8601String()],
        ]);
        $res->assertCreated();
        $code = $res->json('booking.code');
        $this->assertNotEmpty($code);

        // Reload check: Database state
        $booking = Booking::where('code', $code)->firstOrFail();
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame(200000, (int) $booking->fresh()->total_amount);
        $this->assertSame($initialBalance - 200000, (int) $wallet->fresh()->balance);

        // Reload check: HTTP show endpoint returns confirmed booking
        $showRes = $this->actingAs($user)->getJson("/api/v1/bookings/{$code}");
        $showRes->assertOk()
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('code', $code);

        // 404 check: Non-existent booking lookup returns 404
        $this->actingAs($user)->getJson('/api/v1/bookings/BK-DOES-NOT-EXIST')->assertNotFound();

        // 404 check: Retired cancellation endpoint returns 404 and does not mutate booking
        $this->actingAs($user)->postJson("/api/v1/bookings/{$code}/cancel")->assertNotFound();
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    // =========================================================================
    // Module 2 - Advanced Booking & Split/Recurring
    // =========================================================================

    public function test_module_2_split_payment_422_when_lacks_verified_provider_receipt(): void
    {
        $fixture = $this->createSplitTenantFixture();
        $reservation = $this->createHeldReservation($fixture, 200000);

        $group = app(SplitPaymentService::class)->create($reservation, $fixture['host'], [
            ['amount' => 100000],
            ['amount' => 100000],
        ]);
        $share = $group->shares->first();

        // Payer attempts to pay with unverified payment reference -> 422
        $response = $this->actingAs($fixture['payer'])
            ->withHeaders([
                'X-Organization-ID' => (string) $fixture['organization']->id,
                'Idempotency-Key' => 'split-pay-unverified-' . uniqid(),
            ])
            ->postJson('/api/v2/split-shares/' . $share->id . '/pay', [
                'amount' => 100000,
                'payment_reference' => 'UNVERIFIED-FAKE-PROVIDER-REF-999',
            ]);

        $response->assertStatus(422);

        // Reload check: Share status remains pending, not paid
        $this->assertSame('pending', $share->fresh()->status);
        $this->assertSame(0, $group->fresh()->paid_amount);
    }

    public function test_module_2_split_payment_409_when_split_group_is_expired(): void
    {
        $fixture = $this->createSplitTenantFixture();
        $reservation = $this->createHeldReservation($fixture, 200000);

        $group = app(SplitPaymentService::class)->create($reservation, $fixture['host'], [
            ['amount' => 100000],
            ['amount' => 100000],
        ]);
        $share = $group->shares->first();

        // Force expiration
        $group->update(['expires_at' => now()->subMinutes(15)]);
        $share->update(['expires_at' => now()->subMinutes(15)]);

        $response = $this->actingAs($fixture['payer'])
            ->withHeaders([
                'X-Organization-ID' => (string) $fixture['organization']->id,
                'Idempotency-Key' => 'split-pay-expired-' . uniqid(),
            ])
            ->postJson('/api/v2/split-shares/' . $share->id . '/pay', [
                'amount' => 100000,
                'payment_reference' => 'ANY-REF',
            ]);

        $response->assertStatus(409);
        $this->assertSame('pending', $share->fresh()->status);
    }

    public function test_module_2_recurring_series_200_on_preview_and_preserves_zero_mutation(): void
    {
        $fixture = $this->createSplitTenantFixture();
        $futureMonday = now(config('app.timezone'))->addWeeks(3)->startOfWeek(Carbon::MONDAY)->setTime(9, 0);

        $res = $this->actingAs($fixture['host'])
            ->postJson('/api/v2/recurring-series/preview', [
                'court_id' => $fixture['court']->id,
                'frequency' => 'weekly',
                'weekdays' => [1, 3],
                'starts_at' => $futureMonday->toIso8601String(),
                'occurrences' => 4,
            ]);

        $res->assertOk()
            ->assertJsonPath('count', 4)
            ->assertJsonPath('currency', 'VND')
            ->assertJsonPath('total_amount', 4 * 200000)
            ->assertJsonStructure(['occurrences', 'total_amount', 'currency']);

        // Invariant: Preview is strictly read-only, 0 reservations created
        $this->assertDatabaseMissing('reservations', ['court_id' => $fixture['court']->id, 'payment_model' => 'direct_owner_v1']);
    }

    // =========================================================================
    // Module 3 - POS Shift & Orders
    // =========================================================================

    public function test_module_3_pos_422_on_invalid_line_items_or_negative_price(): void
    {
        [$org, $venue, $cashier] = $this->createPosTenantFixture();

        // 1. Negative product price in product management -> 422
        $badProductRes = $this->actingAs($cashier)->postJson('/api/v2/pos/products', [
            'sku' => 'NEG-PRICE-' . uniqid(),
            'name' => 'Negative Price Drink',
            'price' => -15000,
        ], ['X-Organization-Id' => $org->id]);
        $badProductRes->assertStatus(422)
            ->assertJsonValidationErrors(['price']);

        // Open shift for cashier to test order line items
        $shift = app(PosService::class)->openShift($cashier, $org->id, 100000, $venue->id, 'REG-01');

        $product = PosProduct::create([
            'organization_id' => $org->id,
            'sku' => 'WATER-01',
            'name' => 'Water Bottle',
            'price' => 10000,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);

        // 2. Negative quantity in order items -> 422
        $badOrderRes1 = $this->actingAs($cashier)->postJson('/api/v2/pos/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => -2],
            ],
            'payment_method' => 'cash',
        ], [
            'X-Organization-Id' => $org->id,
            'Idempotency-Key' => 'bad-order-' . uniqid(),
        ]);
        $badOrderRes1->assertStatus(422);

        // 3. Empty items array -> 422
        $badOrderRes2 = $this->actingAs($cashier)->postJson('/api/v2/pos/orders', [
            'items' => [],
            'payment_method' => 'cash',
        ], [
            'X-Organization-Id' => $org->id,
            'Idempotency-Key' => 'empty-order-' . uniqid(),
        ]);
        $badOrderRes2->assertStatus(422);
    }

    public function test_module_3_pos_409_when_closing_shift_with_pending_orders_without_disposition(): void
    {
        [$org, $venue, $cashier] = $this->createPosTenantFixture();
        $shift = app(PosService::class)->openShift($cashier, $org->id, 100000, $venue->id, 'REG-02');

        $product = PosProduct::create([
            'organization_id' => $org->id,
            'sku' => 'SNACK-01',
            'name' => 'Energy Bar',
            'price' => 20000,
            'stock_quantity' => 20,
            'is_active' => true,
        ]);

        // Create pending QR order
        $orderRes = $this->actingAs($cashier)->postJson('/api/v2/pos/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'payment_method' => 'qr',
        ], [
            'X-Organization-Id' => $org->id,
            'Idempotency-Key' => 'pending-qr-' . uniqid(),
        ]);
        $orderRes->assertCreated();

        // Attempting to close shift without disposition fails with 409
        $failClose = $this->actingAs($cashier)->postJson("/api/v2/pos/shifts/{$shift->id}/close", [
            'declared_cash' => 100000,
        ], [
            'X-Organization-Id' => $org->id,
            'Idempotency-Key' => 'close-fail-' . uniqid(),
        ]);
        $failClose->assertStatus(409);
        $this->assertStringContainsString('without disposition', $failClose->json('message'));

        // Reload check: Shift remains open
        $this->assertSame('open', $shift->fresh()->status);
    }

    public function test_module_3_pos_200_on_cash_movements_and_balanced_shift_close_with_idempotent_network_retry(): void
    {
        [$org, $venue, $cashier] = $this->createPosTenantFixture();
        $shift = app(PosService::class)->openShift($cashier, $org->id, 100000, $venue->id, 'REG-03');

        // Cash In: +50,000 float
        $cashInRes = $this->actingAs($cashier)->postJson("/api/v2/pos/shifts/{$shift->id}/cash-movement", [
            'type' => 'cash_in',
            'amount' => 50000,
            'reason' => 'Midday change float',
        ], [
            'X-Organization-Id' => $org->id,
            'Idempotency-Key' => 'cash-in-' . uniqid(),
        ]);
        $this->assertTrue(in_array($cashInRes->status(), [200, 201], true));

        // Cash Out: -20,000 cleaning supplies
        $cashOutRes = $this->actingAs($cashier)->postJson("/api/v2/pos/shifts/{$shift->id}/cash-movement", [
            'type' => 'cash_out',
            'amount' => 20000,
            'reason' => 'Emergency mop replacement',
        ], [
            'X-Organization-Id' => $org->id,
            'Idempotency-Key' => 'cash-out-' . uniqid(),
        ]);
        $this->assertTrue(in_array($cashOutRes->status(), [200, 201], true));

        // Expected cash: 100,000 + 50,000 - 20,000 = 130,000
        $closeKey = 'close-shift-balanced-' . uniqid();
        $closeRes = $this->actingAs($cashier)->postJson("/api/v2/pos/shifts/{$shift->id}/close", [
            'declared_cash' => 130000,
            'note' => 'Shift balanced',
        ], [
            'X-Organization-Id' => $org->id,
            'Idempotency-Key' => $closeKey,
        ]);
        $closeRes->assertOk();
        $this->assertSame(130000, (int) $closeRes->json('shift.expected_cash'));
        $this->assertSame(0, (int) $closeRes->json('shift.variance'));

        // Reload check: Shift is closed in database
        $this->assertSame('closed', $shift->fresh()->status);
        $this->assertSame(130000, (int) $shift->fresh()->expected_cash);

        // Network retry check: replaying exact same Idempotency-Key returns cached response
        $retryRes = $this->actingAs($cashier)->postJson("/api/v2/pos/shifts/{$shift->id}/close", [
            'declared_cash' => 130000,
            'note' => 'Shift balanced',
        ], [
            'X-Organization-Id' => $org->id,
            'Idempotency-Key' => $closeKey,
        ]);
        $retryRes->assertOk();
        $this->assertSame('closed', $shift->fresh()->status);
    }

    // =========================================================================
    // Module 4 - Social Feed & Moderation
    // =========================================================================

    public function test_module_4_social_422_on_activity_with_invalid_skill_level_or_past_time(): void
    {
        [$org, $venue, $owner, $customer] = $this->createSocialTenantFixture();

        // 1. Past start time -> 422
        $pastRes = $this->actingAs($customer)->postJson('/api/v2/social/matches', [
            'title' => 'Past Match Activity',
            'starts_at' => now()->subDays(2)->toIso8601String(),
            'ends_at' => now()->subDays(2)->addHour()->toIso8601String(),
            'required_participants' => 4,
        ], [
            'X-Organization-Id' => (string) $org->id,
            'Idempotency-Key' => 'past-match-' . uniqid(),
        ]);
        $pastRes->assertStatus(422);

        // 2. Inverted skill levels (min_skill > max_skill) -> 422
        $skillRes = $this->actingAs($customer)->postJson('/api/v2/social/matches', [
            'title' => 'Invalid Skill Match',
            'starts_at' => now()->addDays(2)->toIso8601String(),
            'ends_at' => now()->addDays(2)->addHour()->toIso8601String(),
            'min_skill' => 5,
            'max_skill' => 2,
            'required_participants' => 4,
        ], [
            'X-Organization-Id' => (string) $org->id,
            'Idempotency-Key' => 'bad-skill-' . uniqid(),
        ]);
        $skillRes->assertStatus(422);
    }

    public function test_module_4_social_401_and_403_on_unauthorized_post_hide(): void
    {
        [$org, $venue, $owner, $customer] = $this->createSocialTenantFixture();
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $post = FeedPost::create([
            'organization_id' => $org->id,
            'user_id' => $customer->id,
            'audience' => FeedPost::AUDIENCE_ORGANIZATION,
            'content' => 'Public community discussion post',
            'is_hidden' => false,
        ]);

        // 1. Unauthenticated -> 401
        $this->postJson("/api/v2/social/feed/{$post->id}/hide")->assertUnauthorized();

        // 2. Customer without owner/venue_manager role -> 403
        $unauthRes = $this->actingAs($customer)->postJson("/api/v2/social/feed/{$post->id}/hide", [], [
            'X-Organization-Id' => (string) $org->id,
        ]);
        $unauthRes->assertStatus(403);

        // Reload check: Post remains visible
        $this->assertFalse((bool) $post->fresh()->is_hidden);
    }

    public function test_module_4_social_200_on_reasoned_moderation_dismissal_with_reload_check(): void
    {
        [$org, $venue, $owner, $customer] = $this->createSocialTenantFixture();

        $post = FeedPost::create([
            'organization_id' => $org->id,
            'user_id' => $customer->id,
            'audience' => FeedPost::AUDIENCE_ORGANIZATION,
            'content' => 'Post under false report review',
            'is_hidden' => false,
        ]);

        $report = FeedReport::create([
            'organization_id' => $org->id,
            'reporter_id' => $customer->id,
            'reportable_type' => FeedPost::class,
            'reportable_id' => $post->id,
            'reason' => 'False spam claim',
            'status' => FeedReport::STATUS_PENDING,
        ]);

        // Unreasoned dismissal (< 5 chars) -> 422
        $badDismiss = $this->actingAs($owner)->postJson("/api/v2/social/feed/reports/{$report->id}/dismiss", [
            'reason' => 'no',
        ], ['X-Organization-Id' => (string) $org->id]);
        $badDismiss->assertStatus(422);

        // Reasoned moderation dismissal by owner -> 200 OK
        $goodDismiss = $this->actingAs($owner)->postJson("/api/v2/social/feed/reports/{$report->id}/dismiss", [
            'reason' => 'Content complies fully with community standards and court guidelines.',
        ], ['X-Organization-Id' => (string) $org->id]);
        $goodDismiss->assertOk();

        // Reload check: Report status is dismissed and moderation audit record is logged
        $this->assertSame(FeedReport::STATUS_DISMISSED, $report->fresh()->status);
        $this->assertSame('dismiss', $report->fresh()->action_taken);
        $this->assertSame($owner->id, $report->fresh()->reviewed_by);

        $this->assertDatabaseHas('moderation_records', [
            'organization_id' => $org->id,
            'actor_id' => $owner->id,
            'target_type' => FeedPost::class,
            'target_id' => $post->id,
            'action' => 'keep',
        ]);
    }

    // =========================================================================
    // Module 5 - Tournaments
    // =========================================================================

    public function test_module_5_tournament_403_when_reporting_score_without_grant(): void
    {
        [$org, $owner, $official, $stranger, $tourney] = $this->createTournamentFixture();
        $fixture = $tourney->fixtures()->first();

        // Stranger without organizer/official grant -> 403
        $response = $this->actingAs($stranger)->postJson("/api/v2/tournaments/{$tourney->id}/fixtures/{$fixture->id}/score", [
            'expected_version' => $tourney->version,
            'winner_id' => $fixture->participant_one_id,
            'score' => ['p1' => 21, 'p2' => 18],
        ], [
            'X-Organization-Id' => (string) $org->id,
            'Idempotency-Key' => 'score-unauth-' . uniqid(),
        ]);

        $response->assertStatus(403);
    }

    public function test_module_5_tournament_200_on_official_score_and_409_on_finalized_fixture_modification(): void
    {
        [$org, $owner, $official, $stranger, $tourney] = $this->createTournamentFixture();
        $fixture = $tourney->fixtures()->first();

        // 1. Official score submission -> 200 OK
        $scoreRes = $this->actingAs($official)->postJson("/api/v2/tournaments/{$tourney->id}/fixtures/{$fixture->id}/score", [
            'expected_version' => $tourney->version,
            'winner_id' => $fixture->participant_one_id,
            'score' => ['p1' => 21, 'p2' => 14],
        ], [
            'X-Organization-Id' => (string) $org->id,
            'Idempotency-Key' => 'score-official-' . uniqid(),
        ]);
        $scoreRes->assertOk();

        // Reload check: Fixture updated
        $freshFixture = $fixture->fresh();
        $this->assertSame($fixture->participant_one_id, $freshFixture->winner_id);
        $this->assertSame('completed', $freshFixture->status);

        // 2. Finalize tournament
        $finRes = $this->actingAs($owner)->postJson("/api/v2/tournaments/{$tourney->id}/finalize", [], [
            'X-Organization-Id' => (string) $org->id,
            'Idempotency-Key' => 'fin-' . uniqid(),
        ]);
        $finRes->assertOk();
        $this->assertSame('finalized', $tourney->fresh()->status);

        // 3. Modifying fixture of a finalized tournament returns 409 Conflict
        $lockRes = $this->actingAs($owner)->postJson("/api/v2/tournaments/{$tourney->id}/fixtures/{$fixture->id}/score", [
            'expected_version' => $tourney->fresh()->version,
            'winner_id' => $fixture->participant_two_id,
            'score' => ['p1' => 14, 'p2' => 21],
            'reason' => 'Attempting post-finalization edit',
        ], [
            'X-Organization-Id' => (string) $org->id,
            'Idempotency-Key' => 'score-locked-' . uniqid(),
        ]);
        $lockRes->assertStatus(409);
    }

    // =========================================================================
    // Module 6 - Support & Dispute Cases
    // =========================================================================

    public function test_module_6_dispute_422_when_filing_for_expired_window_over_30_days(): void
    {
        [$org, $venue, $court, $customer] = $this->createDisputeFixture();

        // Booking ended 35 days ago (> 30 days window)
        $expiredBooking = $this->createCompletedBooking($customer, $court, now()->subDays(35));

        $res = $this->actingAs($customer)->postJson('/api/v1/disputes', [
            'title' => 'Expired Booking Dispute',
            'summary' => 'Nội dung tóm tắt khiếu nại đã quá hạn 30 ngày theo quy định pháp lý.',
            'dispute_type' => 'court_quality',
            'booking_id' => $expiredBooking->id,
        ]);

        $res->assertStatus(422)
            ->assertJsonFragment(['message' => 'Filing window has expired for this booking.']);
    }

    public function test_module_6_dispute_409_on_duplicate_dispute_for_same_booking(): void
    {
        [$org, $venue, $court, $customer] = $this->createDisputeFixture();
        $booking = $this->createCompletedBooking($customer, $court, now()->subDays(5));

        // First dispute filing -> 201 Created
        $res1 = $this->actingAs($customer)->postJson('/api/v1/disputes', [
            'title' => 'Sân chất lượng kém',
            'summary' => 'Mặt sân trơn trượt ngập nước không thi đấu được an toàn.',
            'dispute_type' => 'court_quality',
            'booking_id' => $booking->id,
        ]);
        $res1->assertStatus(201);
        $disputeId = $res1->json('data.id');

        // Second dispute filing for exact same booking while first is active -> 409 Conflict
        $res2 = $this->actingAs($customer)->postJson('/api/v1/disputes', [
            'title' => 'Khiếu nại trùng lặp',
            'summary' => 'Cố gắng tạo thêm hồ sơ khiếu nại thứ hai cho cùng một đơn đặt sân.',
            'dispute_type' => 'payment_issue',
            'booking_id' => $booking->id,
        ]);

        $res2->assertStatus(409)
            ->assertJsonFragment([
                'message' => 'A dispute is already active for this booking.',
                'dispute_id' => $disputeId,
            ]);
    }

    public function test_module_6_dispute_409_when_closing_with_unsettled_refund_obligation(): void
    {
        [$org, $venue, $court, $customer, $admin] = $this->createDisputeFixtureWithAdmin();
        $booking = $this->createCompletedBooking($customer, $court, now()->subDays(3));

        $obligation = OwnerRefundObligation::create([
            'organization_id' => $org->id,
            'venue_id' => $venue->id,
            'payer_id' => $customer->id,
            'reference_code' => 'OBL-DISP-' . uniqid(),
            'idempotency_key' => 'IDEM-OBL-' . uniqid(),
            'request_fingerprint' => hash('sha256', uniqid()),
            'slot_ids' => [1],
            'gross_amount' => 150000,
            'fee_amount' => 0,
            'amount' => 150000,
            'currency' => 'VND',
            'status' => OwnerRefundObligation::STATUS_APPROVED, // Unsettled!
            'reason' => 'Dispute refund ruling',
            'policy_snapshot' => ['type' => 'dispute'],
            'recipient_ciphertext' => Crypt::encryptString(json_encode(['name' => $customer->name])),
            'due_at' => now()->addDays(2),
            'requested_at' => now(),
            'requested_by' => $customer->id,
        ]);

        $dispute = DisputeCase::create([
            'organization_id' => $org->id,
            'venue_id' => $venue->id,
            'user_id' => $customer->id,
            'dispute_code' => 'DSP-' . strtoupper(uniqid()),
            'booking_id' => $booking->id,
            'dispute_type' => 'court_quality',
            'status' => 'resolved',
            'severity' => 'high',
            'title' => 'Dispute with pending refund obligation',
            'summary' => 'Dispute resolved with approved refund obligation awaiting payment.',
            'refund_obligation_id' => $obligation->id,
            'sla_deadline' => now()->addDays(1),
        ]);

        // 1. Attempting to close while obligation is unsettled (approved) -> 409 Conflict
        $closeFail = $this->actingAs($admin)->postJson("/api/v1/admin/disputes/{$dispute->id}/close");
        $closeFail->assertStatus(409)
            ->assertJsonFragment([
                'message' => 'Cannot close dispute: pending refund obligation must be executed or settled first.',
            ]);

        // Reload check: Status remains resolved
        $this->assertSame('resolved', $dispute->fresh()->status);

        // 2. Mark obligation as acknowledged (settled)
        $obligation->update([
            'status' => OwnerRefundObligation::STATUS_ACKNOWLEDGED,
            'recipient_acknowledged_at' => now(),
        ]);

        // 3. Closing dispute now succeeds -> 200 OK
        $closeSuccess = $this->actingAs($admin)->postJson("/api/v1/admin/disputes/{$dispute->id}/close");
        $closeSuccess->assertOk();

        // Reload check: Dispute is closed
        $this->assertSame('closed', $dispute->fresh()->status);

        // 404 check: Non-existent dispute close
        $this->actingAs($admin)->postJson('/api/v1/admin/disputes/999999/close')->assertNotFound();
    }

    // =========================================================================
    // Module 7 - Financial Ledger & Reconciliation
    // =========================================================================

    public function test_module_7_finance_422_when_auto_resolving_reconciliation_mismatch_note_only(): void
    {
        [$org, $venue, $owner] = $this->createFinanceFixture();

        $run = ReconciliationRun::create([
            'organization_id' => $org->id,
            'run_date' => now()->toDateString(),
            'status' => 'mismatch',
            'provider_total' => 0,
            'journal_total' => 0,
            'settlement_total' => 0,
            'payout_total' => 0,
            'mismatch_amount' => 150000,
            'started_at' => now(),
            'details' => ['mismatches' => ['unmatched_booking' => 150000]],
        ]);

        $exception = FinanceException::create([
            'organization_id' => $org->id,
            'reference' => 'reconciliation:' . now()->toDateString(),
            'type' => 'reconciliation_mismatch',
            'severity' => 'critical',
            'amount' => 150000,
            'currency' => 'VND',
            'status' => 'open',
            'description' => 'Unallocated bank receipt mismatch',
            'detected_at' => now(),
            'links' => ['reconciliation_run_id' => $run->id],
        ]);

        // 1. Auto-resolve without evidence_reference -> 422 Unprocessable Content
        $autoResolveRes = $this->actingAs($owner)->postJson('/api/v2/finance/reconciliation/auto-resolve', [
            'run_id' => $run->id,
            'notes' => 'Yêu cầu kế toán tự động bù trừ chỉ bằng ghi chú văn bản',
        ], ['X-Organization-Id' => (string) $org->id]);

        $autoResolveRes->assertStatus(422)
            ->assertJsonValidationErrors(['evidence_reference']);

        // 2. Manual exception resolve endpoint without evidence_reference -> 422 Unprocessable Content
        $manualResolveRes = $this->actingAs($owner)->postJson("/api/v2/finance/exceptions/{$exception->id}/resolve", [
            'action_type' => 'manual_override',
            'reason' => 'Ghi chú giải trình kiểm toán thủ công không đính kèm chứng từ sao kê',
            'note' => 'Cố gắng bypass D47 bằng note thủ công',
        ], [
            'X-Organization-Id' => (string) $org->id,
            'Idempotency-Key' => 'manual-resolve-note-only-' . uniqid(),
        ]);

        $manualResolveRes->assertStatus(422)
            ->assertJsonValidationErrors(['evidence_reference']);

        // Reload check: Exception status remains open, mismatch amount not zeroed
        $this->assertSame('open', $exception->fresh()->status);
        $this->assertSame('mismatch', $run->fresh()->status);
        $this->assertSame(150000, (int) $run->fresh()->mismatch_amount);
    }

    public function test_module_7_finance_403_when_exporting_unauthorized_venue_and_200_on_authorized_streaming_csv(): void
    {
        [$orgA, $venueA, $ownerA] = $this->createFinanceFixture('Tenant Alpha');
        [$orgB, $venueB, $ownerB] = $this->createFinanceFixture('Tenant Beta');

        // Create an active booking in Venue A so the export has real rows
        $customer = User::factory()->create(['email' => 'csvcustomer@aura.local']);
        $courtA = $venueA->courts()->firstOrFail();
        Booking::create([
            'court_id' => $courtA->id,
            'user_id' => $customer->id,
            'code' => 'BK-CSV-' . strtoupper(uniqid()),
            'status' => 'confirmed',
            'total_amount' => 250000,
            'created_at' => now(),
        ]);

        // 1. Owner A attempts to export reports of unauthorized Venue B -> 403 Forbidden
        $unauthExport = $this->actingAs($ownerA)
            ->getJson('/api/v1/admin/reports/csv?venue_id=' . $venueB->id);
        $unauthExport->assertStatus(403);

        // 2. Owner A exports reports of authorized Venue A -> 200 OK Streaming CSV
        $authExport = $this->actingAs($ownerA)
            ->get('/api/v1/admin/reports/csv?venue_id=' . $venueA->id);
        $authExport->assertOk();

        // Verify real streamed CSV content with headers and non-empty rows
        $content = $authExport->streamedContent();
        $this->assertNotEmpty($content);
        $lines = array_filter(explode("\n", trim($content)));
        $this->assertGreaterThanOrEqual(2, count($lines)); // Header + at least 1 booking row

        // Assert CSV header
        $header = str_getcsv($lines[0]);
        $this->assertContains('code', $header);
        $this->assertContains('source_type', $header);
        $this->assertContains('gross_amount', $header);
    }

    // =========================================================================
    // Fixture Helpers
    // =========================================================================

    private function createTenantWithCourt(string $name): array
    {
        $org = Organization::create([
            'name' => $name,
            'slug' => Str::slug($name) . '-' . uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => $name . ' Venue',
            'slug' => Str::slug($name) . '-venue-' . uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);

        $court = Court::create([
            'venue_id' => $venue->id,
            'name' => 'Court 1',
            'is_active' => true,
        ]);

        PriceRule::create([
            'organization_id' => $org->id,
            'court_id' => $court->id,
            'type' => 'base',
            'price' => 150000,
        ]);

        return [$org, $venue, $court];
    }

    private function createSplitTenantFixture(): array
    {
        $organization = Organization::create([
            'name' => 'Split Org',
            'slug' => 'split-org-' . uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $owner = User::factory()->create();
        $host = User::factory()->create(['email_verified_at' => now()]);
        $payer = User::factory()->create(['email_verified_at' => now()]);

        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $host->id, 'role' => 'customer', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $payer->id, 'role' => 'customer', 'status' => 'active']);

        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => 'Split Venue',
            'slug' => 'split-venue-' . uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = $venue->courts()->create(['name' => 'Court 1', 'is_active' => true]);
        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court->id, 'type' => 'base', 'price' => 200000]);

        VenueBookingPolicy::create([
            'venue_id' => $venue->id,
            'version' => 1,
            'created_by' => $owner->id,
            'effective_at' => now(),
            'settings' => [
                'booking' => ['hold_ttl_minutes' => 15, 'max_days_ahead' => 30, 'min_duration_minutes' => 60, 'max_duration_minutes' => 240, 'require_continuity' => true],
                'recurring' => ['payment_policy' => 'per_period', 'conflict_policy' => 'all_or_nothing', 'overdue_release_hours' => 24],
            ],
        ]);

        $connection = new ReceivingConnection([
            'organization_id' => $organization->id,
            'venue_id' => $venue->id,
            'owner_user_id' => $owner->id,
            'provider' => 'sepay',
            'routing_id' => (string) Str::uuid(),
            'bank_code' => 'MBBank',
            'status' => ReceivingConnection::STATUS_VERIFIED,
            'credential_version' => 1,
            'owner_attested_at' => now(),
            'verified_at' => now(),
            'effective_from' => now(),
        ]);
        $connection->setAccountDetails('Split Owner', '0987654321');
        $connection->webhook_secret_ciphertext = Crypt::encryptString('whsec_' . Str::random(48));
        $connection->save();
        $venue->forceFill(['current_receiving_connection_id' => $connection->id])->save();

        config([
            'features.flags.direct_owner_payments' => true,
            'features.flags.marketplace_split_payments' => true,
            'features.pilot_organizations' => array_values(array_unique(array_merge(
                (array) config('features.pilot_organizations', []),
                [(string) $organization->id]
            ))),
        ]);

        return compact('organization', 'owner', 'host', 'payer', 'venue', 'court', 'connection');
    }

    private function createHeldReservation(array $fixture, int $amount): Reservation
    {
        $reservation = Reservation::create([
            'organization_id' => $fixture['organization']->id,
            'user_id' => $fixture['host']->id,
            'court_id' => $fixture['court']->id,
            'code' => 'RSV-SPLIT-' . Str::upper(Str::random(8)),
            'status' => 'held',
            'currency' => 'VND',
            'total_amount' => $amount,
            'payment_model' => 'direct_owner_v1',
            'hold_expires_at' => now()->addMinutes(15),
        ]);

        $slot = ReservationSlot::create([
            'organization_id' => $fixture['organization']->id,
            'reservation_id' => $reservation->id,
            'court_id' => $fixture['court']->id,
            'starts_at' => now()->addDays(2)->setTime(10, 0),
            'ends_at' => now()->addDays(2)->setTime(11, 0),
            'unit_price' => $amount,
            'status' => 'held',
            'snapshot' => [],
        ]);

        ReservationEntitlement::create([
            'organization_id' => $fixture['organization']->id,
            'reservation_id' => $reservation->id,
            'reservation_slot_id' => $slot->id,
            'court_id' => $fixture['court']->id,
            'user_id' => $fixture['host']->id,
            'entitlement_type' => 'court_reservation',
            'status' => 'held',
            'starts_at' => now()->addDays(2)->setTime(10, 0),
            'ends_at' => now()->addDays(2)->setTime(11, 0),
            'amount' => $amount,
        ]);

        return $reservation;
    }

    private function createPosTenantFixture(): array
    {
        $org = Organization::create([
            'name' => 'POS Org',
            'slug' => 'pos-org-' . uniqid(),
            'status' => 'active',
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'POS Arena',
            'slug' => 'pos-arena-' . uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);

        $cashier = User::factory()->create(['name' => 'Cashier']);
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $cashier->id,
            'role' => OrganizationMember::ROLE_CASHIER,
            'status' => 'active',
        ]);

        VenueBookingPolicy::create([
            'venue_id' => $venue->id,
            'version' => 1,
            'created_by' => $cashier->id,
            'effective_at' => now(),
            'settings' => ['pos' => ['allow_mixed_tender' => true]],
        ]);

        return [$org, $venue, $cashier];
    }

    private function createSocialTenantFixture(): array
    {
        $org = Organization::create([
            'name' => 'Social Org',
            'slug' => 'social-org-' . uniqid(),
            'status' => 'active',
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Social Stadium',
            'slug' => 'social-stadium-' . uniqid(),
            'status' => 'published',
            'is_active' => true,
            'timezone' => 'Asia/Ho_Chi_Minh',
        ]);

        $owner = User::factory()->create(['name' => 'Social Owner']);
        $customer = User::factory()->create(['name' => 'Social Customer']);

        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER, 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $customer->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);

        return [$org, $venue, $owner, $customer];
    }

    private function createTournamentFixture(): array
    {
        $org = Organization::create([
            'name' => 'Tournament Org',
            'slug' => 'tourney-org-' . uniqid(),
            'status' => 'active',
        ]);

        $owner = User::factory()->create(['name' => 'Tournament Owner']);
        $official = User::factory()->create(['name' => 'Tournament Official']);
        $stranger = User::factory()->create(['name' => 'Tournament Stranger']);
        $p1 = User::factory()->create(['name' => 'Player 1']);
        $p2 = User::factory()->create(['name' => 'Player 2']);

        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $official->id, 'role' => 'customer', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $stranger->id, 'role' => 'customer', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $p1->id, 'role' => 'customer', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $p2->id, 'role' => 'customer', 'status' => 'active']);

        WalletAccount::create(['organization_id' => $org->id, 'user_id' => $p1->id, 'currency' => 'VND', 'balance' => 500000, 'status' => 'active']);
        WalletAccount::create(['organization_id' => $org->id, 'user_id' => $p2->id, 'currency' => 'VND', 'balance' => 500000, 'status' => 'active']);

        $service = app(TournamentService::class);
        $tourney = $service->createAndGenerate($owner, $org->id, 'Aura Championship', 'single_elimination', [$p1->id, $p2->id]);

        TournamentGrant::create([
            'organization_id' => $org->id,
            'tournament_id' => $tourney->id,
            'user_id' => $official->id,
            'role' => 'official',
            'granted_by' => $owner->id,
        ]);

        return [$org, $owner, $official, $stranger, $tourney];
    }

    private function createDisputeFixture(): array
    {
        $org = Organization::create([
            'name' => 'Dispute Org',
            'slug' => 'disp-org-' . uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        $owner = User::factory()->create(['name' => 'Dispute Owner']);
        $customer = User::factory()->create(['name' => 'Dispute Customer', 'role' => 'customer', 'email_verified_at' => now()]);

        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER, 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $customer->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Dispute Arena',
            'slug' => 'disp-venue-' . uniqid(),
            'is_active' => true,
        ]);

        $court = Court::create([
            'venue_id' => $venue->id,
            'name' => 'Dispute Court',
            'is_active' => true,
        ]);

        return [$org, $venue, $court, $customer];
    }

    private function createDisputeFixtureWithAdmin(): array
    {
        [$org, $venue, $court, $customer] = $this->createDisputeFixture();

        $admin = User::factory()->create([
            'name' => 'Platform Admin',
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        return [$org, $venue, $court, $customer, $admin];
    }

    private function createCompletedBooking(User $customer, Court $court, Carbon $endedAt): Booking
    {
        $booking = Booking::create([
            'code' => 'BK-' . strtoupper(uniqid()),
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'total_amount' => 200000,
            'status' => 'confirmed',
            'created_at' => (clone $endedAt)->subHour(),
        ]);

        $booking->slots()->create([
            'court_id' => $court->id,
            'starts_at' => (clone $endedAt)->subHour(),
            'ends_at' => $endedAt,
            'unit_price' => 200000,
            'status' => 'confirmed',
        ]);

        return $booking;
    }

    private function createFinanceFixture(string $name = 'Finance Org'): array
    {
        $org = Organization::create([
            'name' => $name,
            'slug' => Str::slug($name) . '-' . uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        $owner = User::factory()->create([
            'role' => 'customer',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        $org->update(['user_id' => $owner->id]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => $name . ' Stadium',
            'slug' => Str::slug($name) . '-venue-' . uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);

        $court = Court::create([
            'venue_id' => $venue->id,
            'name' => 'Court 1',
            'is_active' => true,
        ]);

        return [$org, $venue, $owner];
    }
}
