<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PriceRule;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\WalletPaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConcurrentSlotAndWalletCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function setupTenant(): array
    {
        $organization = Organization::create(['name' => 'Org Concurrency', 'slug' => 'org-conc-'.uniqid()]);
        $venue = Venue::create(['organization_id' => $organization->id, 'name' => 'Venue Conc', 'slug' => 'venue-conc-'.uniqid(), 'is_active' => true]);
        $court = $venue->courts()->create(['name' => 'Court Conc', 'is_active' => true]);
        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court->id, 'type' => 'base', 'price' => 200000]);

        return [$organization, $venue, $court];
    }

    private function fundUserWallet(User $user, int $organizationId, int $amount): void
    {
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id, $organizationId);
        $walletService->credit($wallet, $amount, 'test_topup', 1, 'Top up');
    }

    public function test_concurrent_wallet_debits_prevent_overdraft_and_double_spend(): void
    {
        [$organization, $venue, $court] = $this->setupTenant();
        $user = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => 'customer', 'status' => 'active']);

        // User wallet has exactly 200,000 VND (only enough for 1 booking)
        $this->fundUserWallet($user, $organization->id, 200000);

        $court2 = $venue->courts()->create(['name' => 'Court Conc 2', 'is_active' => true]);
        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court2->id, 'type' => 'base', 'price' => 200000]);

        $date1 = Carbon::now(config('app.timezone'))->addDays(2)->setTime(8, 0)->startOfHour();
        $date2 = Carbon::now(config('app.timezone'))->addDays(2)->setTime(9, 0)->startOfHour();
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date1->dayOfWeekIso, 'starts_at' => '08:00', 'ends_at' => '09:00', 'price' => 200000, 'is_active' => true]);
        CourtPriceSlot::create(['court_id' => $court2->id, 'weekday' => $date2->dayOfWeekIso, 'starts_at' => '09:00', 'ends_at' => '10:00', 'price' => 200000, 'is_active' => true]);

        // Attempt checkout 1
        $res1 = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$date1->toIso8601String()],
        ]);
        $res1->assertCreated();

        // Attempt checkout 2 with same user, now out of funds
        $res2 = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'court_id' => $court2->id,
            'slots' => [$date2->toIso8601String()],
        ]);
        $res2->assertStatus(422);
        $res2->assertJsonValidationErrors(['wallet']);

        // Assert wallet balance is exactly 0 and not negative
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id, $organization->id);
        $this->assertSame(0, (int) $wallet->balance);

        // Assert only 1 booking confirmed
        $this->assertSame(1, Booking::where('user_id', $user->id)->where('status', 'confirmed')->count());
    }

    public function test_cross_version_v1_and_v2_slot_contention_guarantees_exactly_one_winner(): void
    {
        [$organization, $venue, $court] = $this->setupTenant();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user1->id, 'role' => 'customer', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user2->id, 'role' => 'customer', 'status' => 'active']);

        $this->fundUserWallet($user1, $organization->id, 500000);
        $this->fundUserWallet($user2, $organization->id, 500000);

        $date = Carbon::now(config('app.timezone'))->addDays(2)->setTime(14, 0)->startOfHour();
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '14:00', 'ends_at' => '15:00', 'price' => 200000, 'is_active' => true]);

        // User 1 books via V1
        $resV1 = $this->actingAs($user1)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
        ]);
        $resV1->assertCreated();

        // User 2 attempts to book the same slot via V2
        $resV2 = $this->actingAs($user2)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => uniqid('v2-contention-'),
        ])->postJson('/api/v2/reservations', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
        ]);
        $resV2->assertStatus(409);

        // Invariant: Exactly one active booking and entitlement
        $this->assertSame(1, Booking::where('court_id', $court->id)->where('status', 'confirmed')->count());
        $this->assertSame(1, ReservationEntitlement::where('court_id', $court->id)->whereIn('status', ['held', 'active'])->count());
    }

    public function test_unpaid_legacy_hold_confirmation_is_rejected(): void
    {
        [$organization, $venue, $court] = $this->setupTenant();
        $user = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => 'customer', 'status' => 'active']);

        // User has 0 balance
        $date = Carbon::now(config('app.timezone'))->addDays(2)->setTime(16, 0)->startOfHour();

        // Hold reservation directly in held state without wallet debit
        $reservation = Reservation::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'court_id' => $court->id,
            'code' => 'HOLD-'.uniqid(),
            'status' => 'held',
            'currency' => 'VND',
            'total_amount' => 200000,
            'hold_expires_at' => now()->addMinutes(15),
        ]);

        // Attempting to confirm without wallet balance returns 409
        $confirmResponse = $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => uniqid('unpaid-conf-'),
        ])->postJson("/api/v2/reservations/{$reservation->code}/confirm");

        $confirmResponse->assertStatus(409);
        $this->assertStringContainsString('insufficient wallet balance', $confirmResponse->json('message'));
        $this->assertSame('held', $reservation->fresh()->status);
    }
    public function test_v1_historical_api_compatibility_contracts(): void
    {
        [$organization, $venue, $court] = $this->setupTenant();
        $user = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => 'customer', 'status' => 'active']);
        $this->fundUserWallet($user, $organization->id, 1000000);

        $date = Carbon::now(config('app.timezone'))->addDays(2)->setTime(10, 0)->startOfHour();
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 200000, 'is_active' => true]);

        // 1. Create booking returns {booking: {id, code, status, total_amount, ...}}
        $createRes = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
        ]);
        $createRes->assertCreated();
        $createRes->assertJsonStructure([
            'booking' => ['id', 'code', 'status', 'total_amount', 'court_id', 'user_id', 'slots'],
        ]);
        $code = $createRes->json('booking.code');

        // 2. Fetch booking details returns {booking: ...}
        $showRes = $this->actingAs($user)->getJson("/api/v1/bookings/{$code}");
        $showRes->assertOk();
        $showRes->assertJsonPath('code', $code);
        $showRes->assertJsonPath('status', 'confirmed');

        // 3. Booking history list returns paginated bookings
        $listRes = $this->actingAs($user)->getJson('/api/v1/bookings');
        $listRes->assertOk();
        $this->assertGreaterThanOrEqual(1, count($listRes->json('data')));

        // 4. Cancel booking returns {message: ..., booking: ...} and refunds wallet
        $cancelRes = $this->actingAs($user)->postJson("/api/v1/bookings/{$code}/cancel");
        $cancelRes->assertOk();
        $this->assertSame('refunded', $cancelRes->json('status'));
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id, $organization->id);
        $this->assertSame(1000000, (int) $wallet->balance);
    }
}
