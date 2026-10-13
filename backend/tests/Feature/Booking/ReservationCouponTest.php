<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\Actions\ExpirePendingBookings;
use App\Models\Coupon;
use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PriceRule;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\WalletPaymentService;
use App\Services\Reservation\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Coupons on the reservation flow: a quote validates the code, hold and checkout take one use under lock
 * (metadata.coupon_usage), confirmation keeps it and an unpaid hold gives it back exactly once.
 */
class ReservationCouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_with_a_coupon_returns_the_discounted_total_and_its_coupon_snapshot(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $coupon = $this->coupon($organization);

        $this->command($user, $organization, '/api/price-quotes', $this->slots($court) + ['coupon_code' => 'SAVE20K'])
            ->assertCreated()
            ->assertJsonPath('quote.subtotal', 120000)
            ->assertJsonPath('quote.total_amount', 100000)
            ->assertJsonPath('quote.benefit_snapshot.coupon_id', $coupon->id)
            ->assertJsonPath('quote.benefit_snapshot.coupon_code', 'SAVE20K')
            ->assertJsonPath('quote.benefit_snapshot.coupon_discount_amount', 20000);

        $this->assertDatabaseHas('price_quotes', ['court_id' => $court->id, 'status' => 'open', 'subtotal' => 120000, 'total_amount' => 100000]);
        $this->assertSame(0, $coupon->fresh()->usage_count);
    }

    public function test_checkout_with_a_coupon_reserves_one_use_that_confirmation_keeps(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $coupon = $this->coupon($organization, ['usage_count' => 3]);
        $this->fundWallet($user, $organization);

        $code = $this->command($user, $organization, '/api/reservations/checkout', $this->slots($court) + ['coupon_code' => 'SAVE20K'])
            ->assertCreated()
            ->assertJsonPath('reservation.status', 'confirmed')
            ->assertJsonPath('reservation.total_amount', 100000)
            ->json('reservation.code');

        $this->assertSame(4, $coupon->fresh()->usage_count);
        $usage = $this->couponUsage($code);
        $this->assertSame($coupon->id, $usage['coupon_id']);
        $this->assertNull($usage['promotion_campaign_id']);
        $this->assertNotEmpty($usage['reserved_at']);
        $this->assertNull($usage['released_at']);
        $this->assertSame(900000, (int) app(WalletPaymentService::class)->getOrCreateWallet($user->id, $organization->id)->balance);
    }

    public function test_confirming_a_held_reservation_keeps_its_coupon_use_past_the_hold_window(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $coupon = $this->coupon($organization, ['usage_count' => 3]);
        $this->fundWallet($user, $organization);
        $code = $this->command($user, $organization, '/api/reservations', $this->slots($court) + ['coupon_code' => 'SAVE20K'])
            ->assertCreated()->json('reservation.code');

        $this->command($user, $organization, "/api/reservations/{$code}/confirm", [])
            ->assertOk()
            ->assertJsonPath('reservation.status', 'confirmed');
        $this->travel(16)->minutes();

        $this->assertSame(0, app(ExpirePendingBookings::class)->execute());
        $this->assertSame('confirmed', Reservation::withoutGlobalScope('tenant_isolation')->where('code', $code)->value('status'));
        $this->assertSame(4, $coupon->fresh()->usage_count);
        $this->assertNull($this->couponUsage($code)['released_at']);
        $this->assertSame(900000, (int) app(WalletPaymentService::class)->getOrCreateWallet($user->id, $organization->id)->balance);
    }

    public function test_expired_hold_returns_its_coupon_use_exactly_once(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $coupon = $this->coupon($organization, ['usage_count' => 3]);

        $code = $this->command($user, $organization, '/api/reservations', $this->slots($court) + ['coupon_code' => 'SAVE20K'])
            ->assertCreated()
            ->assertJsonPath('reservation.status', 'held')
            ->assertJsonPath('reservation.total_amount', 100000)
            ->json('reservation.code');
        $this->assertSame(4, $coupon->fresh()->usage_count);
        $usage = $this->couponUsage($code);
        $this->assertSame($coupon->id, $usage['coupon_id']);
        $this->assertNull($usage['released_at']);

        $this->travel(16)->minutes();
        $this->assertSame(1, app(ExpirePendingBookings::class)->execute());

        $this->assertSame('expired', Reservation::withoutGlobalScope('tenant_isolation')->where('code', $code)->value('status'));
        $this->assertSame(3, $coupon->fresh()->usage_count);
        $releasedAt = $this->couponUsage($code)['released_at'];
        $this->assertNotNull($releasedAt);

        $this->travel(1)->minutes();
        $this->assertSame(0, app(ExpirePendingBookings::class)->execute());
        $this->assertSame(3, $coupon->fresh()->usage_count);
        $this->assertSame($releasedAt, $this->couponUsage($code)['released_at']);
    }

    public function test_releasing_a_held_reservation_returns_its_coupon_use_exactly_once(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $coupon = $this->coupon($organization, ['usage_count' => 3]);
        $code = $this->command($user, $organization, '/api/reservations', $this->slots($court) + ['coupon_code' => 'SAVE20K'])
            ->assertCreated()->json('reservation.code');
        $this->assertSame(4, $coupon->fresh()->usage_count);
        $reservation = Reservation::withoutGlobalScope('tenant_isolation')->where('code', $code)->firstOrFail();
        // A second caller loaded the same row while it was still held.
        $stale = Reservation::withoutGlobalScope('tenant_isolation')->where('code', $code)->firstOrFail();

        app(ReservationService::class)->release($reservation, 'cancelled');
        $releasedAt = $this->couponUsage($code)['released_at'];
        app(ReservationService::class)->release($stale, 'cancelled');

        $this->assertSame('cancelled', $reservation->fresh()->status);
        $this->assertSame(3, $coupon->fresh()->usage_count);
        $this->assertNotNull($releasedAt);
        $this->assertSame($releasedAt, $this->couponUsage($code)['released_at']);
    }

    public function test_unknown_or_inactive_coupon_code_is_rejected_without_using_a_coupon_or_creating_a_reservation(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $suspended = $this->coupon($organization, ['code' => 'PAUSED', 'status' => Coupon::STATUS_SUSPENDED, 'usage_count' => 3]);
        $this->fundWallet($user, $organization);

        foreach (['NOPE', 'PAUSED'] as $couponCode) {
            foreach (['/api/price-quotes', '/api/reservations', '/api/reservations/checkout'] as $uri) {
                $this->command($user, $organization, $uri, $this->slots($court) + ['coupon_code' => $couponCode])
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors(['coupon_code']);
            }
        }

        $this->assertSame(3, $suspended->fresh()->usage_count);
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('price_quotes', 0);
        $this->assertDatabaseCount('idempotency_records', 0);
        $this->assertSame(1000000, (int) app(WalletPaymentService::class)->getOrCreateWallet($user->id, $organization->id)->balance);
    }

    public function test_coupon_at_its_usage_limit_is_rejected(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $rival = $this->member($organization);
        $coupon = $this->coupon($organization, ['usage_limit' => 1]);
        $this->fundWallet($rival, $organization);
        $quote = $this->command($user, $organization, '/api/price-quotes', $this->slots($court) + ['coupon_code' => 'SAVE20K'])
            ->assertCreated()->json('quote');

        // The rival takes the last use after the first quote was made.
        $this->command($rival, $organization, '/api/reservations/checkout', $this->slots($court, 11) + ['coupon_code' => 'SAVE20K'])
            ->assertCreated();

        $this->command($user, $organization, '/api/reservations/checkout', ['quote_id' => $quote['id']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.coupon_code.0', 'Mã giảm giá đã hết lượt sử dụng.');
        $this->command($user, $organization, '/api/price-quotes', $this->slots($court, 12) + ['coupon_code' => 'SAVE20K'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.coupon_code.0', 'Mã giảm giá đã hết lượt sử dụng.');

        $this->assertSame(1, $coupon->fresh()->usage_count);
        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseMissing('reservations', ['user_id' => $user->id]);
        $this->assertDatabaseHas('price_quotes', ['id' => $quote['id'], 'status' => 'open']);
    }

    public function test_coupon_of_another_organization_is_rejected_even_under_that_organizations_header(): void
    {
        [$organization, $user, $court] = $this->tenant();
        [$foreignOrganization] = $this->tenant();
        OrganizationMember::create(['organization_id' => $foreignOrganization->id, 'user_id' => $user->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);
        $foreign = $this->coupon($foreignOrganization, ['usage_count' => 3]);
        $this->fundWallet($user, $organization);

        foreach (['/api/price-quotes', '/api/reservations', '/api/reservations/checkout'] as $uri) {
            $this->command($user, $foreignOrganization, $uri, $this->slots($court) + ['coupon_code' => 'SAVE20K'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['coupon_code']);
        }

        $this->assertSame(3, $foreign->fresh()->usage_count);
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('price_quotes', 0);
    }

    public function test_quote_below_the_coupon_minimum_spend_is_rejected(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $coupon = $this->coupon($organization, ['min_spend' => 150000]);

        $this->command($user, $organization, '/api/price-quotes', $this->slots($court) + ['coupon_code' => 'SAVE20K'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.coupon_code.0', 'Đơn tối thiểu để dùng mã là 150.000₫.');

        $this->assertDatabaseCount('price_quotes', 0);
        $this->assertSame(0, $coupon->fresh()->usage_count);
    }

    /** @return array{0: Organization, 1: User, 2: Court} */
    private function tenant(): array
    {
        $organization = Organization::create([
            'name' => 'Coupon Tenant',
            'slug' => 'coupon-tenant-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $venue = Venue::create(['organization_id' => $organization->id, 'name' => 'Coupon Venue', 'slug' => 'coupon-venue-'.uniqid(), 'status' => 'published', 'is_active' => true]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Coupon Court', 'is_active' => true]);
        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court->id, 'type' => 'base', 'price' => 120000]);

        return [$organization, $this->member($organization), $court];
    }

    private function member(Organization $organization): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);

        return $user;
    }

    private function coupon(Organization $organization, array $overrides = []): Coupon
    {
        return Coupon::create([
            'organization_id' => $organization->id,
            'code' => 'SAVE20K',
            'discount_type' => 'fixed',
            'discount_value' => 20000,
            'is_active' => true,
            'status' => Coupon::STATUS_ACTIVE,
            ...$overrides,
        ]);
    }

    private function fundWallet(User $user, Organization $organization): void
    {
        $wallets = app(WalletPaymentService::class);
        $wallets->credit($wallets->getOrCreateWallet($user->id, $organization->id), 1000000, 'test_topup', 1, 'Fund for test');
    }

    /** @return array{court_id: int, slots: array<int, string>} */
    private function slots(Court $court, int $hour = 10): array
    {
        return ['court_id' => $court->id, 'slots' => [now(config('app.timezone'))->addDays(3)->setTime($hour, 0)->toIso8601String()]];
    }

    private function command(User $user, Organization $organization, string $uri, array $payload): TestResponse
    {
        return $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => (string) Str::uuid(),
        ])->postJson($uri, $payload);
    }

    private function couponUsage(string $code): array
    {
        return Reservation::withoutGlobalScope('tenant_isolation')->where('code', $code)->firstOrFail()->metadata['coupon_usage'];
    }
}
