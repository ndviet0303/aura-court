<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\LegacyBookingMapping;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PriceRule;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
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
        $organization = Organization::create(['name' => 'V2 Tenant', 'slug' => 'v2-tenant-'.uniqid()]);
        $user = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);
        $venue = Venue::create(['organization_id' => $organization->id, 'name' => 'V2 Venue', 'slug' => 'v2-venue-'.uniqid(), 'is_active' => true]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'V2 Court', 'is_active' => true]);

        return [$organization, $user, $court];
    }

    public function test_v1_ordinary_checkout_atomically_debits_wallet_and_syncs_v2_canonical_mapping(): void
    {
        $user = User::factory()->create();
        $venue = Venue::firstOrFail();
        $wallet = $this->fundUserWallet($user, $venue->organization_id, 500000);

        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Sân V1 Checkout', 'is_active' => true]);
        $date = Carbon::now()->addDays(2)->setTime(10, 0)->startOfHour();
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => $date->dayOfWeekIso,
            'starts_at' => '10:00',
            'ends_at' => '11:00',
            'price' => 120000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'booking' => ['id', 'code', 'total_amount', 'status', 'court', 'slots', 'payments'],
        ]);
        $response->assertJsonPath('booking.status', 'confirmed');
        $response->assertJsonPath('booking.total_amount', 120000);

        $bookingCode = $response->json('booking.code');
        $booking = Booking::where('code', $bookingCode)->firstOrFail();

        // 1. Wallet balance debited
        $this->assertSame(380000, $wallet->fresh()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_account_id' => $wallet->id,
            'amount' => 120000,
            'type' => 'debit',
            'source_type' => 'booking_payment',
        ]);

        // 2. Payment created and paid
        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'provider' => 'wallet',
            'status' => 'paid',
            'amount' => 120000,
        ]);

        // 3. Balanced ledger journal created
        $this->assertDatabaseHas('journals', [
            'organization_id' => $venue->organization_id,
            'event_type' => 'booking.wallet_payment',
            'reference' => $booking->code,
        ]);

        // 4. Canonical V2 reservation mapping created
        $this->assertDatabaseHas('legacy_booking_mappings', [
            'legacy_booking_id' => $booking->id,
            'organization_id' => $venue->organization_id,
        ]);
        $mapping = LegacyBookingMapping::where('legacy_booking_id', $booking->id)->firstOrFail();
        $this->assertNotNull($mapping->reservation_id);
        $this->assertDatabaseHas('reservations', [
            'id' => $mapping->reservation_id,
            'status' => 'confirmed',
        ]);
    }

    public function test_v1_ordinary_checkout_fails_with_insufficient_wallet_balance(): void
    {
        $user = User::factory()->create();
        $venue = Venue::firstOrFail();
        // Wallet has only 50k, but slot costs 120k
        $this->fundUserWallet($user, $venue->organization_id, 50000);

        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Sân V1 Insufficient', 'is_active' => true]);
        $date = Carbon::now()->addDays(2)->setTime(10, 0)->startOfHour();
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => $date->dayOfWeekIso,
            'starts_at' => '10:00',
            'ends_at' => '11:00',
            'price' => 120000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['wallet']);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_slots', 0);
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
            ->postJson('/api/v2/price-quotes', [
                'court_id' => $court->id,
                'slots' => [$date->toIso8601String()],
            ]);
        $quoteRes->assertCreated();
        $quoteId = $quoteRes->json('quote.id');

        // 2. Canonical checkout
        $checkoutRes = $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => 'v2-canonical-checkout-2',
        ])->postJson('/api/v2/reservations/checkout', [
            'quote_id' => $quoteId,
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
        ])->postJson('/api/v2/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
        ]);

        $checkoutRes->assertStatus(422);
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('reservation_entitlements', 0);
    }

    public function test_v1_and_v2_cross_version_slot_conflict_prevention(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $this->fundUserWallet($user, $organization->id, 1000000);

        $date = Carbon::now(config('app.timezone'))->addDays(3)->setTime(16, 0)->startOfHour();

        // Price setup for both V1 and V2
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => $date->dayOfWeekIso,
            'starts_at' => '16:00',
            'ends_at' => '17:00',
            'price' => 150000,
            'is_active' => true,
        ]);
        PriceRule::create([
            'organization_id' => $organization->id,
            'court_id' => $court->id,
            'type' => 'base',
            'price' => 150000,
        ]);

        // 1. User checks out slot in V1
        $v1Res = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
        ]);
        $v1Res->assertCreated();

        // 2. Another customer attempts to check out the same slot in V2 -> 409 Conflict
        $secondUser = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $secondUser->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);
        $this->fundUserWallet($secondUser, $organization->id, 1000000);
        $v2Res = $this->actingAs($secondUser)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => 'v2-conflict-attempt',
        ])->postJson('/api/v2/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
        ]);

        $v2Res->assertStatus(409);
    }

    public function test_rescheduling_is_atomic_and_applies_wallet_price_difference(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $wallet = $this->fundUserWallet($user, $organization->id, 500000);

        $oldDate = Carbon::now(config('app.timezone'))->addDays(2)->setTime(10, 0)->startOfHour();
        $newDate = Carbon::now(config('app.timezone'))->addDays(3)->setTime(10, 0)->startOfHour();

        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $oldDate->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 100000, 'is_active' => true]);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $newDate->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 150000, 'is_active' => true]);

        // 1. Initial booking: costs 100k, wallet balance becomes 400k
        $initialRes = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$oldDate->toIso8601String()],
        ]);
        $initialRes->assertCreated();
        $oldBookingCode = $initialRes->json('booking.code');
        $this->assertSame(400000, $wallet->fresh()->balance);

        // 2. Reduce wallet balance to 70k (less than new full price 150k, but >= 50k difference)
        $wallet->update(['balance' => 70000]);

        // 3. Reschedule: difference is +50k
        $rescheduleRes = $this->actingAs($user)->postJson("/api/v1/bookings/{$oldBookingCode}/reschedule", [
            'court_id' => $court->id,
            'slots' => [$newDate->toIso8601String()],
        ]);

        $rescheduleRes->assertCreated();
        $rescheduleRes->assertJsonPath('booking.status', 'confirmed');
        $rescheduleRes->assertJsonPath('booking.total_amount', 150000);

        // 4. Wallet debited only by the 50k difference (70k - 50k = 20k)
        $this->assertSame(20000, $wallet->fresh()->balance);
        $this->assertDatabaseHas('bookings', ['code' => $oldBookingCode, 'status' => 'refunded']);
    }

    public function test_rescheduling_refunds_wallet_when_new_slot_is_cheaper(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $wallet = $this->fundUserWallet($user, $organization->id, 500000);

        $oldDate = Carbon::now(config('app.timezone'))->addDays(2)->setTime(10, 0)->startOfHour();
        $newDate = Carbon::now(config('app.timezone'))->addDays(3)->setTime(10, 0)->startOfHour();

        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $oldDate->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 150000, 'is_active' => true]);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $newDate->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 100000, 'is_active' => true]);

        // 1. Initial booking: 150k, balance becomes 350k
        $initialRes = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$oldDate->toIso8601String()],
        ]);
        $initialRes->assertCreated();
        $oldBookingCode = $initialRes->json('booking.code');
        $this->assertSame(350000, $wallet->fresh()->balance);

        // 2. Reschedule to cheaper slot (100k, diff = -50k)
        $rescheduleRes = $this->actingAs($user)->postJson("/api/v1/bookings/{$oldBookingCode}/reschedule", [
            'court_id' => $court->id,
            'slots' => [$newDate->toIso8601String()],
        ]);

        $rescheduleRes->assertCreated();
        // Wallet credited with 50k (350k + 50k = 400k)
        $this->assertSame(400000, $wallet->fresh()->balance);
        $this->assertDatabaseHas('bookings', ['code' => $oldBookingCode, 'status' => 'refunded']);
    }

    public function test_rescheduling_rejects_when_additional_wallet_balance_is_insufficient(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $wallet = $this->fundUserWallet($user, $organization->id, 500000);

        $oldDate = Carbon::now(config('app.timezone'))->addDays(2)->setTime(10, 0)->startOfHour();
        $newDate = Carbon::now(config('app.timezone'))->addDays(3)->setTime(10, 0)->startOfHour();

        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $oldDate->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 100000, 'is_active' => true]);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $newDate->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 150000, 'is_active' => true]);

        // Initial booking: 100k
        $initialRes = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$oldDate->toIso8601String()],
        ]);
        $initialRes->assertCreated();
        $oldBookingCode = $initialRes->json('booking.code');

        // Set wallet to only 20k (needs 50k difference)
        $wallet->update(['balance' => 20000]);

        $rescheduleRes = $this->actingAs($user)->postJson("/api/v1/bookings/{$oldBookingCode}/reschedule", [
            'court_id' => $court->id,
            'slots' => [$newDate->toIso8601String()],
        ]);

        $rescheduleRes->assertStatus(422);
        // Original booking and wallet unchanged
        $this->assertSame(20000, $wallet->fresh()->balance);
        $this->assertDatabaseHas('bookings', ['code' => $oldBookingCode, 'status' => 'confirmed']);
    }

    public function test_rescheduling_conflict_preserves_original_booking_and_balance(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $wallet = $this->fundUserWallet($user, $organization->id, 500000);

        $oldDate = Carbon::now(config('app.timezone'))->addDays(2)->setTime(10, 0)->startOfHour();
        $newDate = Carbon::now(config('app.timezone'))->addDays(3)->setTime(10, 0)->startOfHour();

        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $oldDate->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 100000, 'is_active' => true]);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $newDate->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 100000, 'is_active' => true]);

        // Initial booking
        $initialRes = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$oldDate->toIso8601String()],
        ]);
        $initialRes->assertCreated();
        $oldBookingCode = $initialRes->json('booking.code');
        $balanceBefore = $wallet->fresh()->balance;

        // Another user takes the new slot first
        $rival = User::factory()->create();
        $this->fundUserWallet($rival, $organization->id, 500000);
        $this->actingAs($rival)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$newDate->toIso8601String()],
        ])->assertCreated();

        // Reschedule attempt conflicts
        $rescheduleRes = $this->actingAs($user)->postJson("/api/v1/bookings/{$oldBookingCode}/reschedule", [
            'court_id' => $court->id,
            'slots' => [$newDate->toIso8601String()],
        ]);

        $rescheduleRes->assertStatus(409);
        $this->assertSame($balanceBefore, $wallet->fresh()->balance);
        $this->assertDatabaseHas('bookings', ['code' => $oldBookingCode, 'status' => 'confirmed']);
    }

    public function test_whole_cancellation_refunds_wallet_and_posts_financial_reversal(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $wallet = $this->fundUserWallet($user, $organization->id, 500000);

        $date = Carbon::now(config('app.timezone'))->addDays(2)->setTime(10, 0)->startOfHour();
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 120000, 'is_active' => true]);

        // Booking costs 120k
        $res = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
        ])->assertCreated();
        $code = $res->json('booking.code');
        $this->assertSame(380000, $wallet->fresh()->balance);

        // Cancel
        $cancelRes = $this->actingAs($user)->postJson("/api/v1/bookings/{$code}/cancel");
        $cancelRes->assertOk();
        $this->assertSame(500000, $wallet->fresh()->balance);
        $this->assertDatabaseHas('bookings', ['code' => $code, 'status' => 'refunded']);
        $this->assertDatabaseHas('journals', [
            'organization_id' => $organization->id,
            'event_type' => 'booking.wallet_refund',
            'reference' => $code,
        ]);
    }

    public function test_cancellation_retry_is_idempotent_and_refunds_exactly_once(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $wallet = $this->fundUserWallet($user, $organization->id, 500000);

        $date = Carbon::now(config('app.timezone'))->addDays(2)->setTime(10, 0)->startOfHour();
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 100000, 'is_active' => true]);

        $res = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
        ])->assertCreated();
        $code = $res->json('booking.code');
        $this->assertSame(400000, $wallet->fresh()->balance);

        // First cancellation
        $this->actingAs($user)->postJson("/api/v1/bookings/{$code}/cancel")->assertOk();
        $this->assertSame(500000, $wallet->fresh()->balance);

        // Second cancellation (retry): must succeed without refunding again
        $this->actingAs($user)->postJson("/api/v1/bookings/{$code}/cancel")->assertOk();
        $this->assertSame(500000, $wallet->fresh()->balance);
    }

    public function test_partial_cancellation_refunds_only_cancelled_slot_amounts(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $wallet = $this->fundUserWallet($user, $organization->id, 500000);

        $date1 = Carbon::now(config('app.timezone'))->addDays(2)->setTime(10, 0)->startOfHour();
        $date2 = Carbon::now(config('app.timezone'))->addDays(2)->setTime(11, 0)->startOfHour();
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date1->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 100000, 'is_active' => true]);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date2->dayOfWeekIso, 'starts_at' => '11:00', 'ends_at' => '12:00', 'price' => 120000, 'is_active' => true]);

        // Book 2 consecutive slots: 100k + 120k = 220k
        $res = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$date1->toIso8601String(), $date2->toIso8601String()],
        ])->assertCreated();
        $code = $res->json('booking.code');
        $slot1Id = $res->json('booking.slots.0.id');
        $this->assertSame(280000, $wallet->fresh()->balance);

        // Partially cancel slot 1 (100k)
        $cancelSlotsRes = $this->actingAs($user)->postJson("/api/v1/bookings/{$code}/cancel-slots", [
            'slot_ids' => [$slot1Id],
        ]);
        $cancelSlotsRes->assertOk();

        // Wallet credited exactly 100k (280k + 100k = 380k)
        $this->assertSame(380000, $wallet->fresh()->balance);
        $this->assertDatabaseHas('refunds', [
            'amount' => 100000,
            'status' => 'completed',
        ]);
    }

    public function test_v2_reservation_cancellation_refunds_wallet(): void
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
        ])->postJson('/api/v2/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
        ])->assertCreated();
        $code = $res->json('reservation.code');
        $this->assertSame(300000, $wallet->fresh()->balance);

        // 2. Cancel reservation -> 200k refunded
        $cancelRes = $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => 'v2-cancel-test-cancel',
        ])->postJson("/api/v2/reservations/{$code}/cancel");
        $cancelRes->assertOk();
        $this->assertSame(500000, $wallet->fresh()->balance);

        // 3. Retry cancel -> idempotent, balance remains 500k
        $retryRes = $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => 'v2-cancel-test-retry',
        ])->postJson("/api/v2/reservations/{$code}/cancel");
        $retryRes->assertOk();
        $this->assertSame(500000, $wallet->fresh()->balance);
    }
}
