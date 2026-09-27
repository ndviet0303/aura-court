<?php

namespace Tests\Feature;

use App\Domain\Booking\DTOs\CreateBookingData;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\LoyaltyRule;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PointLedger;
use App\Models\PriceQuote;
use App\Models\PriceRule;
use App\Models\Reservation;
use App\Models\SportZone;
use App\Models\User;
use App\Models\Venue;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Services\Booking\OrdinaryCheckoutService;
use App\Services\Payment\WalletPaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoyaltyCheckoutTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Organization, 1: User, 2: Court, 3: WalletAccount, 4: Venue} */
    private function setupCheckoutEnv(int $initialPoints = 2000, int $initialWallet = 500000): array
    {
        $org = Organization::create([
            'name' => 'Loyalty Org ' . uniqid(),
            'slug' => 'loyalty-org-' . uniqid(),
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
            'slug' => 'loyalty-center-' . uniqid(),
            'is_active' => true,
            'status' => 'active',
        ]);

        $sportZone = SportZone::create([
            'venue_id' => $venue->id,
            'name' => 'Badminton Zone',
            'sport_type' => 'badminton',
            'slot_duration' => 60,
            'is_active' => true,
        ]);
        $court = Court::create([
            'venue_id' => $venue->id,
            'sport_zone_id' => $sportZone->id,
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

    public function test_v1_atomic_points_and_wallet_checkout(): void
    {
        $this->withoutExceptionHandling();
        [$org, $user, $court, $wallet] = $this->setupCheckoutEnv(2000, 500000);
        $date = now()->addDays(2)->setTime(10, 0);

        $res = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'v1-checkout-key-1')
            ->postJson('/api/v1/bookings', [
                'court_id' => $court->id,
                'slots' => [$date->toIso8601String()],
                'loyalty_points' => 100,
                'loyalty_policy_version' => 1,
            ]);

        $res->assertCreated();
        $res->assertJsonPath('booking.status', 'confirmed');
        // Total amount payable is net: 200,000 - 5,000 = 195,000 VND
        $res->assertJsonPath('booking.total_amount', 195000);

        $bookingCode = $res->json('booking.code');
        $booking = Booking::where('code', $bookingCode)->firstOrFail();

        // 1. Projections
        $this->assertSame(5000, $booking->loyalty_discount_amount);
        $this->assertSame(100, $booking->loyalty_points_redeemed);

        // 2. Wallet balance debited net 195,000 VND (500k - 195k = 305k)
        $this->assertSame(305000, $wallet->fresh()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_account_id' => $wallet->id,
            'amount' => 195000,
            'type' => 'debit',
            'source_type' => 'booking_payment',
        ]);

        // 3. PointLedger debited 100 points (2000 - 100 = 1900)
        $userPointBalance = (int) PointLedger::where('organization_id', $org->id)->where('user_id', $user->id)->sum('delta');
        $this->assertSame(1900, $userPointBalance);
        $this->assertDatabaseHas('point_ledgers', [
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'delta' => -100,
            'balance_after' => 1900,
            'source_type' => 'booking',
            'source_id' => $booking->id,
        ]);

        // 4. Booking snapshot captured
        $this->assertNotNull($booking->metadata['loyalty_snapshot']);
        $this->assertSame(100, $booking->metadata['loyalty_snapshot']['points_spent']);
        $this->assertSame(5000, $booking->metadata['loyalty_snapshot']['discount_vnd']);

        // 5. Canonical V2 reservation mapping created
        $this->assertDatabaseHas('legacy_booking_mappings', [
            'legacy_booking_id' => $booking->id,
            'organization_id' => $org->id,
        ]);
    }

    public function test_v2_atomic_points_and_wallet_checkout(): void
    {
        [$org, $user, $court, $wallet] = $this->setupCheckoutEnv(2000, 500000);
        $date = now()->addDays(3)->setTime(14, 0);

        // Create V2 quote
        $quoteRes = $this->actingAs($user)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->withHeader('Idempotency-Key', 'v2-quote-key-1')
            ->postJson('/api/v2/price-quotes', [
                'court_id' => $court->id,
                'slots' => [$date->toIso8601String()],
            ]);
        $quoteRes->assertCreated();
        $quoteToken = $quoteRes->json('quote.quote_token');

        // V2 Checkout with 100 points
        $checkoutRes = $this->actingAs($user)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->withHeader('Idempotency-Key', 'v2-checkout-key-1')
            ->postJson('/api/v2/reservations/checkout', [
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
            ->withHeader('Idempotency-Key', 'v1-zero-payable-key')
            ->postJson('/api/v1/bookings', [
                'court_id' => $court->id,
                'slots' => [$date->toIso8601String()],
                'loyalty_points' => 4000,
            ]);

        $res->assertCreated();
        $res->assertJsonPath('booking.status', 'confirmed');
        $res->assertJsonPath('booking.total_amount', 0);

        $bookingCode = $res->json('booking.code');
        $booking = Booking::where('code', $bookingCode)->firstOrFail();

        // 1. Wallet balance remains 0, zero wallet transactions created
        $this->assertSame(0, $wallet->fresh()->balance);
        $this->assertSame(0, WalletTransaction::where('wallet_account_id', $wallet->id)->count());

        // 2. PointLedger debited exactly 4,000 points (balance: 0)
        $userPointBalance = (int) PointLedger::where('organization_id', $org->id)->where('user_id', $user->id)->sum('delta');
        $this->assertSame(0, $userPointBalance);

        // 3. Payment record reflects fully discounted zero payable
        $payment = $booking->payments()->firstOrFail();
        $this->assertSame('loyalty_points', $payment->provider);
        $this->assertSame(0, $payment->amount);
        $this->assertSame('paid', $payment->status);
        $this->assertTrue($payment->payload['fully_discounted'] ?? false);
    }

    public function test_insufficient_wallet_balance_rolls_back_point_debit(): void
    {
        // Setup: user has 2,000 points and only 50,000 VND in wallet.
        // Slot is 200,000 VND. 100 points gives 5,000 VND discount.
        // Net payable is 195,000 VND > 50,000 VND wallet balance.
        [$org, $user, $court, $wallet] = $this->setupCheckoutEnv(2000, 50000);

        $date = now()->addDays(2)->setTime(18, 0);

        $res = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'v1-fail-wallet-key')
            ->postJson('/api/v1/bookings', [
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

        // No booking created
        $this->assertSame(0, Booking::where('user_id', $user->id)->count());
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

        // Now user attempts to book the conflicting slot with points
        $res = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'v1-conflict-key')
            ->postJson('/api/v1/bookings', [
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
            ->postJson('/api/v1/bookings', [
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
            ->postJson('/api/v1/bookings', [
                'court_id' => $court->id,
                'slots' => [$date2->toIso8601String()],
                'loyalty_points' => 100,
            ]);
        $res2->assertStatus(422);

        // Balance remains 0, never negative!
        $this->assertSame(0, (int) PointLedger::where('organization_id', $org->id)->where('user_id', $user->id)->sum('delta'));
    }

    public function test_idempotent_replay_returns_existing_booking_without_duplicate_debit(): void
    {
        [$org, $user, $court, $wallet] = $this->setupCheckoutEnv(2000, 500000);
        $date = now()->addDays(4)->setTime(10, 0);

        $payload = [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
            'loyalty_points' => 100,
            'loyalty_policy_version' => 1,
            'idempotency_key' => 'stable-user-replay-key',
        ];

        // 1. Initial request
        $res1 = $this->actingAs($user)->postJson('/api/v1/bookings', $payload);
        $res1->assertCreated();
        $code1 = $res1->json('booking.code');

        // Wallet debited 195k, points debited 100
        $this->assertSame(305000, $wallet->fresh()->balance);
        $this->assertSame(1900, (int) PointLedger::where('user_id', $user->id)->sum('delta'));

        // 2. Replay identical request with same key
        $res2 = $this->actingAs($user)->postJson('/api/v1/bookings', $payload);
        $res2->assertSuccessful();
        $this->assertSame($code1, $res2->json('booking.code'));

        // Assert NO duplicate wallet debit and NO duplicate point debit!
        $this->assertSame(305000, $wallet->fresh()->balance);
        $this->assertSame(1900, (int) PointLedger::where('user_id', $user->id)->sum('delta'));
        $this->assertSame(1, PointLedger::where('source_type', 'booking')->count());
        $this->assertSame(1, WalletTransaction::where('source_type', 'booking_payment')->count());

        // 3. Conflicting request with same key but different slots fails with 409
        $conflictPayload = $payload;
        $conflictPayload['slots'] = [now()->addDays(4)->setTime(11, 0)->toIso8601String()];
        $resConflict = $this->actingAs($user)->postJson('/api/v1/bookings', $conflictPayload);
        $resConflict->assertStatus(409);
    }

    public function test_v1_and_v2_parity_no_point_regression(): void
    {
        [$org, $user, $court, $wallet] = $this->setupCheckoutEnv(2000, 500000);
        $date = now()->addDays(5)->setTime(15, 0);

        // Ordinary V1 without points continues to work 100% normally
        $res = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
        ]);
        $res->assertCreated();
        $res->assertJsonPath('booking.total_amount', 200000);
        $this->assertSame(300000, $wallet->fresh()->balance);

        // Point balance untouched (still 2000)
        $this->assertSame(2000, (int) PointLedger::where('user_id', $user->id)->sum('delta'));
    }
}
