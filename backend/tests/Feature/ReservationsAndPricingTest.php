<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\LegacyBookingMapping;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\PriceRule;
use App\Models\User;
use App\Models\Venue;
use App\Services\Booking\RecurringSeriesService;
use App\Services\Payment\WalletPaymentService;
use App\Services\Reservation\LegacyBookingBackfillService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationsAndPricingTest extends TestCase
{
    use RefreshDatabase;

    private function fundWallet(User $user, int $organizationId, int $amount = 1000000): void
    {
        $walletService = app(WalletPaymentService::class);
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
            ->postJson('/api/price-quotes', ['court_id' => $court->id, 'slots' => [$date->toIso8601String()]]);

        $response->assertCreated()->assertJsonPath('quote.total_amount', 250000);
        $quote = $response->json('quote');
        PriceRule::query()->where('type', 'date_exception')->update(['price' => 999999]);

        $this->assertDatabaseHas('price_quotes', ['id' => $quote['id'], 'total_amount' => 250000]);
    }

    public function test_effective_date_edges_priority_and_rule_version_are_deterministic(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $start = now(config('app.timezone'))->addDays(8)->setTime(18, 0, 0);
        $from = $start->toDateString();
        $until = $start->copy()->addDay()->toDateString();

        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court->id, 'type' => 'base', 'price' => 100000]);
        PriceRule::create([
            'organization_id' => $organization->id,
            'court_id' => $court->id,
            'type' => 'peak',
            'starts_at' => '18:00',
            'ends_at' => '19:00',
            'price' => 999000,
            'priority' => 4,
            'version' => 99,
            'effective_from' => $from,
            'effective_until' => $until,
        ]);
        PriceRule::create([
            'organization_id' => $organization->id,
            'court_id' => $court->id,
            'type' => 'peak',
            'starts_at' => '18:00',
            'ends_at' => '19:00',
            'price' => 180000,
            'priority' => 6,
            'version' => 1,
            'effective_from' => $from,
            'effective_until' => $until,
        ]);
        PriceRule::create([
            'organization_id' => $organization->id,
            'court_id' => $court->id,
            'type' => 'peak',
            'starts_at' => '18:00',
            'ends_at' => '19:00',
            'price' => 200000,
            'priority' => 6,
            'version' => 2,
            'effective_from' => $from,
            'effective_until' => $until,
        ]);

        $quote = function (Carbon $time, string $key) use ($organization, $user, $court) {
            return $this->actingAs($user)->withHeaders([
                'X-Organization-ID' => (string) $organization->id,
                'Idempotency-Key' => $key,
            ])->postJson('/api/price-quotes', [
                'court_id' => $court->id,
                'slots' => [$time->toIso8601String()],
            ]);
        };

        $quote($start->copy()->subDay(), 'effective-before')->assertCreated()->assertJsonPath('quote.total_amount', 100000);
        $quote($start, 'effective-from-inclusive')->assertCreated()
            ->assertJsonPath('quote.total_amount', 200000)
            ->assertJsonPath('quote.slot_snapshot.0.rule.version', 2)
            ->assertJsonPath('quote.slot_snapshot.0.rule.priority', 6)
            ->assertJsonPath('quote.slot_snapshot.0.rule.effective_from', $from)
            ->assertJsonPath('quote.slot_snapshot.0.rule.effective_until', $until);
        $quote($start->copy()->addDay(), 'effective-until-inclusive')->assertCreated()->assertJsonPath('quote.total_amount', 200000);
        $quote($start->copy()->addDays(2), 'effective-after')->assertCreated()->assertJsonPath('quote.total_amount', 100000);
    }

    public function test_price_rule_api_rejects_an_inverted_effective_date_range(): void
    {
        [$organization, $user] = $this->tenant();
        OrganizationMember::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->update(['role' => OrganizationMember::ROLE_ORGANIZATION_OWNER]);
        $this->platformSubscription($organization);
        $from = now()->addDays(10)->toDateString();
        $until = Carbon::parse($from)->subDay()->toDateString();

        $this->actingAs($user)->withHeader('X-Organization-ID', (string) $organization->id)
            ->postJson('/api/price-rules', [
                'type' => 'base',
                'price' => 100000,
                'effective_from' => $from,
                'effective_until' => $until,
            ])->assertUnprocessable()->assertJsonValidationErrors(['effective_until']);

        $validFrom = now()->addDays(10)->toDateString();
        $validUntil = Carbon::parse($validFrom)->addDay()->toDateString();
        $created = $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => 'effective-range-create',
        ])->postJson('/api/price-rules', [
            'type' => 'base',
            'price' => 100000,
            'effective_from' => $validFrom,
            'effective_until' => $validUntil,
        ])->assertCreated();

        $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => 'effective-range-update',
        ])->patchJson('/api/price-rules/'.$created->json('rule.id'), [
            'effective_until' => Carbon::parse($validFrom)->subDay()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['effective_until']);
    }

    public function test_pricing_activation_is_blocked_when_platform_plan_entitlement_is_invalid(): void
    {
        [$organization, $user] = $this->tenant();
        OrganizationMember::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->update(['role' => OrganizationMember::ROLE_ORGANIZATION_OWNER]);

        // D29: correct role, but no platform plan entitlement at all.
        $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => 'no-plan-create',
        ])->postJson('/api/price-rules', ['type' => 'base', 'price' => 100000])
            ->assertForbidden()
            ->assertJsonPath('code', 'NO_ACTIVE_SUBSCRIPTION');

        // Correct role, but the plan has expired: activating new pricing is still denied.
        $subscription = $this->platformSubscription($organization, 'active', now()->subDay());

        $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => 'expired-plan-create',
        ])->postJson('/api/price-rules', ['type' => 'base', 'price' => 100000])
            ->assertForbidden()
            ->assertJsonPath('code', 'SUBSCRIPTION_EXPIRED');

        $this->assertDatabaseHas('platform_subscriptions', ['id' => $subscription->id, 'status' => 'expired']);
        $this->assertDatabaseMissing('price_rules', ['organization_id' => $organization->id]);

        // Renewing to a valid plan lets the same owner activate pricing again.
        $this->platformSubscription($organization, 'active', now()->addMonth());

        $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => 'valid-plan-create',
        ])->postJson('/api/price-rules', ['type' => 'base', 'price' => 100000])
            ->assertCreated();

        $this->assertDatabaseHas('price_rules', ['organization_id' => $organization->id, 'type' => 'base', 'price' => 100000]);
    }

    public function test_unmatched_legacy_time_is_rejected_until_an_explicit_base_rule_exists(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $start = now(config('app.timezone'))->addDays(7)->setTime(10, 0, 0);
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => $start->dayOfWeekIso,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
            'price' => 260000,
            'is_active' => true,
        ]);
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => $start->dayOfWeekIso,
            'starts_at' => '06:00',
            'ends_at' => '22:00',
            'price' => 500000,
            'is_active' => true,
        ]);

        $availability = $this->getJson("/api/availability?court_id={$court->id}&date={$start->toDateString()}")
            ->assertOk();
        $this->assertCount(1, $availability->json());
        $this->assertSame(260000, (int) $availability->json('0.price'));

        $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => 'unmatched-v2-no-fallback',
        ])->postJson('/api/price-quotes', [
            'court_id' => $court->id,
            'slots' => [$start->toIso8601String()],
        ])->assertUnprocessable();

        PriceRule::create(['organization_id' => $organization->id, 'type' => 'base', 'price' => 120000]);

        $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => 'unmatched-v2-explicit-base',
        ])->postJson('/api/price-quotes', [
            'court_id' => $court->id,
            'slots' => [$start->toIso8601String()],
        ])->assertCreated()->assertJsonPath('quote.total_amount', 120000);
    }

    public function test_availability_quote_and_recurring_share_holiday_peak_and_weekly_prices(): void
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
        foreach ($courts as $court) {
            foreach ($dates as $date) {
                CourtPriceSlot::create([
                    'court_id' => $court->id,
                    'weekday' => $date->dayOfWeekIso,
                    'starts_at' => $date->format('H:i'),
                    'ends_at' => $date->copy()->addHour()->format('H:i'),
                    'price' => 120000,
                    'is_active' => true,
                ]);
            }
        }

        PriceRule::create(['organization_id' => $organization->id, 'venue_id' => $venue->id, 'type' => 'base', 'price' => 100000]);
        foreach ($dates as $date) {
            PriceRule::create([
                'organization_id' => $organization->id,
                'venue_id' => $venue->id,
                'type' => 'weekly',
                'weekday' => $date->dayOfWeekIso,
                'starts_at' => $date->format('H:i'),
                'ends_at' => $date->copy()->addHour()->format('H:i'),
                'price' => 150000,
            ]);
        }
        PriceRule::create([
            'organization_id' => $organization->id,
            'venue_id' => $venue->id,
            'type' => 'peak',
            'starts_at' => '18:00',
            'ends_at' => '19:00',
            'price' => 200000,
        ]);
        PriceRule::create([
            'organization_id' => $organization->id,
            'venue_id' => $venue->id,
            'type' => 'date_exception',
            'exception_date' => $dates[0]->toDateString(),
            'price' => 250000,
        ]);

        $wallets = app(WalletPaymentService::class);
        $wallet = $wallets->getOrCreateWallet($user->id, $organization->id);
        $wallets->credit($wallet, 2000000, 'test_topup', 1, 'Shared pricing test');
        $cases = [
            ['date' => $dates[0], 'amount' => 250000],
            ['date' => $dates[1], 'amount' => 200000],
            // The court's own hourly price is the narrower scope, so it beats the venue-wide weekly rule.
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
        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court->id, 'type' => 'base', 'price' => 120000]);
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
        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court->id, 'type' => 'base', 'price' => 180000]);
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
        $rule = PriceRule::create([
            'organization_id' => $organization->id,
            'court_id' => $court->id,
            'type' => 'base',
            'price' => 210000,
            'effective_from' => now()->addDays(6)->toDateString(),
            'effective_until' => now()->addDays(6)->toDateString(),
        ]);
        $headers = ['X-Organization-ID' => (string) $organization->id];
        $this->fundWallet($user, $organization->id);
        $payload = ['court_id' => $court->id, 'slots' => [$date->toIso8601String()]];
        $quote = $this->actingAs($user)->withHeaders($headers + ['Idempotency-Key' => 'immutable-quote'])->postJson('/api/price-quotes', $payload);
        $hold = $this->actingAs($user)->withHeaders($headers + ['Idempotency-Key' => 'immutable-hold'])->postJson('/api/reservations', ['quote_id' => $quote->json('quote.id'), 'expected_total' => $quote->json('quote.total_amount')]);
        $reservationCode = $hold->json('reservation.code');
        $this->actingAs($user)->withHeaders($headers + ['Idempotency-Key' => 'immutable-confirm'])->postJson("/api/reservations/{$reservationCode}/confirm")->assertOk();

        $rule->update(['price' => 999000, 'effective_until' => now()->addDays(5)->toDateString()]);
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
        $res1 = $this->actingAs($user)->withHeaders($headers + ['Idempotency-Key' => 'tier-1'])->postJson('/api/price-quotes', $payload);
        $res1->assertCreated()->assertJsonPath('quote.total_amount', 250000);

        // 2. Disable date_exception: peak wins (200k)
        $exception->update(['is_active' => false]);
        $res2 = $this->actingAs($user)->withHeaders($headers + ['Idempotency-Key' => 'tier-2'])->postJson('/api/price-quotes', $payload);
        $res2->assertCreated()->assertJsonPath('quote.total_amount', 200000);

        // 3. Disable peak: weekly wins (150k)
        $peak->update(['is_active' => false]);
        $res3 = $this->actingAs($user)->withHeaders($headers + ['Idempotency-Key' => 'tier-3'])->postJson('/api/price-quotes', $payload);
        $res3->assertCreated()->assertJsonPath('quote.total_amount', 150000);

        // 4. Disable weekly: base wins (100k)
        $weekly->update(['is_active' => false]);
        $res4 = $this->actingAs($user)->withHeaders($headers + ['Idempotency-Key' => 'tier-4'])->postJson('/api/price-quotes', $payload);
        $res4->assertCreated()->assertJsonPath('quote.total_amount', 100000);
    }

    public function test_rule_change_before_submission_refuses_the_older_quote_at_its_shown_price(): void
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
            ->postJson('/api/price-quotes', ['court_id' => $court->id, 'slots' => [$date1->toIso8601String()]]);
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
            ->postJson('/api/price-quotes', ['court_id' => $court->id, 'slots' => [$date2->toIso8601String()]]);
        $q2->assertCreated()
            ->assertJsonPath('quote.total_amount', 240000)
            ->assertJsonPath('quote.rule_version', 2);
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

        // 6. User 2 checks out Quote 2: reservation total is 240k (new rule honored)
        $hold2 = $this->actingAs($user2)->withHeaders($headers + ['Idempotency-Key' => 'hold-user-2'])
            ->postJson('/api/reservations', ['quote_id' => $q2Id, 'expected_total' => 240000]);
        $hold2->assertCreated()->assertJsonPath('reservation.total_amount', 240000);
        $res2Code = $hold2->json('reservation.code');
        $this->actingAs($user2)->withHeaders($headers + ['Idempotency-Key' => 'confirm-user-2'])
            ->postJson("/api/reservations/{$res2Code}/confirm")
            ->assertOk();

        // The refused quote is left untouched; the accepted one keeps its snapshot
        $this->assertDatabaseHas('reservations', ['code' => $res2Code, 'total_amount' => 240000, 'status' => 'confirmed']);
        $this->assertDatabaseHas('price_quotes', ['id' => $q1Id, 'total_amount' => 120000, 'rule_version' => 1, 'status' => 'open']);
        $this->assertDatabaseHas('price_quotes', ['id' => $q2Id, 'total_amount' => 240000, 'rule_version' => 2, 'status' => 'accepted']);
    }

    public function test_reservation_lookup_follows_its_owner_across_organizations_but_not_other_operators(): void
    {
        [$orgA, $customer] = $this->tenant();
        [$orgB, , $courtB] = $this->tenant();
        OrganizationMember::create(['organization_id' => $orgB->id, 'user_id' => $customer->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);
        $operatorA = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create(['organization_id' => $orgA->id, 'user_id' => $operatorA->id, 'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER, 'status' => 'active']);
        PriceRule::create(['organization_id' => $orgB->id, 'court_id' => $courtB->id, 'type' => 'base', 'price' => 150000]);
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
        PriceRule::create(['organization_id' => $orgB->id, 'court_id' => $courtB->id, 'type' => 'base', 'price' => 150000]);
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

    private function platformSubscription(Organization $organization, string $status = 'active', ?Carbon $endsAt = null): PlatformSubscription
    {
        $plan = PlatformPlan::create([
            'code' => 'PLAN-'.Str::upper(Str::random(6)),
            'name' => 'Pricing Plan',
            'price' => 100000,
            'billing_cycle' => 'monthly',
            'is_active' => true,
        ]);

        return PlatformSubscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => $status,
            'starts_at' => now()->subMonth(),
            'ends_at' => $endsAt ?? now()->addMonth(),
        ]);
    }
}
