<?php

namespace Tests\Feature\Booking;

use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PriceRule;
use App\Models\SportZone;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Services\Payment\WalletPaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D33 — unified book-ahead and slot-policy enforcement.
 *
 * Every write path (V1 checkout, V2 hold, V2 checkout) shares the under-lock
 * SlotSelectionPolicy, so nonidentical-start overlaps, past slots, over-horizon
 * slots, continuity and total-duration bounds are rejected consistently.
 */
class SlotSelectionPolicyEnforcementTest extends TestCase
{
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
        $zone = SportZone::create(['venue_id' => $venue->id, 'name' => 'Zone', 'sport_type' => 'badminton', 'slot_duration' => $slotDuration, 'is_active' => true]);
        $court = Court::create(['venue_id' => $venue->id, 'sport_zone_id' => $zone->id, 'name' => 'Court', 'is_active' => true]);
        PriceRule::create(['organization_id' => $org->id, 'court_id' => $court->id, 'type' => 'base', 'price' => 150000, 'is_active' => true]);

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

    private function holdV2(User $user, Organization $org, Court $court, array $starts)
    {
        return $this->actingAs($user)
            ->withHeaders(['X-Organization-ID' => (string) $org->id, 'Idempotency-Key' => uniqid('hold-')])
            ->postJson('/api/v2/reservations', ['court_id' => $court->id, 'slots' => $starts]);
    }

    private function checkoutV2(User $user, Organization $org, Court $court, array $starts)
    {
        return $this->actingAs($user)
            ->withHeaders(['X-Organization-ID' => (string) $org->id, 'Idempotency-Key' => uniqid('checkout-')])
            ->postJson('/api/v2/reservations/checkout', ['court_id' => $court->id, 'slots' => $starts]);
    }

    private function bookV1(User $user, Court $court, array $starts)
    {
        return $this->actingAs($user)->postJson('/api/v1/bookings', ['court_id' => $court->id, 'slots' => $starts]);
    }

