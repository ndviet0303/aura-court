<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Services\Payment\WalletPaymentService;
use App\Services\Reservation\LegacyBookingBackfillService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PricesCourts;
use Tests\TestCase;

/**
 * D33 — unified book-ahead and slot-policy enforcement.
 *
 * Every write path (hold, checkout, quote acceptance, recurring series) shares the
 * under-lock SlotSelectionPolicy, so nonidentical-start overlaps, past slots,
 * over-horizon slots, continuity and total-duration bounds are rejected consistently.
 */
class SlotSelectionPolicyEnforcementTest extends TestCase
{
    use PricesCourts;
    use RefreshDatabase;

    /** @return array{org: Organization, user: User, venue: Venue, court: Court} */
    private function tenant(int $slotDuration = 60, ?array $bookingPolicy = null): array
    {
        $org = Organization::create([
            'name' => 'Slot Policy Org',
            'slug' => 'slot-policy-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);
        $venue = Venue::create(['organization_id' => $org->id, 'name' => 'Slot Policy Venue', 'slug' => 'slot-policy-venue-'.uniqid(), 'is_active' => true]);
        $court = Court::create(['venue_id' => $venue->id, 'sport_type' => 'badminton', 'slot_duration' => $slotDuration, 'name' => 'Court', 'is_active' => true]);
        // 150.000 ₫ per slot, which the helpers below send as the expected total.
        $this->priceCourt($court, intdiv(150000 * 60, $slotDuration));

        if ($bookingPolicy !== null) {
            VenueBookingPolicy::create([
                'venue_id' => $venue->id,
                'version' => 1,
                'created_by' => $user->id,
                'effective_at' => now(),
                'settings' => ['booking' => $bookingPolicy],
            ]);
        }

        return compact('org', 'user', 'venue', 'court');
    }

    private function member(Organization $org): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);

        return $user;
    }

    private function fundWallet(User $user, int $organizationId, int $amount = 1000000): void
    {
        $service = app(WalletPaymentService::class);
        $wallet = $service->getOrCreateWallet($user->id, $organizationId);
        $service->credit($wallet, $amount, 'test_topup', 1, 'Fund for test');
    }

    private function hold(User $user, Organization $org, Court $court, array $starts)
    {
        return $this->actingAs($user)
            ->withHeaders(['X-Organization-ID' => (string) $org->id, 'Idempotency-Key' => uniqid('hold-')])
            ->postJson('/api/reservations', ['court_id' => $court->id, 'slots' => $starts, 'expected_total' => 150000 * count($starts)]);
    }

    private function checkout(User $user, Organization $org, Court $court, array $starts)
    {
        return $this->actingAs($user)
            ->withHeaders(['X-Organization-ID' => (string) $org->id, 'Idempotency-Key' => uniqid('checkout-')])
            ->postJson('/api/reservations/checkout', ['court_id' => $court->id, 'slots' => $starts, 'expected_total' => 150000 * count($starts)]);
    }

    private function quote(User $user, Organization $org, Court $court, array $starts)
    {
        return $this->actingAs($user)
            ->withHeaders(['X-Organization-ID' => (string) $org->id, 'Idempotency-Key' => uniqid('quote-')])
            ->postJson('/api/price-quotes', ['court_id' => $court->id, 'slots' => $starts]);
    }

    private function holdQuote(User $user, Organization $org, array $quote)
    {
        return $this->actingAs($user)
            ->withHeaders(['X-Organization-ID' => (string) $org->id, 'Idempotency-Key' => uniqid('hold-quote-')])
            ->postJson('/api/reservations', ['quote_id' => $quote['id'], 'expected_total' => $quote['total_amount']]);
    }

    private function recurring(User $user, Organization $org, Court $court, Carbon $start)
    {
        return $this->actingAs($user)
            ->withHeaders(['X-Organization-ID' => (string) $org->id, 'Idempotency-Key' => uniqid('series-')])
            ->postJson('/api/recurring-series', [
                'court_id' => $court->id,
                'frequency' => 'weekly',
                'starts_at' => $start->toIso8601String(),
                'ends_at' => $start->copy()->addHour()->toIso8601String(),
                'occurrences' => 1,
            ]);
    }

