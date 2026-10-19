<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\LoyaltyRule;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PointLedger;
use App\Models\PriceRule;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Services\Payment\WalletPaymentService;
use App\Services\Reservation\LegacyBookingBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyCheckoutTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Organization, 1: User, 2: Court, 3: WalletAccount, 4: Venue} */
    private function setupCheckoutEnv(int $initialPoints = 2000, int $initialWallet = 500000): array
    {
        $org = Organization::create([
            'name' => 'Loyalty Org '.uniqid(),
            'slug' => 'loyalty-org-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        $user = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Loyalty Center',
            'slug' => 'loyalty-center-'.uniqid(),
            'is_active' => true,
            'status' => 'active',
        ]);

        $court = Court::create([
            'venue_id' => $venue->id,
            'sport_type' => 'badminton',
            'name' => 'Court 1',
            'is_active' => true,
        ]);
        // Court price slots: 200,000 VND for hourly slots
        for ($w = 1; $w <= 7; $w++) {
            for ($h = 6; $h <= 23; $h++) {
                CourtPriceSlot::create([
                    'court_id' => $court->id,
                    'weekday' => $w,
                    'starts_at' => sprintf('%02d:00', $h),
                    'ends_at' => sprintf('%02d:00', ($h + 1) % 24),
                    'price' => 200000,
                    'is_active' => true,
                ]);
            }
        }

        PriceRule::create([
            'organization_id' => $org->id,
            'court_id' => $court->id,
            'type' => 'base',
            'price' => 200000,
            'is_active' => true,
        ]);

        // Wallet
        $wallet = app(WalletPaymentService::class)->getOrCreateWallet($user->id, $org->id);
        if ($initialWallet > 0) {
            app(WalletPaymentService::class)->credit($wallet, $initialWallet, 'test_fund', 1, 'Initial funding');
        }

        // Points
        if ($initialPoints > 0) {
            PointLedger::create([
                'organization_id' => $org->id,
                'user_id' => $user->id,
                'loyalty_rule_id' => null,
                'delta' => $initialPoints,
                'balance_after' => $initialPoints,
                'source_type' => 'initial_seed',
                'source_id' => 1,
            ]);
        }

        // Base redemption rule: 100 points -> 5,000 VND
        LoyaltyRule::create([
            'organization_id' => $org->id,
            'name' => 'Standard Base Redemption',
            'version' => 1,
            'earn_points_per_100_vnd' => 10,
            'redeem_points_per_100_vnd' => 0,
            'status' => 'published',
            'metadata' => [
                'points_redeemed' => 100,
                'reward_value_vnd' => 5000,
            ],
            'published_at' => now(),
        ]);

        return [$org, $user, $court, $wallet, $venue];
    }

    public function test_atomic_points_and_wallet_checkout_from_slots(): void
    {
        $this->withoutExceptionHandling();
        [$org, $user, $court, $wallet] = $this->setupCheckoutEnv(2000, 500000);
        $date = now()->addDays(2)->setTime(10, 0);

        $res = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'slot-checkout-key-1')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $court->id,
                'slots' => [$date->toIso8601String()],
                'loyalty_points' => 100,
                'loyalty_policy_version' => 1,
            ]);

        $res->assertCreated();
        $res->assertJsonPath('reservation.status', 'confirmed');
        // Total amount payable is net: 200,000 - 5,000 = 195,000 VND
        $res->assertJsonPath('reservation.total_amount', 195000);

        $reservation = Reservation::where('code', $res->json('reservation.code'))->firstOrFail();

        // 1. Projections
        $this->assertSame(5000, $reservation->loyalty_discount_amount);
        $this->assertSame(100, $reservation->loyalty_points_redeemed);

        // 2. Wallet balance debited net 195,000 VND (500k - 195k = 305k)
        $this->assertSame(305000, $wallet->fresh()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_account_id' => $wallet->id,
            'amount' => 195000,
            'type' => 'debit',
            'source_type' => 'reservation_payment',
        ]);

        // 3. PointLedger debited 100 points (2000 - 100 = 1900)
        $userPointBalance = (int) PointLedger::where('organization_id', $org->id)->where('user_id', $user->id)->sum('delta');
        $this->assertSame(1900, $userPointBalance);
        $this->assertDatabaseHas('point_ledgers', [
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'delta' => -100,
            'balance_after' => 1900,
            'source_type' => 'reservation',
            'source_id' => $reservation->id,
        ]);

        // 4. Reservation snapshot captured
        $this->assertNotNull($reservation->metadata['loyalty_snapshot']);
        $this->assertSame(100, $reservation->metadata['loyalty_snapshot']['points_spent']);
        $this->assertSame(5000, $reservation->metadata['loyalty_snapshot']['discount_vnd']);
    }

    public function test_v2_atomic_points_and_wallet_checkout(): void
    {
        [$org, $user, $court, $wallet] = $this->setupCheckoutEnv(2000, 500000);
        $date = now()->addDays(3)->setTime(14, 0);

        // Create V2 quote
        $quoteRes = $this->actingAs($user)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->withHeader('Idempotency-Key', 'v2-quote-key-1')
            ->postJson('/api/price-quotes', [
                'court_id' => $court->id,
                'slots' => [$date->toIso8601String()],
            ]);
        $quoteRes->assertCreated();
        $quoteToken = $quoteRes->json('quote.quote_token');

        // V2 Checkout with 100 points
        $checkoutRes = $this->actingAs($user)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->withHeader('Idempotency-Key', 'v2-checkout-key-1')
            ->postJson('/api/reservations/checkout', [
                'quote_token' => $quoteToken,
                'loyalty_points' => 100,
                'loyalty_policy_version' => 1,
            ]);

        $checkoutRes->assertCreated();
        $checkoutRes->assertJsonPath('reservation.status', 'confirmed');
        $checkoutRes->assertJsonPath('reservation.total_amount', 195000);

        $resCode = $checkoutRes->json('reservation.code');
        $reservation = Reservation::where('code', $resCode)->firstOrFail();

        // 1. Projections on Reservation
        $this->assertSame(5000, $reservation->loyalty_discount_amount);
        $this->assertSame(100, $reservation->loyalty_points_redeemed);

        // 2. Wallet balance debited 195k
        $this->assertSame(305000, $wallet->fresh()->balance);

        // 3. PointLedger debited 100 points with source reservation
        $userPointBalance = (int) PointLedger::where('organization_id', $org->id)->where('user_id', $user->id)->sum('delta');
        $this->assertSame(1900, $userPointBalance);
        $this->assertDatabaseHas('point_ledgers', [
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'delta' => -100,
            'source_type' => 'reservation',
            'source_id' => $reservation->id,
        ]);
    }

    public function test_zero_payable_checkout_creates_no_wallet_debit(): void
    {
        // Setup: user has 4,000 points and 0 wallet balance.
        // Court slot is 200,000 VND.
        // Ratio: 100 pts -> 5,000 VND (1 pt = 50 VND).
        // 4,000 pts -> 200,000 VND discount -> Net payable: 0 VND!
        [$org, $user, $court, $wallet] = $this->setupCheckoutEnv(4000, 0);
        $this->assertSame(0, $wallet->balance);

        $date = now()->addDays(2)->setTime(16, 0);

        $res = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'zero-payable-key')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $court->id,
                'slots' => [$date->toIso8601String()],
                'loyalty_points' => 4000,
            ]);

        $res->assertCreated();
        $res->assertJsonPath('reservation.status', 'confirmed');
        $res->assertJsonPath('reservation.total_amount', 0);

        $reservation = Reservation::where('code', $res->json('reservation.code'))->firstOrFail();

        // 1. Wallet balance remains 0, zero wallet transactions created
        $this->assertSame(0, $wallet->fresh()->balance);
        $this->assertSame(0, WalletTransaction::where('wallet_account_id', $wallet->id)->count());

        // 2. PointLedger debited exactly 4,000 points (balance: 0)
        $userPointBalance = (int) PointLedger::where('organization_id', $org->id)->where('user_id', $user->id)->sum('delta');
        $this->assertSame(0, $userPointBalance);

        // 3. The points cover the full price and no wallet payment is posted
        $this->assertSame(200000, $reservation->loyalty_discount_amount);
        $this->assertSame(4000, $reservation->loyalty_points_redeemed);
        $this->assertDatabaseMissing('journals', ['event_type' => 'reservation.wallet_payment', 'reference' => $reservation->code]);
    }

    public function test_insufficient_wallet_balance_rolls_back_point_debit(): void
    {
        // Setup: user has 2,000 points and only 50,000 VND in wallet.
        // Slot is 200,000 VND. 100 points gives 5,000 VND discount.
        // Net payable is 195,000 VND > 50,000 VND wallet balance.
        [$org, $user, $court, $wallet] = $this->setupCheckoutEnv(2000, 50000);

        $date = now()->addDays(2)->setTime(18, 0);

        $res = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'fail-wallet-key')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $court->id,
                'slots' => [$date->toIso8601String()],
                'loyalty_points' => 100,
            ]);

        $res->assertStatus(422);

        // Point balance must remain exactly 2,000 (no partial point debit!)
        $userPointBalance = (int) PointLedger::where('organization_id', $org->id)->where('user_id', $user->id)->sum('delta');
        $this->assertSame(2000, $userPointBalance);
        $this->assertSame(1, PointLedger::where('user_id', $user->id)->count());

        // Wallet balance remains 50,000
        $this->assertSame(50000, $wallet->fresh()->balance);

        // No reservation created
        $this->assertSame(0, Reservation::where('user_id', $user->id)->count());
    }

    public function test_inventory_conflict_rolls_back_point_debit(): void
    {
        [$org, $user, $court, $wallet] = $this->setupCheckoutEnv(2000, 500000);
        $date = now()->addDays(2)->setTime(19, 0);

        // Other user books this slot first
        $otherUser = User::factory()->create();
        $existingBooking = Booking::create([
            'code' => 'BKEXISTING01',
            'user_id' => $otherUser->id,
            'court_id' => $court->id,
            'total_amount' => 200000,
            'status' => 'confirmed',
        ]);
        BookingSlot::create([
            'booking_id' => $existingBooking->id,
            'court_id' => $court->id,
            'starts_at' => $date->copy()->utc(),
            'ends_at' => $date->copy()->addHour()->utc(),
            'unit_price' => 200000,
            'status' => 'confirmed',
        ]);
        app(LegacyBookingBackfillService::class)->backfillBooking($existingBooking);

        // Now user attempts to book the conflicting slot with points
        $res = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'conflict-key')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $court->id,
                'slots' => [$date->toIso8601String()],
                'loyalty_points' => 100,
            ]);

        $res->assertStatus(409);

        // Points must NOT be debited
        $userPointBalance = (int) PointLedger::where('organization_id', $org->id)->where('user_id', $user->id)->sum('delta');
        $this->assertSame(2000, $userPointBalance);

        // Wallet must NOT be debited
        $this->assertSame(500000, $wallet->fresh()->balance);
    }

    public function test_two_competing_checkouts_prevent_overdraw(): void
    {
        // User has only 100 points in balance.
        [$org, $user, $court, $wallet] = $this->setupCheckoutEnv(100, 500000);

        $date1 = now()->addDays(2)->setTime(8, 0);
        $date2 = now()->addDays(2)->setTime(9, 0);

        // 1. First checkout succeeds with 100 points
        $res1 = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'compete-key-1')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $court->id,
                'slots' => [$date1->toIso8601String()],
                'loyalty_points' => 100,
            ]);
        $res1->assertCreated();

        // Balance is now 0
        $this->assertSame(0, (int) PointLedger::where('organization_id', $org->id)->where('user_id', $user->id)->sum('delta'));

        // 2. Second checkout attempting to use 100 points must FAIL with 422
        $res2 = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'compete-key-2')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $court->id,
                'slots' => [$date2->toIso8601String()],
                'loyalty_points' => 100,
            ]);
        $res2->assertStatus(422);

        // Balance remains 0, never negative!
        $this->assertSame(0, (int) PointLedger::where('organization_id', $org->id)->where('user_id', $user->id)->sum('delta'));
    }

    public function test_idempotent_replay_returns_existing_reservation_without_duplicate_debit(): void
    {
        [$org, $user, $court, $wallet] = $this->setupCheckoutEnv(2000, 500000);
        $date = now()->addDays(4)->setTime(10, 0);

        $payload = [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
            'loyalty_points' => 100,
            'loyalty_policy_version' => 1,
        ];

        // 1. Initial request
        $res1 = $this->actingAs($user)->withHeader('Idempotency-Key', 'stable-user-replay-key')->postJson('/api/reservations/checkout', $payload);
        $res1->assertCreated();
        $code1 = $res1->json('reservation.code');

        // Wallet debited 195k, points debited 100
        $this->assertSame(305000, $wallet->fresh()->balance);
        $this->assertSame(1900, (int) PointLedger::where('user_id', $user->id)->sum('delta'));

        // 2. Replay identical request with same key
        $res2 = $this->actingAs($user)->withHeader('Idempotency-Key', 'stable-user-replay-key')->postJson('/api/reservations/checkout', $payload);
        $res2->assertSuccessful();
        $this->assertSame($code1, $res2->json('reservation.code'));

        // Assert NO duplicate wallet debit and NO duplicate point debit!
        $this->assertSame(305000, $wallet->fresh()->balance);
        $this->assertSame(1900, (int) PointLedger::where('user_id', $user->id)->sum('delta'));
        $this->assertSame(1, PointLedger::where('source_type', 'reservation')->count());
        $this->assertSame(1, WalletTransaction::where('source_type', 'reservation_payment')->count());

        // 3. Conflicting request with same key but different slots fails with 409
        $conflictPayload = $payload;
        $conflictPayload['slots'] = [now()->addDays(4)->setTime(11, 0)->toIso8601String()];
        $resConflict = $this->actingAs($user)->withHeader('Idempotency-Key', 'stable-user-replay-key')->postJson('/api/reservations/checkout', $conflictPayload);
        $resConflict->assertStatus(409);
    }

    public function test_checkout_without_points_has_no_point_regression(): void
    {
        [$org, $user, $court, $wallet] = $this->setupCheckoutEnv(2000, 500000);
        $date = now()->addDays(5)->setTime(15, 0);

        // Ordinary checkout without points continues to work 100% normally
        $res = $this->actingAs($user)->withHeader('Idempotency-Key', 'no-points-key')->postJson('/api/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
        ]);
        $res->assertCreated();
        $res->assertJsonPath('reservation.total_amount', 200000);
        $this->assertSame(300000, $wallet->fresh()->balance);

        // Point balance untouched (still 2000)
        $this->assertSame(2000, (int) PointLedger::where('user_id', $user->id)->sum('delta'));
    }
}
