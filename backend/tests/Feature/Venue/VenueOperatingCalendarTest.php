<?php

namespace Tests\Feature\Venue;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueOperatingSchedule;
use App\Models\VenueScheduleException;
use App\Services\Reservation\LegacyBookingBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PricesCourts;
use Tests\TestCase;

class VenueOperatingCalendarTest extends TestCase
{
    use PricesCourts;
    use RefreshDatabase;

    public function test_calendar_persists_timezone_exceptions_and_blocks_out_of_hours_booking_apis(): void
    {
        $owner = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $organization = Organization::create([
            'name' => 'Calendar Club',
            'slug' => 'calendar-club',
            'status' => 'active',
            'partner_status' => 'approved',
            'settings' => ['timezone' => 'Asia/Ho_Chi_Minh'],
        ]);
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => 'organization_owner',
            'status' => 'active',
        ]);
        $plan = PlatformPlan::create([
            'code' => 'CALENDAR-PLAN',
            'name' => 'Calendar Plan',
            'price' => 500000,
            'billing_cycle' => 'monthly',
            'max_venues' => 2,
            'max_courts' => 4,
            'terms_version' => '1',
            'terms_text' => 'Terms',
            'is_active' => true,
        ]);
        PlatformSubscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => ['vi' => 'Sân Lịch', 'en' => 'Calendar Court'],
            'slug' => 'calendar-court',
            'address' => '23 Calendar Street',
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = Court::create([
            'venue_id' => $venue->id,
            'sport_type' => 'badminton',
            'name' => 'Court 1',
            'slug' => 'calendar-court-1',
            'is_active' => true,
        ]);
        $date = Carbon::now('Asia/Ho_Chi_Minh')->addDays(2)->toDateString();
        $dayId = Carbon::parse($date, 'Asia/Ho_Chi_Minh')->dayOfWeekIso;
        $this->priceCourtWith($court, [['09:00', '10:00', 100000, [$dayId]]]);
        $slotStart = Carbon::parse("{$date} 09:00", 'Asia/Ho_Chi_Minh')->toIso8601String();
        $base = "/api/partner/organizations/{$organization->id}/venues/{$venue->id}";
        $weeklyHours = [];
        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $weeklyHours[(string) $weekday] = [
                'closed' => $weekday !== $dayId,
                'intervals' => $weekday === $dayId ? [['start' => '06:00', 'end' => '08:00']] : [],
            ];
        }

        Sanctum::actingAs($owner);
        $this->putJson("{$base}/operating-schedule", [
            'timezone' => 'Asia/Ho_Chi_Minh',
            'weekly_hours' => $weeklyHours,
        ])->assertOk()->assertJsonPath('data.version', 1);
        $this->getJson("{$base}/operating-schedule")
            ->assertOk()
            ->assertJsonPath("data.weekly_hours.{$dayId}.intervals.0.start", '06:00');

        $availabilityUrl = "/api/availability?court_id={$court->id}&date={$date}";
        $this->getJson($availabilityUrl)->assertOk()->assertJsonCount(0);

        $this->postJson('/api/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$slotStart],
            'expected_total' => 100000,
        ], ['Idempotency-Key' => 'calendar-outside-hours-checkout'])
            ->assertUnprocessable();
        $this->assertDatabaseMissing('reservations', ['court_id' => $court->id]);
        $this->postJson('/api/price-quotes', [
            'court_id' => $court->id,
            'slots' => [$slotStart],
        ], ['Idempotency-Key' => 'calendar-outside-hours'])
            ->assertUnprocessable();

        $this->putJson("{$base}/schedule-exceptions", [
            'exception_date' => $date,
            'name' => 'Tournament hours',
            'type' => 'special_hours',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'intervals' => [['start' => '08:00', 'end' => '10:00']],
        ])->assertCreated();
        $this->getJson($availabilityUrl)->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.startsAt', $slotStart)
            ->assertJsonPath('0.available', true);
        $this->postJson('/api/price-quotes', [
            'court_id' => $court->id,
            'slots' => [$slotStart],
        ], ['Idempotency-Key' => 'calendar-special-hours'])
            ->assertCreated();

        $this->putJson("{$base}/schedule-exceptions", [
            'exception_date' => $date,
            'name' => 'Holiday closure',
            'type' => 'closed',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ])->assertCreated();
        $this->getJson($availabilityUrl)->assertOk()->assertJsonCount(0);
        $this->postJson('/api/price-quotes', [
            'court_id' => $court->id,
            'slots' => [$slotStart],
        ], ['Idempotency-Key' => 'calendar-closed-exception'])
            ->assertUnprocessable();
    }

    public function test_operating_schedule_rejects_overlap_and_wrong_court_scope(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $organization = Organization::create(['name' => 'Scoped Calendar', 'slug' => 'scoped-calendar', 'status' => 'active']);
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => 'organization_owner',
            'status' => 'active',
        ]);
        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => ['vi' => 'Venue', 'en' => 'Venue'],
            'slug' => 'scoped-calendar-venue',
            'address' => '24 Test Street',
            'status' => 'draft',
            'is_active' => false,
        ]);
        $otherVenue = Venue::create([
            'organization_id' => $organization->id,
            'name' => ['vi' => 'Other', 'en' => 'Other'],
            'slug' => 'scoped-calendar-other',
            'address' => '25 Test Street',
            'status' => 'draft',
            'is_active' => false,
        ]);
        $otherCourt = Court::create([
            'venue_id' => $otherVenue->id,
            'name' => 'Other Court',
            'slug' => 'scoped-other-court',
            'is_active' => true,
        ]);
        Sanctum::actingAs($owner);
        $weeklyHours = [];
        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $weeklyHours[(string) $weekday] = ['closed' => true, 'intervals' => []];
        }
        $weeklyHours['1'] = ['closed' => false, 'intervals' => [
            ['start' => '08:00', 'end' => '12:00'],
            ['start' => '11:00', 'end' => '13:00'],
        ]];

        $this->putJson("/api/partner/organizations/{$organization->id}/venues/{$venue->id}/operating-schedule", [
            'timezone' => 'Asia/Ho_Chi_Minh',
            'weekly_hours' => $weeklyHours,
        ])->assertUnprocessable();

        $this->putJson("/api/partner/organizations/{$organization->id}/venues/{$venue->id}/operating-schedule", [
            'court_id' => $otherCourt->id,
            'timezone' => 'Asia/Ho_Chi_Minh',
            'weekly_hours' => $weeklyHours,
        ])->assertForbidden();
    }

    public function test_schedule_exception_and_downtime_cannot_hide_confirmed_legacy_or_reservation_commitments(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $customer = User::factory()->create(['role' => 'customer']);
        $organization = Organization::create([
            'name' => 'Protected Calendar Club',
            'slug' => 'protected-calendar-club',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => 'organization_owner',
            'status' => 'active',
        ]);
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $customer->id,
            'role' => 'customer',
            'status' => 'active',
        ]);
        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => ['vi' => 'Protected', 'en' => 'Protected'],
            'slug' => 'protected-calendar-venue',
            'address' => '26 Test Street',
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = Court::create([
            'venue_id' => $venue->id,
            'name' => 'Protected Court',
            'slug' => 'protected-court',
            'is_active' => true,
        ]);
        $start = Carbon::now('Asia/Ho_Chi_Minh')->addDays(3)->setTime(9, 0);
        $end = $start->copy()->addHour();
        $weekday = $start->dayOfWeekIso;
        $hours = [];
        for ($day = 1; $day <= 7; $day++) {
            $hours[(string) $day] = ['closed' => false, 'intervals' => [['start' => '06:00', 'end' => '22:00']]];
        }
        VenueOperatingSchedule::create([
            'venue_id' => $venue->id,
            'scope_key' => 'venue',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'version' => 1,
            'weekly_hours' => $hours,
            'created_by' => $owner->id,
        ]);
        $this->priceCourt($court);

        $booking = Booking::create([
            'code' => 'BK-CALENDAR-PROTECTED',
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'total_amount' => 100000,
            'status' => 'confirmed',
        ]);
        // The next hour: the mirror's reservation slot may not overlap RES-CALENDAR-PROTECTED (exclusion constraint).
        BookingSlot::create([
            'booking_id' => $booking->id,
            'court_id' => $court->id,
            'starts_at' => $end->copy()->utc(),
            'ends_at' => $end->copy()->addHour()->utc(),
            'unit_price' => 100000,
            'status' => 'confirmed',
        ]);
        app(LegacyBookingBackfillService::class)->backfillBooking($booking);
        $reservation = Reservation::create([
            'organization_id' => $organization->id,
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'code' => 'RES-CALENDAR-PROTECTED',
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => 100000,
            'confirmed_at' => now(),
        ]);
        ReservationSlot::create([
            'organization_id' => $organization->id,
            'reservation_id' => $reservation->id,
            'court_id' => $court->id,
            'starts_at' => $start->copy()->utc(),
            'ends_at' => $end->copy()->utc(),
            'unit_price' => 100000,
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($owner);
        $base = "/api/partner/organizations/{$organization->id}/venues/{$venue->id}";
        $closedHours = $hours;
        $closedHours[(string) $weekday] = ['closed' => false, 'intervals' => [['start' => '06:00', 'end' => '08:00']]];

        $this->putJson("{$base}/operating-schedule", [
            'timezone' => 'Asia/Ho_Chi_Minh',
            'weekly_hours' => $closedHours,
        ])->assertStatus(409)
            ->assertJsonFragment(['LEGACY-BK-CALENDAR-PROTECTED'])
            ->assertJsonFragment(['RES-CALENDAR-PROTECTED']);
        $this->assertSame(1, VenueOperatingSchedule::where('venue_id', $venue->id)->where('scope_key', 'venue')->value('version'));
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame('confirmed', Reservation::where('code', 'LEGACY-BK-CALENDAR-PROTECTED')->value('status'));
        $this->assertSame('confirmed', $reservation->fresh()->status);

        $this->putJson("{$base}/schedule-exceptions", [
            'exception_date' => $start->toDateString(),
            'name' => 'Closure conflicts with confirmed booking',
            'type' => 'closed',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ])->assertStatus(409)
            ->assertJsonFragment(['LEGACY-BK-CALENDAR-PROTECTED'])
            ->assertJsonFragment(['RES-CALENDAR-PROTECTED']);
        $this->assertSame(0, VenueScheduleException::where('venue_id', $venue->id)->count());

        $this->postJson("/api/admin/courts/{$court->id}/blocks", [
            'starts_at' => $start->copy()->toIso8601String(),
            'ends_at' => $end->copy()->addHour()->toIso8601String(),
            'reason' => 'Maintenance',
        ])->assertStatus(409)
            ->assertJsonFragment(['LEGACY-BK-CALENDAR-PROTECTED'])
            ->assertJsonFragment(['RES-CALENDAR-PROTECTED']);
    }
}