    private function mappedLegacyBooking(User $user, Court $court, Carbon $start, Carbon $end): void
    {
        $booking = Booking::create(['code' => uniqid('BK-'), 'user_id' => $user->id, 'court_id' => $court->id, 'total_amount' => 150000, 'status' => 'confirmed']);
        BookingSlot::create(['booking_id' => $booking->id, 'court_id' => $court->id, 'starts_at' => $start, 'ends_at' => $end, 'unit_price' => 150000, 'status' => 'confirmed']);
        app(LegacyBookingBackfillService::class)->backfillBooking($booking);
    }

    public function test_hold_overlapping_a_mapped_legacy_booking_with_nonidentical_start_is_rejected(): void
    {
        ['org' => $org, 'user' => $holder, 'court' => $court] = $this->tenant(90);
        $base = Carbon::now(config('app.timezone'))->addDays(3)->setTime(11, 0);

        // Existing legacy booking: 11:00–12:00.
        $this->mappedLegacyBooking($holder, $court, $base, $base->copy()->addHour());

        // Hold 10:00–11:30 overlaps.
        $intruder = $this->member($org);
        $this->hold($intruder, $org, $court, [$base->copy()->setTime(10, 0)->toIso8601String()])
            ->assertStatus(409);

        // Only the legacy booking's mapped entitlement exists; the rejected hold added none.
        $this->assertDatabaseMissing('reservations', ['status' => 'held']);
        $this->assertDatabaseCount('reservation_entitlements', 1);
    }

    public function test_checkout_overlapping_a_mapped_legacy_booking_with_nonidentical_start_is_rejected(): void
    {
        ['org' => $org, 'user' => $holder, 'court' => $court] = $this->tenant(90);
        $base = Carbon::now(config('app.timezone'))->addDays(3)->setTime(11, 0);

        $this->mappedLegacyBooking($holder, $court, $base, $base->copy()->addHour());

        $intruder = $this->member($org);
        $this->fundWallet($intruder, $org->id);
        $this->checkout($intruder, $org, $court, [$base->copy()->setTime(10, 0)->toIso8601String()])
            ->assertStatus(409);

        $this->assertDatabaseCount('reservation_entitlements', 1);
    }

    public function test_two_v2_holds_with_nonidentical_overlapping_starts_conflict(): void
    {
        ['org' => $org, 'user' => $first, 'court' => $court] = $this->tenant(90);
        $base = Carbon::now(config('app.timezone'))->addDays(4)->setTime(10, 0);

        $this->hold($first, $org, $court, [$base->toIso8601String()])->assertCreated();

        $second = $this->member($org);
        $this->hold($second, $org, $court, [$base->copy()->setTime(11, 0)->toIso8601String()])
            ->assertStatus(409);

        $this->assertDatabaseCount('reservation_entitlements', 1);
    }

    public function test_v2_hold_and_checkout_reject_past_slots(): void
    {
        ['org' => $org, 'user' => $user, 'court' => $court] = $this->tenant();
        $this->fundWallet($user, $org->id);
        $past = Carbon::now(config('app.timezone'))->subDay()->setTime(10, 0);

        $this->hold($user, $org, $court, [$past->toIso8601String()])->assertStatus(422);
        $this->checkout($user, $org, $court, [$past->toIso8601String()])->assertStatus(422);
    }

