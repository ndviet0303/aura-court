<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PriceRule;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Models\WalletAccount;
use App\Services\Payment\WalletPaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdinaryWalletCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function fundUserWallet(User $user, int $organizationId, int $amount = 1000000): WalletAccount
    {
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id, $organizationId);
        $walletService->credit($wallet, $amount, 'test_topup', 1, 'Fund for test');

        return $wallet;
    }

    /** @return array{0:Organization,1:User,2:Court} */
    private function tenant(): array
    {
        $organization = Organization::create([
            'name' => 'V2 Tenant',
            'slug' => 'v2-tenant-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);
        $venue = Venue::create(['organization_id' => $organization->id, 'name' => 'V2 Venue', 'slug' => 'v2-venue-'.uniqid(), 'is_active' => true]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'V2 Court', 'is_active' => true]);

        return [$organization, $user, $court];
    }

    public function test_ordinary_checkout_from_slots_atomically_debits_wallet_and_confirms_reservation(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $wallet = $this->fundUserWallet($user, $organization->id, 500000);

        $date = Carbon::now()->addDays(2)->setTime(10, 0)->startOfHour();
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => $date->dayOfWeekIso,
            'starts_at' => '10:00',
            'ends_at' => '11:00',
            'price' => 120000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->withHeader('Idempotency-Key', 'ordinary-slot-checkout')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $court->id,
                'slots' => [$date->toIso8601String()],
                'expected_total' => 120000,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('reservation.status', 'confirmed');
        $response->assertJsonPath('reservation.total_amount', 120000);

        $reservation = Reservation::where('code', $response->json('reservation.code'))->firstOrFail();

        // 1. Wallet balance debited
        $this->assertSame(380000, $wallet->fresh()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_account_id' => $wallet->id,
            'amount' => 120000,
            'type' => 'debit',
            'source_type' => 'reservation_payment',
            'source_id' => $reservation->id,
        ]);

        // 2. Balanced ledger journal created
        $this->assertDatabaseHas('journals', [
            'organization_id' => $organization->id,
            'event_type' => 'reservation.wallet_payment',
            'reference' => $reservation->code,
        ]);

        // 3. Slots confirmed and entitlements active
        $this->assertDatabaseHas('reservation_slots', ['reservation_id' => $reservation->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('reservation_entitlements', ['reservation_id' => $reservation->id, 'status' => 'active']);
    }

    public function test_v2_canonical_checkout_atomically_debits_wallet_and_creates_active_entitlements(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $wallet = $this->fundUserWallet($user, $organization->id, 600000);

        $date = Carbon::now(config('app.timezone'))->addDays(4)->setTime(14, 0)->startOfHour();
        PriceRule::create([
            'organization_id' => $organization->id,
            'court_id' => $court->id,
            'type' => 'base',
            'price' => 200000,
        ]);

        $headers = [
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => 'v2-canonical-checkout-1',
        ];

        // 1. Create quote
        $quoteRes = $this->actingAs($user)->withHeaders($headers)
            ->postJson('/api/price-quotes', [
                'court_id' => $court->id,
                'slots' => [$date->toIso8601String()],
            ]);
        $quoteRes->assertCreated();
        $quoteId = $quoteRes->json('quote.id');

        // 2. Canonical checkout
        $checkoutRes = $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => 'v2-canonical-checkout-2',
        ])->postJson('/api/reservations/checkout', [
            'quote_id' => $quoteId,
            'expected_total' => $quoteRes->json('quote.total_amount'),
        ]);

        $checkoutRes->assertCreated();
        $checkoutRes->assertJsonPath('reservation.status', 'confirmed');
        $checkoutRes->assertJsonPath('reservation.total_amount', 200000);

        $code = $checkoutRes->json('reservation.code');
        $reservation = Reservation::where('code', $code)->firstOrFail();

        // 3. Balance debited
        $this->assertSame(400000, $wallet->fresh()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_account_id' => $wallet->id,
            'amount' => 200000,
            'type' => 'debit',
            'source_type' => 'reservation_payment',
        ]);

        // 4. Slots confirmed and entitlements active
        $this->assertDatabaseHas('reservation_slots', [
            'reservation_id' => $reservation->id,
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('reservation_entitlements', [
            'reservation_id' => $reservation->id,
            'status' => 'active',
        ]);

        // 5. Balanced financial ledger journal posted
        $this->assertDatabaseHas('journals', [
            'organization_id' => $organization->id,
            'event_type' => 'reservation.wallet_payment',
            'reference' => $reservation->code,
        ]);
    }

    public function test_v2_canonical_checkout_rejects_when_wallet_has_insufficient_funds(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $this->fundUserWallet($user, $organization->id, 50000); // Only 50k, rule price is 200k

        $date = Carbon::now(config('app.timezone'))->addDays(4)->setTime(15, 0)->startOfHour();
        PriceRule::create([
            'organization_id' => $organization->id,
            'court_id' => $court->id,
            'type' => 'base',
            'price' => 200000,
        ]);

        $checkoutRes = $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => 'v2-insufficient-checkout',
        ])->postJson('/api/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
            'expected_total' => 200000,
        ]);

        $checkoutRes->assertStatus(422);
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('reservation_entitlements', 0);
    }

    public function test_v2_reservation_cancellation_returns_404_with_no_side_effects_including_used_idempotency_keys(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $wallet = $this->fundUserWallet($user, $organization->id, 500000);

        $date = Carbon::now(config('app.timezone'))->addDays(4)->setTime(14, 0)->startOfHour();
        PriceRule::create([
            'organization_id' => $organization->id,
            'court_id' => $court->id,
            'type' => 'base',
            'price' => 200000,
        ]);

        // 1. Checkout reservation: costs 200k
        $res = $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => 'v2-cancel-test-checkout',
        ])->postJson('/api/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
            'expected_total' => 200000,
        ])->assertCreated();
        $code = $res->json('reservation.code');
        $this->assertSame(300000, $wallet->fresh()->balance);

        // 2. Cancel reservation with fresh key returns 404
        $cancelRes = $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => 'v2-cancel-test-cancel',
        ])->postJson("/api/reservations/{$code}/cancel");
        $cancelRes->assertNotFound();
        $this->assertSame(300000, $wallet->fresh()->balance);
        $this->assertDatabaseHas('reservations', ['code' => $code, 'status' => 'confirmed']);

        // 3. Retry cancel with another fresh key returns 404
        $retryRes = $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => 'v2-cancel-test-retry',
        ])->postJson("/api/reservations/{$code}/cancel");
        $retryRes->assertNotFound();
        $this->assertSame(300000, $wallet->fresh()->balance);
        $this->assertDatabaseHas('reservations', ['code' => $code, 'status' => 'confirmed']);

        // 4. Retry cancel using previously used checkout Idempotency-Key returns 404 with no side effects
        $usedKeyRes = $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => 'v2-cancel-test-checkout',
        ])->postJson("/api/reservations/{$code}/cancel");
        $usedKeyRes->assertNotFound();
        $this->assertSame(300000, $wallet->fresh()->balance);
        $this->assertDatabaseHas('reservations', ['code' => $code, 'status' => 'confirmed']);
        $this->assertDatabaseCount('refunds', 0);
    }
}
