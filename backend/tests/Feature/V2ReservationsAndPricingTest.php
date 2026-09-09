<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\LegacyBookingMapping;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PriceRule;
use App\Models\User;
use App\Models\Venue;
use App\Models\SportZone;
use App\Services\Reservation\LegacyBookingBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class V2ReservationsAndPricingTest extends TestCase
{
    use RefreshDatabase;
    private function fundWallet(User $user, int $organizationId, int $amount = 1000000): void
    {
        $walletService = app(\App\Services\Payment\WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id, $organizationId);
        $walletService->credit($wallet, $amount, 'test_topup', 1, 'Fund for test');
    }


    public function test_date_exception_precedes_peak_weekly_and_base_rules(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $date = now(config('app.timezone'))->addDays(4)->setTime(18, 0);
        PriceRule::create(['organization_id' => $organization->id, 'type' => 'base', 'price' => 100000]);
        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court->id, 'type' => 'weekly', 'weekday' => $date->dayOfWeekIso, 'starts_at' => '18:00', 'ends_at' => '19:00', 'price' => 150000]);
        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court->id, 'type' => 'peak', 'starts_at' => '18:00', 'ends_at' => '19:00', 'price' => 200000]);
        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court->id, 'type' => 'date_exception', 'exception_date' => $date->toDateString(), 'price' => 250000]);

        $response = $this->actingAs($user)->withHeader('X-Organization-ID', (string) $organization->id)
            ->withHeader('Idempotency-Key', 'quote-precedence')
            ->postJson('/api/v2/price-quotes', ['court_id' => $court->id, 'slots' => [$date->toIso8601String()]]);

        $response->assertCreated()->assertJsonPath('quote.total_amount', 250000);
        $quote = $response->json('quote');
        PriceRule::query()->where('type', 'date_exception')->update(['price' => 999999]);

        $this->assertDatabaseHas('price_quotes', ['id' => $quote['id'], 'total_amount' => 250000]);
    }

    public function test_same_idempotency_key_replays_and_different_content_conflicts(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $date = now(config('app.timezone'))->addDays(3)->setTime(10, 0);
        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court->id, 'type' => 'base', 'price' => 120000]);
        $payload = ['court_id' => $court->id, 'slots' => [$date->toIso8601String()]];

        $first = $this->actingAs($user)->withHeader('X-Organization-ID', (string) $organization->id)->withHeader('Idempotency-Key', 'same-key')->postJson('/api/v2/price-quotes', $payload);
        $second = $this->actingAs($user)->withHeader('X-Organization-ID', (string) $organization->id)->withHeader('Idempotency-Key', 'same-key')->postJson('/api/v2/price-quotes', $payload);
        $conflict = $this->actingAs($user)->withHeader('X-Organization-ID', (string) $organization->id)->withHeader('Idempotency-Key', 'same-key')->postJson('/api/v2/price-quotes', ['court_id' => $court->id, 'slots' => [$date->addHour()->toIso8601String()]]);

        $first->assertCreated();
        $second->assertCreated()->assertJsonPath('quote.id', $first->json('quote.id'));
        $conflict->assertConflict();
        $this->assertDatabaseCount('price_quotes', 1);
        $this->assertDatabaseCount('idempotency_records', 1);
    }

    public function test_only_one_v2_entitlement_can_hold_a_court_time(): void
    {
        [$organization, $firstUser, $court] = $this->tenant();
        $secondUser = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $secondUser->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);
        $date = now(config('app.timezone'))->addDays(5)->setTime(11, 0);
        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court->id, 'type' => 'base', 'price' => 180000]);
        $payload = ['court_id' => $court->id, 'slots' => [$date->toIso8601String()]];

        $winner = $this->actingAs($firstUser)->withHeader('X-Organization-ID', (string) $organization->id)->withHeader('Idempotency-Key', 'reservation-one')->postJson('/api/v2/reservations', $payload);
        $loser = $this->actingAs($secondUser)->withHeader('X-Organization-ID', (string) $organization->id)->withHeader('Idempotency-Key', 'reservation-two')->postJson('/api/v2/reservations', $payload);

        $winner->assertCreated();
        $loser->assertConflict();
        $this->assertDatabaseCount('reservation_entitlements', 1);
        $this->assertDatabaseHas('reservation_entitlements', ['status' => 'held', 'court_id' => $court->id]);
    }

    public function test_confirmed_reservation_keeps_the_accepted_quote_snapshot(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $date = now(config('app.timezone'))->addDays(6)->setTime(13, 0);
        $rule = PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court->id, 'type' => 'base', 'price' => 210000]);
        $headers = ['X-Organization-ID' => (string) $organization->id];
        $this->fundWallet($user, $organization->id);
        $payload = ['court_id' => $court->id, 'slots' => [$date->toIso8601String()]];
        $quote = $this->actingAs($user)->withHeaders($headers + ['Idempotency-Key' => 'immutable-quote'])->postJson('/api/v2/price-quotes', $payload);
        $hold = $this->actingAs($user)->withHeaders($headers + ['Idempotency-Key' => 'immutable-hold'])->postJson('/api/v2/reservations', ['quote_id' => $quote->json('quote.id')]);
        $reservationCode = $hold->json('reservation.code');
        $this->actingAs($user)->withHeaders($headers + ['Idempotency-Key' => 'immutable-confirm'])->postJson("/api/v2/reservations/{$reservationCode}/confirm")->assertOk();

        $rule->update(['price' => 999000]);
        $this->assertDatabaseHas('reservations', ['code' => $reservationCode, 'status' => 'confirmed', 'total_amount' => 210000]);
        $this->assertDatabaseHas('price_quotes', ['status' => 'accepted', 'total_amount' => 210000]);
    }

    public function test_legacy_backfill_is_checksum_validated_and_repeatable(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $booking = Booking::create(['code' => 'BK-BACKFILL-001', 'user_id' => $user->id, 'court_id' => $court->id, 'total_amount' => 200000, 'status' => 'confirmed']);
        BookingSlot::create(['booking_id' => $booking->id, 'court_id' => $court->id, 'starts_at' => now()->addDays(3)->setTime(14, 0), 'ends_at' => now()->addDays(3)->setTime(15, 0), 'unit_price' => 200000, 'status' => 'confirmed']);

        $service = app(LegacyBookingBackfillService::class);
        $first = $service->backfill($organization->id);
        $second = $service->backfill($organization->id);

        $this->assertTrue($first['valid']);
        $this->assertSame(1, $first['created']);
        $this->assertSame(1, $second['existing']);
        $this->assertDatabaseCount('legacy_booking_mappings', 1);
        $this->assertDatabaseCount('legacy_booking_slot_mappings', 1);
        $this->assertDatabaseCount('reservations', 1);
        $this->assertSame($organization->id, LegacyBookingMapping::firstOrFail()->organization_id);
    }

    public function test_v1_adapter_syncs_and_projects_a_legacy_booking(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $booking = Booking::create(['code' => 'BK-ADAPTER-001', 'user_id' => $user->id, 'court_id' => $court->id, 'total_amount' => 160000, 'status' => 'confirmed']);
        BookingSlot::create(['booking_id' => $booking->id, 'court_id' => $court->id, 'starts_at' => now()->addDays(2)->setTime(16, 0), 'ends_at' => now()->addDays(2)->setTime(17, 0), 'unit_price' => 160000, 'status' => 'confirmed']);

        $sync = $this->actingAs($user)->postJson("/api/v1/bookings/{$booking->code}/sync-v2");
        $sync->assertCreated();
        $reservationCode = $sync->json('mapping.reservation.code');
        $projection = $this->actingAs($user)->getJson("/api/v1/legacy/reservations/{$reservationCode}");

        $projection->assertOk()->assertJsonPath('code', $booking->code)->assertJsonPath('total_amount', 160000);
        $this->assertSame($organization->id, $sync->json('mapping.organization_id'));
    }

    public function test_four_tier_and_scope_rule_precedence(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $date = now(config('app.timezone'))->addDays(4)->setTime(19, 0);

        // Base tier (100k)
        $base = PriceRule::create([
            'organization_id' => $organization->id,
            'type' => 'base',
            'price' => 100000,
            'version' => 1,
        ]);
        // Weekly tier (150k)
        $weekly = PriceRule::create([
            'organization_id' => $organization->id,
            'court_id' => $court->id,
            'type' => 'weekly',
            'weekday' => $date->dayOfWeekIso,
            'starts_at' => '19:00',
            'ends_at' => '20:00',
            'price' => 150000,
            'version' => 1,
        ]);
        // Peak tier (200k)
        $peak = PriceRule::create([
            'organization_id' => $organization->id,
            'court_id' => $court->id,
            'type' => 'peak',
            'starts_at' => '19:00',
            'ends_at' => '20:00',
            'price' => 200000,
            'version' => 1,
        ]);
        // Date exception tier (250k)
        $exception = PriceRule::create([
            'organization_id' => $organization->id,
            'court_id' => $court->id,
            'type' => 'date_exception',
            'exception_date' => $date->toDateString(),
            'price' => 250000,
            'version' => 1,
        ]);

        $headers = ['X-Organization-ID' => (string) $organization->id];
        $payload = ['court_id' => $court->id, 'slots' => [$date->toIso8601String()]];

        // 1. All active: date_exception wins (250k)
        $res1 = $this->actingAs($user)->withHeaders($headers + ['Idempotency-Key' => 'tier-1'])->postJson('/api/v2/price-quotes', $payload);
        $res1->assertCreated()->assertJsonPath('quote.total_amount', 250000);

        // 2. Disable date_exception: peak wins (200k)
        $exception->update(['is_active' => false]);
        $res2 = $this->actingAs($user)->withHeaders($headers + ['Idempotency-Key' => 'tier-2'])->postJson('/api/v2/price-quotes', $payload);
        $res2->assertCreated()->assertJsonPath('quote.total_amount', 200000);

        // 3. Disable peak: weekly wins (150k)
        $peak->update(['is_active' => false]);
        $res3 = $this->actingAs($user)->withHeaders($headers + ['Idempotency-Key' => 'tier-3'])->postJson('/api/v2/price-quotes', $payload);
        $res3->assertCreated()->assertJsonPath('quote.total_amount', 150000);

        // 4. Disable weekly: base wins (100k)
        $weekly->update(['is_active' => false]);
        $res4 = $this->actingAs($user)->withHeaders($headers + ['Idempotency-Key' => 'tier-4'])->postJson('/api/v2/price-quotes', $payload);
        $res4->assertCreated()->assertJsonPath('quote.total_amount', 100000);
    }

    public function test_concurrent_rule_change_preserves_quoted_snapshot_for_in_flight_checkout(): void
    {
        [$organization, $user1, $court] = $this->tenant();
        $user2 = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $user2->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);

        $date1 = now(config('app.timezone'))->addDays(5)->setTime(10, 0);
        $date2 = now(config('app.timezone'))->addDays(5)->setTime(11, 0);

        // 1. Initial rule v1: price 120k
        $rule = PriceRule::create([
            'organization_id' => $organization->id,
            'court_id' => $court->id,
            'type' => 'base',
            'price' => 120000,
            'version' => 1,
        ]);

        $headers = ['X-Organization-ID' => (string) $organization->id];

        // 2. User 1 gets Quote 1 at v1 (120k)
        $q1 = $this->actingAs($user1)->withHeaders($headers + ['Idempotency-Key' => 'quote-user-1'])
            ->postJson('/api/v2/price-quotes', ['court_id' => $court->id, 'slots' => [$date1->toIso8601String()]]);
        $q1->assertCreated()
            ->assertJsonPath('quote.total_amount', 120000)
            ->assertJsonPath('quote.rule_version', 1);
        $q1Id = $q1->json('quote.id');

        // 3. Admin concurrently updates rule to v2: price 240k
        $rule->update([
            'price' => 240000,
            'version' => 2,
        ]);

        // 4. User 2 gets Quote 2 at v2 (240k, version 2)
        $q2 = $this->actingAs($user2)->withHeaders($headers + ['Idempotency-Key' => 'quote-user-2'])
            ->postJson('/api/v2/price-quotes', ['court_id' => $court->id, 'slots' => [$date2->toIso8601String()]]);
        $q2->assertCreated()
            ->assertJsonPath('quote.total_amount', 240000)
            ->assertJsonPath('quote.rule_version', 2);
        $q2Id = $q2->json('quote.id');

        // 5. User 1 checks out Quote 1: reservation total is 120k (snapshot honored)
        $this->fundWallet($user1, $organization->id);
        $this->fundWallet($user2, $organization->id);
        $hold1 = $this->actingAs($user1)->withHeaders($headers + ['Idempotency-Key' => 'hold-user-1'])
            ->postJson('/api/v2/reservations', ['quote_id' => $q1Id]);
        $hold1->assertCreated()->assertJsonPath('reservation.total_amount', 120000);
        $res1Code = $hold1->json('reservation.code');
        $this->actingAs($user1)->withHeaders($headers + ['Idempotency-Key' => 'confirm-user-1'])
            ->postJson("/api/v2/reservations/{$res1Code}/confirm")
            ->assertOk();

        // 6. User 2 checks out Quote 2: reservation total is 240k (new rule honored)
        $hold2 = $this->actingAs($user2)->withHeaders($headers + ['Idempotency-Key' => 'hold-user-2'])
            ->postJson('/api/v2/reservations', ['quote_id' => $q2Id]);
        $hold2->assertCreated()->assertJsonPath('reservation.total_amount', 240000);
        $res2Code = $hold2->json('reservation.code');
        $this->actingAs($user2)->withHeaders($headers + ['Idempotency-Key' => 'confirm-user-2'])
            ->postJson("/api/v2/reservations/{$res2Code}/confirm")
            ->assertOk();

        // Verify both reservations and quotes retain their independent snapshots
        $this->assertDatabaseHas('reservations', ['code' => $res1Code, 'total_amount' => 120000, 'status' => 'confirmed']);
        $this->assertDatabaseHas('reservations', ['code' => $res2Code, 'total_amount' => 240000, 'status' => 'confirmed']);
        $this->assertDatabaseHas('price_quotes', ['id' => $q1Id, 'total_amount' => 120000, 'rule_version' => 1, 'status' => 'accepted']);
        $this->assertDatabaseHas('price_quotes', ['id' => $q2Id, 'total_amount' => 240000, 'rule_version' => 2, 'status' => 'accepted']);
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
}
