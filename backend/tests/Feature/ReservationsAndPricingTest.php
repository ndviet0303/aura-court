<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\LegacyBookingMapping;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PriceQuote;
use App\Models\User;
use App\Models\Venue;
use App\Services\Booking\RecurringSeriesService;
use App\Services\Payment\WalletPaymentService;
use App\Services\Reservation\LegacyBookingBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PricesCourts;
use Tests\TestCase;

class ReservationsAndPricingTest extends TestCase
{
    use PricesCourts;
    use RefreshDatabase;

    private function fundWallet(User $user, int $organizationId, int $amount = 1000000): void
    {
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id, $organizationId);
        $walletService->credit($wallet, $amount, 'test_topup', 1, 'Fund for test');
    }

    public function test_availability_quote_and_recurring_share_holiday_and_normal_table_prices(): void
    {
        [$organization, $user, $availabilityCourt] = $this->tenant();
        $venue = $availabilityCourt->venue;
        $user->forceFill(['email_verified_at' => now()])->save();
        config(['features.flags.direct_owner_payments' => false, 'features.pilot_organizations' => []]);

        $courts = collect([$availabilityCourt]);
        foreach (['Quote', 'Recurring'] as $name) {
            $courts->push(Court::create([
                'venue_id' => $venue->id,
                'name' => "Pricing {$name}",
                'is_active' => true,
            ]));
        }

        $dates = [
            now(config('app.timezone'))->addDays(4)->setTime(10, 0, 0),
            now(config('app.timezone'))->addDays(5)->setTime(18, 0, 0),
            now(config('app.timezone'))->addDays(6)->setTime(14, 0, 0),
        ];
        $this->priceCourtWith($courts->all(), [
            ['10:00', '11:00', 150000, [$dates[0]->dayOfWeekIso]],
            ['18:00', '19:00', 200000, [$dates[1]->dayOfWeekIso]],
            ['14:00', '15:00', 120000, [$dates[2]->dayOfWeekIso]],
        ]);
        $this->priceCourtHolidays($courts->all(), [$dates[0]->toDateString()], [['10:00', '11:00', 250000]]);

        $wallets = app(WalletPaymentService::class);
        $wallet = $wallets->getOrCreateWallet($user->id, $organization->id);
        $wallets->credit($wallet, 2000000, 'test_topup', 1, 'Shared pricing test');
        $cases = [
            // The holiday table replaces the normal one on its date.
            ['date' => $dates[0], 'amount' => 250000],
            ['date' => $dates[1], 'amount' => 200000],
            ['date' => $dates[2], 'amount' => 120000],
        ];

        foreach ($cases as $index => $case) {
            $date = $case['date'];
            $startsAt = $date->toIso8601String();
            $amount = $case['amount'];

            $this->getJson("/api/availability?court_id={$courts[0]->id}&date={$date->toDateString()}")
                ->assertOk()
                ->assertJsonPath('0.price', $amount);

            $this->actingAs($user)->withHeaders([
                'X-Organization-ID' => (string) $organization->id,
                'Idempotency-Key' => "shared-quote-{$index}",
            ])->postJson('/api/price-quotes', [
                'court_id' => $courts[1]->id,
                'slots' => [$startsAt],
            ])->assertCreated()->assertJsonPath('quote.total_amount', $amount);

            $series = app(RecurringSeriesService::class)->createAndConfirm($user, $organization->id, [
                'court_id' => $courts[2]->id,
                'frequency' => 'weekly',
                'starts_at' => $startsAt,
                'ends_at' => $date->copy()->addHour()->toIso8601String(),
                'occurrences' => 1,
            ]);
            $this->assertSame($amount, (int) $series->occurrences->first()->amount);
        }
    }

    public function test_same_idempotency_key_replays_and_different_content_conflicts(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $date = now(config('app.timezone'))->addDays(3)->setTime(10, 0);
        $this->priceCourt($court, 120000);
        $payload = ['court_id' => $court->id, 'slots' => [$date->toIso8601String()]];

        $first = $this->actingAs($user)->withHeader('X-Organization-ID', (string) $organization->id)->withHeader('Idempotency-Key', 'same-key')->postJson('/api/price-quotes', $payload);
        $second = $this->actingAs($user)->withHeader('X-Organization-ID', (string) $organization->id)->withHeader('Idempotency-Key', 'same-key')->postJson('/api/price-quotes', $payload);
        $conflict = $this->actingAs($user)->withHeader('X-Organization-ID', (string) $organization->id)->withHeader('Idempotency-Key', 'same-key')->postJson('/api/price-quotes', ['court_id' => $court->id, 'slots' => [$date->addHour()->toIso8601String()]]);

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
        $this->priceCourt($court, 180000);
        $payload = ['court_id' => $court->id, 'slots' => [$date->toIso8601String()], 'expected_total' => 180000];

        $winner = $this->actingAs($firstUser)->withHeader('X-Organization-ID', (string) $organization->id)->withHeader('Idempotency-Key', 'reservation-one')->postJson('/api/reservations', $payload);
        $loser = $this->actingAs($secondUser)->withHeader('X-Organization-ID', (string) $organization->id)->withHeader('Idempotency-Key', 'reservation-two')->postJson('/api/reservations', $payload);

        $winner->assertCreated();
        $loser->assertConflict();
        $this->assertDatabaseCount('reservation_entitlements', 1);
        $this->assertDatabaseHas('reservation_entitlements', ['status' => 'held', 'court_id' => $court->id]);
    }

    public function test_confirmed_reservation_keeps_the_accepted_quote_snapshot(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $date = now(config('app.timezone'))->addDays(6)->setTime(13, 0);
        $table = $this->priceCourt($court, 210000);
        $headers = ['X-Organization-ID' => (string) $organization->id];
        $this->fundWallet($user, $organization->id);
        $payload = ['court_id' => $court->id, 'slots' => [$date->toIso8601String()]];
        $quote = $this->actingAs($user)->withHeaders($headers + ['Idempotency-Key' => 'immutable-quote'])->postJson('/api/price-quotes', $payload);
        $hold = $this->actingAs($user)->withHeaders($headers + ['Idempotency-Key' => 'immutable-hold'])->postJson('/api/reservations', ['quote_id' => $quote->json('quote.id'), 'expected_total' => $quote->json('quote.total_amount')]);
        $reservationCode = $hold->json('reservation.code');
        $this->actingAs($user)->withHeaders($headers + ['Idempotency-Key' => 'immutable-confirm'])->postJson("/api/reservations/{$reservationCode}/confirm")->assertOk();

        $this->publishPriceVersion($table, [['00:00', '23:59', 999000]], now());
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

    public function test_price_change_before_submission_refuses_the_older_quote_at_its_shown_price(): void
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

        // 1. Initial price table version 1: price 120k
        $table = $this->priceCourt($court, 120000);
        $version1 = $table->versions()->firstOrFail();

        $headers = ['X-Organization-ID' => (string) $organization->id];

        // 2. User 1 gets Quote 1 at v1 (120k)
        $q1 = $this->actingAs($user1)->withHeaders($headers + ['Idempotency-Key' => 'quote-user-1'])
            ->postJson('/api/price-quotes', ['court_id' => $court->id, 'slots' => [$date1->toIso8601String()]]);
        $q1->assertCreated()
            ->assertJsonPath('quote.total_amount', 120000)
            ->assertJsonPath('quote.slot_snapshot.0.parts.0.price_table_version_id', $version1->id);
        $q1Id = $q1->json('quote.id');

        // 3. Admin concurrently publishes version 2, effective now: price 240k
        $version2 = $this->publishPriceVersion($table, [['00:00', '23:59', 240000]], now());

        // 4. User 2 gets Quote 2 at v2 (240k, version 2)
        $q2 = $this->actingAs($user2)->withHeaders($headers + ['Idempotency-Key' => 'quote-user-2'])
            ->postJson('/api/price-quotes', ['court_id' => $court->id, 'slots' => [$date2->toIso8601String()]]);
        $q2->assertCreated()
            ->assertJsonPath('quote.total_amount', 240000)
            ->assertJsonPath('quote.slot_snapshot.0.parts.0.price_table_version_id', $version2->id);
        $q2Id = $q2->json('quote.id');

        // 5. User 1 submits Quote 1 at the 120k it showed: repriced at submission, the rise is refused (PRICE_CHANGED)
        $this->fundWallet($user1, $organization->id);
        $this->fundWallet($user2, $organization->id);
        $this->actingAs($user1)->withHeaders($headers + ['Idempotency-Key' => 'hold-user-1'])
            ->postJson('/api/reservations', ['quote_id' => $q1Id, 'expected_total' => 120000])
            ->assertConflict()
            ->assertJsonPath('code', 'PRICE_CHANGED')
            ->assertJsonPath('total', 240000);
        $this->assertDatabaseMissing('reservations', ['user_id' => $user1->id]);

        // 6. User 2 checks out Quote 2: reservation total is 240k (new version honored)
        $hold2 = $this->actingAs($user2)->withHeaders($headers + ['Idempotency-Key' => 'hold-user-2'])
            ->postJson('/api/reservations', ['quote_id' => $q2Id, 'expected_total' => 240000]);
        $hold2->assertCreated()->assertJsonPath('reservation.total_amount', 240000);
        $res2Code = $hold2->json('reservation.code');
        $this->actingAs($user2)->withHeaders($headers + ['Idempotency-Key' => 'confirm-user-2'])
            ->postJson("/api/reservations/{$res2Code}/confirm")
            ->assertOk();

        // The refused quote is left untouched; the accepted one keeps its snapshot
        $this->assertDatabaseHas('reservations', ['code' => $res2Code, 'total_amount' => 240000, 'status' => 'confirmed']);
        $this->assertDatabaseHas('price_quotes', ['id' => $q1Id, 'total_amount' => 120000, 'status' => 'open']);
        $this->assertDatabaseHas('price_quotes', ['id' => $q2Id, 'total_amount' => 240000, 'status' => 'accepted']);
        $this->assertSame($version1->id, PriceQuote::findOrFail($q1Id)->slot_snapshot[0]['parts'][0]['price_table_version_id']);
    }

    public function test_reservation_lookup_follows_its_owner_across_organizations_but_not_other_operators(): void
    {
        [$orgA, $customer] = $this->tenant();
        [$orgB, , $courtB] = $this->tenant();
        OrganizationMember::create(['organization_id' => $orgB->id, 'user_id' => $customer->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);
        $operatorA = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create(['organization_id' => $orgA->id, 'user_id' => $operatorA->id, 'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER, 'status' => 'active']);
        $this->priceCourt($courtB, 150000);
        $date = now(config('app.timezone'))->addDays(4)->setTime(9, 0);
        $code = $this->actingAs($customer)->withHeaders(['X-Organization-ID' => (string) $orgB->id, 'Idempotency-Key' => 'cross-org-hold'])
            ->postJson('/api/reservations', ['court_id' => $courtB->id, 'slots' => [$date->toIso8601String()], 'expected_total' => 150000])
            ->assertCreated()->json('reservation.code');
        $headersA = ['X-Organization-ID' => (string) $orgA->id];

        $this->actingAs($customer)->withHeaders($headersA)->getJson("/api/reservations/{$code}")
            ->assertOk()
            ->assertJsonPath('reservation.organization_id', $orgB->id)
            ->assertJsonCount(1, 'reservation.slots')
            ->assertJsonCount(1, 'reservation.entitlements');
        $this->actingAs($customer)->withHeaders($headersA)->getJson("/api/reservations/{$code}/payment")
            ->assertOk()->assertJsonPath('reservation.code', $code);
        $this->actingAs($operatorA)->withHeaders($headersA)->getJson("/api/reservations/{$code}")->assertNotFound();
    }

    public function test_checkout_rejects_a_quote_from_another_organization_context(): void
    {
        [$orgA, $customer] = $this->tenant();
        [$orgB, , $courtB] = $this->tenant();
        OrganizationMember::create(['organization_id' => $orgB->id, 'user_id' => $customer->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);
        $this->priceCourt($courtB, 150000);
        $this->fundWallet($customer, $orgA->id);
        $this->fundWallet($customer, $orgB->id);
        $date = now(config('app.timezone'))->addDays(4)->setTime(10, 0);
        $quote = $this->actingAs($customer)->withHeaders(['X-Organization-ID' => (string) $orgB->id, 'Idempotency-Key' => 'cross-org-quote'])
            ->postJson('/api/price-quotes', ['court_id' => $courtB->id, 'slots' => [$date->toIso8601String()]])
            ->assertCreated()->json('quote');

        $this->actingAs($customer)->withHeaders(['X-Organization-ID' => (string) $orgA->id, 'Idempotency-Key' => 'cross-org-checkout'])
            ->postJson('/api/reservations/checkout', ['quote_token' => $quote['quote_token'], 'expected_total' => $quote['total_amount']])
            ->assertNotFound();

        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseHas('price_quotes', ['id' => $quote['id'], 'status' => 'open']);
    }

    public function test_reservation_detail_of_a_legacy_mirror_carries_its_mapping(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $booking = Booking::create(['code' => 'BK-DEEP-001', 'user_id' => $user->id, 'court_id' => $court->id, 'total_amount' => 120000, 'status' => 'pending_payment', 'payment_expires_at' => now()->addMinutes(10)]);
        BookingSlot::create(['booking_id' => $booking->id, 'court_id' => $court->id, 'starts_at' => now()->addDays(2)->setTime(9, 0), 'ends_at' => now()->addDays(2)->setTime(10, 0), 'unit_price' => 120000, 'status' => 'held']);
        app(LegacyBookingBackfillService::class)->backfillBooking($booking);

        $this->actingAs($user)->withHeaders(['X-Organization-ID' => (string) $organization->id])
            ->getJson('/api/reservations/LEGACY-BK-DEEP-001')
            ->assertOk()
            ->assertJsonPath('reservation.code', 'LEGACY-BK-DEEP-001')
            ->assertJsonPath('reservation.legacy_mapping.0.legacy_code', 'BK-DEEP-001')
            ->assertJsonPath('reservation.legacy_mapping.0.legacy_status', 'pending_payment')
            ->assertJsonMissingPath('reservation.legacy_mapping.0.source_checksum');
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
        $venue = Venue::create(['organization_id' => $organization->id, 'name' => 'V2 Venue', 'slug' => 'v2-venue-'.uniqid(), 'status' => 'published', 'is_active' => true]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'V2 Court', 'is_active' => true]);

        return [$organization, $user, $court];
    }
}
