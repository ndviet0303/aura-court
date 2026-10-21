<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\LoyaltyRule;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PointLedger;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Services\Payment\WalletPaymentService;
use App\Services\Reservation\LegacyBookingBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PricesCourts;
use Tests\TestCase;

class LoyaltyCheckoutTest extends TestCase
{
    use PricesCourts;
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
        $this->priceCourt($court, 200000);

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

    public function test_checkout_naming_points_is_refused_and_creates_nothing(): void
    {
        [$org, $user, $court, $wallet] = $this->setupCheckoutEnv(4000, 500000);
        $date = now()->addDays(3)->setTime(14, 0);
        $quoteToken = $this->actingAs($user)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->withHeader('Idempotency-Key', 'v2-quote-key-1')
            ->postJson('/api/price-quotes', ['court_id' => $court->id, 'slots' => [$date->toIso8601String()]])
            ->assertCreated()
            ->json('quote.quote_token');

        // Slot checkout, quote checkout and a points-only (0 đ) checkout: court bookings no longer redeem points.
        foreach ([
            ['court_id' => $court->id, 'slots' => [$date->toIso8601String()], 'expected_total' => 195000, 'loyalty_points' => 100, 'loyalty_policy_version' => 1],
            ['quote_token' => $quoteToken, 'expected_total' => 195000, 'loyalty_points' => 100],
            ['court_id' => $court->id, 'slots' => [$date->toIso8601String()], 'expected_total' => 0, 'loyalty_points' => 4000],
        ] as $i => $payload) {
            $this->actingAs($user)
                ->withHeader('X-Organization-ID', (string) $org->id)
                ->withHeader('Idempotency-Key', "points-checkout-{$i}")
                ->postJson('/api/reservations/checkout', $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['loyalty_points' => 'Đặt sân không còn dùng điểm tích lũy.']);
        }

        $this->assertSame(0, Reservation::where('user_id', $user->id)->count());
        $this->assertSame(500000, $wallet->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('wallet_account_id', $wallet->id)->count());
        $this->assertSame(1, PointLedger::where('user_id', $user->id)->count());
        $this->assertSame(4000, (int) PointLedger::where('user_id', $user->id)->sum('delta'));
    }

    public function test_insufficient_wallet_balance_rolls_back_debit(): void
    {
        // The 200,000 VND slot costs more than the 50,000 VND wallet balance.
        [, $user, $court, $wallet] = $this->setupCheckoutEnv(2000, 50000);
        $date = now()->addDays(2)->setTime(18, 0);

        $res = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'fail-wallet-key')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $court->id,
                'expected_total' => 200000,
                'slots' => [$date->toIso8601String()],
            ]);

        $res->assertStatus(422);
        $this->assertSame(50000, $wallet->fresh()->balance);
        $this->assertSame(0, Reservation::where('user_id', $user->id)->count());
    }

    public function test_inventory_conflict_rolls_back_debit(): void
    {
        [, $user, $court, $wallet] = $this->setupCheckoutEnv(2000, 500000);
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

        $res = $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'conflict-key')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $court->id,
                'expected_total' => 200000,
                'slots' => [$date->toIso8601String()],
            ]);

        $res->assertStatus(409);
        $this->assertSame(500000, $wallet->fresh()->balance);
        $this->assertSame(0, Reservation::where('user_id', $user->id)->count());
    }

    public function test_two_competing_checkouts_prevent_overdraw(): void
    {
        // The wallet covers one 200,000 VND slot, not two.
        [, $user, $court, $wallet] = $this->setupCheckoutEnv(0, 300000);
        $date1 = now()->addDays(2)->setTime(8, 0);
        $date2 = now()->addDays(2)->setTime(9, 0);

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'compete-key-1')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $court->id,
                'expected_total' => 200000,
                'slots' => [$date1->toIso8601String()],
            ])
            ->assertCreated();
        $this->assertSame(100000, $wallet->fresh()->balance);

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', 'compete-key-2')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $court->id,
                'expected_total' => 200000,
                'slots' => [$date2->toIso8601String()],
            ])
            ->assertStatus(422);

        // Balance never goes negative and only the first booking exists.
        $this->assertSame(100000, $wallet->fresh()->balance);
        $this->assertSame(1, Reservation::where('user_id', $user->id)->count());
    }

    public function test_idempotent_replay_returns_existing_reservation_without_duplicate_debit(): void
    {
        [, $user, $court, $wallet] = $this->setupCheckoutEnv(2000, 500000);
        $date = now()->addDays(4)->setTime(10, 0);

        $payload = [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
            'expected_total' => 200000,
        ];

        $res1 = $this->actingAs($user)->withHeader('Idempotency-Key', 'stable-user-replay-key')->postJson('/api/reservations/checkout', $payload);
        $res1->assertCreated();
        $code1 = $res1->json('reservation.code');
        $this->assertSame(300000, $wallet->fresh()->balance);

        // Replay identical request with same key
        $res2 = $this->actingAs($user)->withHeader('Idempotency-Key', 'stable-user-replay-key')->postJson('/api/reservations/checkout', $payload);
        $res2->assertSuccessful();
        $this->assertSame($code1, $res2->json('reservation.code'));
        $this->assertSame(300000, $wallet->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('source_type', 'reservation_payment')->count());

        // Same key with different slots conflicts
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
            'expected_total' => 200000,
            'slots' => [$date->toIso8601String()],
        ]);
        $res->assertCreated();
        $res->assertJsonPath('reservation.total_amount', 200000);
        $this->assertSame(300000, $wallet->fresh()->balance);

        // Point balance untouched (still 2000)
        $this->assertSame(2000, (int) PointLedger::where('user_id', $user->id)->sum('delta'));
    }
}