    public function test_over_horizon_slot_is_rejected_on_every_path(): void
    {
        $policy = ['hold_ttl_minutes' => 30, 'max_days_ahead' => 7, 'min_duration_minutes' => 30, 'max_duration_minutes' => 240, 'require_continuity' => false];
        ['org' => $org, 'user' => $user, 'court' => $court] = $this->tenant(60, $policy);
        $this->fundWallet($user, $org->id);
        $farStart = Carbon::now(config('app.timezone'))->addDays(30)->setTime(10, 0);
        $far = $farStart->toIso8601String();

        $this->hold($user, $org, $court, [$far])->assertStatus(422);
        $this->checkout($user, $org, $court, [$far])->assertStatus(422);
        // A quote only prices the slot; the horizon is enforced when it is accepted.
        $quote = $this->quote($user, $org, $court, [$far])->assertCreated()->json('quote');
        $this->holdQuote($user, $org, $quote)->assertStatus(422);
        $this->recurring($user, $org, $court, $farStart)->assertStatus(422);

        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('recurring_series', 0);
    }

    public function test_gapped_selection_is_rejected_when_continuity_is_required(): void
    {
        $policy = ['hold_ttl_minutes' => 30, 'max_days_ahead' => 60, 'min_duration_minutes' => 60, 'max_duration_minutes' => 240, 'require_continuity' => true];
        ['org' => $org, 'user' => $user, 'court' => $court] = $this->tenant(60, $policy);
        $this->fundWallet($user, $org->id);
        $base = Carbon::now(config('app.timezone'))->addDays(3);
        $gapped = [
            $base->copy()->setTime(10, 0)->toIso8601String(),
            $base->copy()->setTime(12, 0)->toIso8601String(),
        ];

        $this->hold($user, $org, $court, $gapped)->assertStatus(422);
        $this->checkout($user, $org, $court, $gapped)->assertStatus(422);
    }

    public function test_gapped_selection_is_rejected_even_when_the_venue_turns_continuity_off(): void
    {
        // Continuity always applies; a venue's require_continuity setting no longer turns it off.
        $policy = ['hold_ttl_minutes' => 30, 'max_days_ahead' => 60, 'min_duration_minutes' => 60, 'max_duration_minutes' => 240, 'require_continuity' => false];
        ['org' => $org, 'user' => $user, 'court' => $court] = $this->tenant(60, $policy);
        $this->fundWallet($user, $org->id);
        $base = Carbon::now(config('app.timezone'))->addDays(3);
        $gapped = [
            $base->copy()->setTime(10, 0)->toIso8601String(),
            $base->copy()->setTime(12, 0)->toIso8601String(),
        ];

        $this->hold($user, $org, $court, $gapped)->assertStatus(422)->assertJsonValidationErrors(['slots']);
        $this->checkout($user, $org, $court, $gapped)->assertStatus(422)->assertJsonValidationErrors(['slots']);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_total_session_duration_below_minimum_is_rejected(): void
    {
        $policy = ['hold_ttl_minutes' => 30, 'max_days_ahead' => 60, 'min_duration_minutes' => 120, 'max_duration_minutes' => 240, 'require_continuity' => false];
        ['org' => $org, 'user' => $user, 'court' => $court] = $this->tenant(60, $policy);
        $this->fundWallet($user, $org->id);
        $slot = [Carbon::now(config('app.timezone'))->addDays(3)->setTime(10, 0)->toIso8601String()];

        $this->hold($user, $org, $court, $slot)->assertStatus(422);
        $this->checkout($user, $org, $court, $slot)->assertStatus(422);
    }

    public function test_total_session_duration_above_maximum_is_rejected(): void
    {
        $policy = ['hold_ttl_minutes' => 30, 'max_days_ahead' => 60, 'min_duration_minutes' => 60, 'max_duration_minutes' => 90, 'require_continuity' => false];
        ['org' => $org, 'user' => $user, 'court' => $court] = $this->tenant(60, $policy);
        $this->fundWallet($user, $org->id);
        $base = Carbon::now(config('app.timezone'))->addDays(3);
        $twoHours = [
            $base->copy()->setTime(10, 0)->toIso8601String(),
            $base->copy()->setTime(11, 0)->toIso8601String(),
        ];

        $this->hold($user, $org, $court, $twoHours)->assertStatus(422);
        $this->checkout($user, $org, $court, $twoHours)->assertStatus(422);
    }
}