    public function test_v1_booking_overlapping_an_existing_v2_hold_with_nonidentical_start_is_rejected(): void
    {
        ['org' => $org, 'user' => $holder, 'court' => $court] = $this->tenant(90);
        $base = Carbon::now(config('app.timezone'))->addDays(3)->setTime(10, 0);

        // Existing V2 hold: 10:00–11:30 (90-minute zone).
        $this->holdV2($holder, $org, $court, [$base->toIso8601String()])->assertCreated();

        // V1 booking 11:00–12:00 starts differently but overlaps the reserved interval.
        $intruder = $this->member($org);
        $this->fundWallet($intruder, $org->id);
        $this->bookV1($intruder, $court, [$base->copy()->setTime(11, 0)->toIso8601String()])
            ->assertStatus(409);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_v2_hold_overlapping_an_existing_v1_booking_with_nonidentical_start_is_rejected(): void
    {
        ['org' => $org, 'user' => $holder, 'court' => $court] = $this->tenant(90);
        $this->fundWallet($holder, $org->id);
        $base = Carbon::now(config('app.timezone'))->addDays(3)->setTime(11, 0);

        // Existing V1 booking: 11:00–12:00.
        $this->bookV1($holder, $court, [$base->toIso8601String()])->assertCreated();

        // V2 hold 10:00–11:30 overlaps.
        $intruder = $this->member($org);
        $this->holdV2($intruder, $org, $court, [$base->copy()->setTime(10, 0)->toIso8601String()])
            ->assertStatus(409);

        // Only the V1 booking's backfilled entitlement exists; the rejected hold added none.
        $this->assertDatabaseMissing('reservations', ['status' => 'held']);
    }

    public function test_v2_checkout_overlapping_an_existing_v1_booking_with_nonidentical_start_is_rejected(): void
    {
        ['org' => $org, 'user' => $holder, 'court' => $court] = $this->tenant(90);
        $this->fundWallet($holder, $org->id);
        $base = Carbon::now(config('app.timezone'))->addDays(3)->setTime(11, 0);

        $this->bookV1($holder, $court, [$base->toIso8601String()])->assertCreated();

        $intruder = $this->member($org);
        $this->fundWallet($intruder, $org->id);
        $this->checkoutV2($intruder, $org, $court, [$base->copy()->setTime(10, 0)->toIso8601String()])
            ->assertStatus(409);
    }

    public function test_two_v2_holds_with_nonidentical_overlapping_starts_conflict(): void
    {
        ['org' => $org, 'user' => $first, 'court' => $court] = $this->tenant(90);
        $base = Carbon::now(config('app.timezone'))->addDays(4)->setTime(10, 0);

        $this->holdV2($first, $org, $court, [$base->toIso8601String()])->assertCreated();

        $second = $this->member($org);
        $this->holdV2($second, $org, $court, [$base->copy()->setTime(11, 0)->toIso8601String()])
            ->assertStatus(409);

        $this->assertDatabaseCount('reservation_entitlements', 1);
    }

    public function test_v2_hold_and_checkout_reject_past_slots(): void
    {
        ['org' => $org, 'user' => $user, 'court' => $court] = $this->tenant();
        $this->fundWallet($user, $org->id);
        $past = Carbon::now(config('app.timezone'))->subDay()->setTime(10, 0);

        $this->holdV2($user, $org, $court, [$past->toIso8601String()])->assertStatus(422);
        $this->checkoutV2($user, $org, $court, [$past->toIso8601String()])->assertStatus(422);
    }

    public function test_over_horizon_slot_is_rejected_on_every_path(): void
    {
        $policy = ['hold_ttl_minutes' => 30, 'max_days_ahead' => 7, 'min_duration_minutes' => 30, 'max_duration_minutes' => 240, 'require_continuity' => false];
        ['org' => $org, 'user' => $user, 'court' => $court] = $this->tenant(60, $policy);
        $this->fundWallet($user, $org->id);
        $far = Carbon::now(config('app.timezone'))->addDays(30)->setTime(10, 0)->toIso8601String();

        $this->bookV1($user, $court, [$far])->assertStatus(422);
        $this->holdV2($user, $org, $court, [$far])->assertStatus(422);
        $this->checkoutV2($user, $org, $court, [$far])->assertStatus(422);

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('reservations', 0);
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

        $this->bookV1($user, $court, $gapped)->assertStatus(422);
        $this->holdV2($user, $org, $court, $gapped)->assertStatus(422);
    }

    public function test_gapped_selection_is_allowed_on_v1_when_continuity_is_not_required(): void
    {
        $policy = ['hold_ttl_minutes' => 30, 'max_days_ahead' => 60, 'min_duration_minutes' => 60, 'max_duration_minutes' => 240, 'require_continuity' => false];
        ['org' => $org, 'user' => $user, 'court' => $court] = $this->tenant(60, $policy);
        $this->fundWallet($user, $org->id);
        $base = Carbon::now(config('app.timezone'))->addDays(3);
        $gapped = [
            $base->copy()->setTime(10, 0)->toIso8601String(),
            $base->copy()->setTime(12, 0)->toIso8601String(),
        ];

        $this->bookV1($user, $court, $gapped)->assertCreated();
        $this->assertDatabaseCount('booking_slots', 2);
    }

    public function test_total_session_duration_below_minimum_is_rejected(): void
    {
        $policy = ['hold_ttl_minutes' => 30, 'max_days_ahead' => 60, 'min_duration_minutes' => 120, 'max_duration_minutes' => 240, 'require_continuity' => false];
        ['org' => $org, 'user' => $user, 'court' => $court] = $this->tenant(60, $policy);
        $slot = [Carbon::now(config('app.timezone'))->addDays(3)->setTime(10, 0)->toIso8601String()];

        $this->bookV1($user, $court, $slot)->assertStatus(422);
        $this->holdV2($user, $org, $court, $slot)->assertStatus(422);
    }

    public function test_total_session_duration_above_maximum_is_rejected(): void
    {
        $policy = ['hold_ttl_minutes' => 30, 'max_days_ahead' => 60, 'min_duration_minutes' => 60, 'max_duration_minutes' => 90, 'require_continuity' => false];
        ['org' => $org, 'user' => $user, 'court' => $court] = $this->tenant(60, $policy);
        $base = Carbon::now(config('app.timezone'))->addDays(3);
        $twoHours = [
            $base->copy()->setTime(10, 0)->toIso8601String(),
            $base->copy()->setTime(11, 0)->toIso8601String(),
        ];

        $this->bookV1($user, $court, $twoHours)->assertStatus(422);
        $this->holdV2($user, $org, $court, $twoHours)->assertStatus(422);
    }
}
