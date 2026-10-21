<?php

namespace Tests\Feature\Booking;

use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\WalletPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\PricesCourts;
use Tests\TestCase;

/**
 * Court bookings take no coupon (design D28): a quote, hold or checkout naming a code is refused with 422 and
 * creates nothing, while an empty code is the same as none.
 */
class ReservationCouponTest extends TestCase
{
    use PricesCourts;
    use RefreshDatabase;

    public function test_quote_hold_and_checkout_naming_a_coupon_are_refused_and_create_nothing(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $wallets = app(WalletPaymentService::class);
        $wallet = $wallets->getOrCreateWallet($user->id, $organization->id);
        $wallets->credit($wallet, 1000000, 'test_topup', 1, 'Fund for test');

        foreach (['/api/price-quotes', '/api/reservations', '/api/reservations/checkout'] as $uri) {
            $this->command($user, $organization, $uri, $this->slots($court) + ['coupon_code' => 'SAVE20K', 'expected_total' => 100000])
                ->assertUnprocessable()
                ->assertJsonPath('errors.coupon_code.0', 'Mã coupon không còn áp dụng cho đặt sân.');
        }

        $this->assertDatabaseCount('price_quotes', 0);
        $this->assertDatabaseCount('reservations', 0);
        $this->assertSame(1000000, (int) $wallet->fresh()->balance);
    }

    public function test_an_empty_coupon_code_is_the_same_as_none(): void
    {
        [$organization, $user, $court] = $this->tenant();

        $this->command($user, $organization, '/api/price-quotes', $this->slots($court) + ['coupon_code' => ''])
            ->assertCreated()
            ->assertJsonPath('quote.subtotal', 120000)
            ->assertJsonPath('quote.total_amount', 120000)
            ->assertJsonPath('quote.benefit_snapshot', null);
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
        $this->priceCourt($court, 120000);
        $user = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);

        return [$organization, $user, $court];
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
}
