<?php

namespace Tests\Feature\Booking;

use App\Models\Court;
use App\Models\LoyaltyRule;
use App\Models\MembershipEntitlement;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PointLedger;
use App\Models\Reservation;
use App\Models\TenantMembership;
use App\Models\TenantMembershipPlan;
use App\Models\User;
use App\Models\Venue;
use App\Models\WalletAccount;
use App\Services\Payment\WalletPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\PricesCourts;
use Tests\TestCase;

/**
 * Court bookings neither spend nor earn points and give no membership discount (design D21): the rental is the
 * whole payable. Bookings made earlier with points still show what they redeemed.
 */
class CheckoutWithoutLoyaltyTest extends TestCase
{
    use PricesCourts;
    use RefreshDatabase;

    public function test_member_with_points_books_at_the_full_rental_without_point_hold_debit_or_earn(): void
    {
        [$organization, $user, $court, $wallet] = $this->tenant();

        $quote = $this->command($user, $organization, '/api/price-quotes', $this->slots($court))
            ->assertCreated()
            ->assertJsonPath('quote.subtotal', 200000)
            ->assertJsonPath('quote.total_amount', 200000)
            ->assertJsonPath('quote.benefit_snapshot', null)
            ->json('quote');

        $code = $this->command($user, $organization, '/api/reservations/checkout', ['quote_token' => $quote['quote_token'], 'expected_total' => 200000])
            ->assertCreated()
            ->assertJsonPath('reservation.status', 'confirmed')
            ->assertJsonPath('reservation.total_amount', 200000)
            ->assertJsonPath('reservation.loyalty_discount_amount', 0)
            ->assertJsonPath('reservation.loyalty_points_redeemed', 0)
            ->json('reservation.code');

        $reservation = Reservation::where('code', $code)->firstOrFail();
        $this->assertArrayNotHasKey('loyalty_snapshot', (array) $reservation->metadata);
        $this->assertArrayNotHasKey('coupon_usage', (array) $reservation->metadata);
        $this->assertSame(800000, (int) $wallet->fresh()->balance);
        // Only the seed lot: no hold, debit or earn for the booking.
        $this->assertSame(1, PointLedger::where('user_id', $user->id)->count());
        $this->assertSame(2000, (int) PointLedger::where('user_id', $user->id)->sum('delta'));
    }

    public function test_quote_hold_and_checkout_naming_points_are_refused_and_create_nothing(): void
    {
        [$organization, $user, $court, $wallet] = $this->tenant();

        foreach (['/api/price-quotes', '/api/reservations', '/api/reservations/checkout'] as $uri) {
            $this->command($user, $organization, $uri, $this->slots($court) + ['loyalty_points' => 100, 'expected_total' => 195000])
                ->assertUnprocessable()
                ->assertJsonPath('errors.loyalty_points.0', 'Đặt sân không còn dùng điểm tích lũy.');
        }

        $this->assertDatabaseCount('price_quotes', 0);
        $this->assertDatabaseCount('reservations', 0);
        $this->assertSame(1000000, (int) $wallet->fresh()->balance);
        $this->assertSame(1, PointLedger::where('user_id', $user->id)->count());
    }

    public function test_a_booking_made_with_points_before_still_shows_its_redemption(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $reservation = Reservation::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'court_id' => $court->id,
            'code' => 'RSV-PTS-'.Str::upper(Str::random(8)),
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => 195000,
            'payment_model' => 'legacy_wallet_v1',
            'metadata' => [
                'loyalty_discount_amount' => 5000,
                'loyalty_points_redeemed' => 100,
                'loyalty_snapshot' => ['points_spent' => 100, 'discount_vnd' => 5000],
            ],
        ]);

        $this->actingAs($user)->withHeader('X-Organization-ID', (string) $organization->id)
            ->getJson("/api/reservations/{$reservation->code}")
            ->assertOk()
            ->assertJsonPath('reservation.total_amount', 195000)
            ->assertJsonPath('reservation.loyalty_discount_amount', 5000)
            ->assertJsonPath('reservation.loyalty_points_redeemed', 100);
    }

    public function test_debit_only_redeem_endpoint_rejected_with_422_and_zero_mutation(): void
    {
        [$organization, $user] = $this->tenant();
        $initialLedgerCount = PointLedger::count();

        $this->actingAs($user)->postJson('/api/loyalty/redeem', [
            'points' => 100,
            'source_type' => 'custom',
            'source_id' => 999,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'DIRECT_REDEMPTION_RETIRED')
            ->assertJsonPath('message', 'Quy đổi điểm trực tiếp đã ngừng hỗ trợ. Đặt sân không còn dùng điểm; điểm vẫn được tích khi mua hàng tại quầy.');

        $this->assertSame($initialLedgerCount, PointLedger::count());
        $this->assertSame(2000, (int) PointLedger::where('organization_id', $organization->id)->where('user_id', $user->id)->sum('delta'));
    }

    /**
     * A customer with 2.000 points, an earn and redemption rule, an active 10% membership and 1.000.000 ₫ in the
     * wallet, on a court priced 200.000 ₫/h.
     *
     * @return array{0: Organization, 1: User, 2: Court, 3: WalletAccount}
     */
    private function tenant(): array
    {
        $organization = Organization::create([
            'name' => 'No Loyalty Club',
            'slug' => 'no-loyalty-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $venue = Venue::create(['organization_id' => $organization->id, 'name' => 'No Loyalty Venue', 'slug' => 'no-loyalty-venue-'.Str::lower(Str::random(8)), 'status' => 'published', 'is_active' => true]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'No Loyalty Court', 'is_active' => true]);
        $this->priceCourt($court, 200000);
        $user = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);

        LoyaltyRule::create([
            'organization_id' => $organization->id,
            'name' => 'Earn and redeem',
            'version' => 1,
            'earn_points_per_100_vnd' => 10,
            'redeem_points_per_100_vnd' => 0,
            'status' => 'published',
            'metadata' => ['points_redeemed' => 100, 'reward_value_vnd' => 5000],
            'published_at' => now(),
        ]);
        PointLedger::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'loyalty_rule_id' => null,
            'delta' => 2000,
            'balance_after' => 2000,
            'source_type' => 'seed',
            'source_id' => 1,
        ]);
        $plan = TenantMembershipPlan::create([
            'organization_id' => $organization->id,
            'name' => 'Gold',
            'version' => 1,
            'price' => 100000,
            'benefits' => ['discount_percent' => 10],
            'status' => 'published',
            'published_at' => now(),
        ]);
        $membership = TenantMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan_version' => $plan->version,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);
        MembershipEntitlement::create([
            'organization_id' => $organization->id,
            'membership_id' => $membership->id,
            'benefit_code' => 'discount_percent',
            'benefit_value' => ['value' => 10, 'plan_version' => $plan->version],
            'starts_at' => $membership->starts_at,
            'ends_at' => $membership->ends_at,
        ]);

        $wallets = app(WalletPaymentService::class);
        $wallet = $wallets->getOrCreateWallet($user->id, $organization->id);
        $wallets->credit($wallet, 1000000, 'test_topup', 1, 'Fund for test');

        return [$organization, $user, $court, $wallet];
    }

    /** @return array{court_id: int, slots: array<int, string>} */
    private function slots(Court $court): array
    {
        return ['court_id' => $court->id, 'slots' => [now(config('app.timezone'))->addDays(3)->setTime(10, 0)->toIso8601String()]];
    }

    private function command(User $user, Organization $organization, string $uri, array $payload): TestResponse
    {
        return $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson($uri, $payload);
    }
}
