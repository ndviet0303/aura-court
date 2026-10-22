<?php

namespace Tests\Feature\Module04;

use App\Models\Court;
use App\Models\OperationalStop;
use App\Models\PriceQuote;
use App\Models\Reservation;
use App\Models\VenuePackage;
use App\Models\VenueSuspension;
use App\Models\VoucherUse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BooksCourts;
use Tests\TestCase;

/** BR-72, BR-76, BR-78, FR-69, FR-70: who may book and which court-time is bookable; looking holds nothing. */
class BookingEligibilityTest extends TestCase
{
    use BooksCourts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 09:00:00');
    }

    public function test_fr_69_guest_finds_a_venue_and_its_courts(): void
    {
        $court = $this->payableCourt();

        $this->getJson('/api/venues')->assertOk()->assertSee($court->venue->slug);
        $this->getJson("/api/venues/{$court->venue->slug}")->assertOk()->assertSee($court->code);
        $this->getJson("/api/courts/{$court->id}")->assertOk()->assertJsonMissingPath('venue.organization.tax_code');
    }

    public function test_br_72_guest_sees_the_schedule_but_must_sign_in_to_book(): void
    {
        $court = $this->payableCourt();

        $this->getJson("/api/availability?court_id={$court->id}&date=2026-11-03")->assertOk();
        $this->postJson('/api/reservations/checkout', ['court_id' => $court->id, 'starts_at' => '2026-11-03 15:00', 'ends_at' => '2026-11-03 16:00', 'expected_total' => 100000], ['Idempotency-Key' => 'k1'])
            ->assertUnauthorized();
        $this->assertSame(0, Reservation::count());
    }

    public function test_br_72_unverified_or_locked_account_cannot_book(): void
    {
        $court = $this->payableCourt();

        $this->checkout($this->customer(['email_verified_at' => null]), $court, '2026-11-03 15:00', '2026-11-03 16:00', 100000)->assertForbidden();
        $this->checkout($this->customer(['status' => 'locked']), $court, '2026-11-03 15:00', '2026-11-03 16:00', 100000)->assertForbidden();
        $this->assertSame(0, Reservation::count());
    }

    public function test_br_76_court_or_venue_not_taking_bookings_is_refused(): void
    {
        $court = $this->payableCourt();
        $user = $this->customer();

        $court->forceFill(['status' => Court::STATUS_PAUSED])->save();
        $this->checkout($user, $court, '2026-11-03 15:00', '2026-11-03 16:00', 100000)->assertStatus(404);

        $court->forceFill(['status' => Court::STATUS_ACTIVE])->save();
        VenueSuspension::create(['venue_id' => $court->venue_id, 'reason' => 'Vi phạm', 'suspended_at' => now()->subHour()]);
        $this->checkout($user, $court, '2026-11-03 15:00', '2026-11-03 16:00', 100000)->assertStatus(404);
        $this->assertSame(0, Reservation::count());
    }

    public function test_br_76_venue_without_an_active_package_is_refused(): void
    {
        $court = $this->payableCourt();
        VenuePackage::where('venue_id', $court->venue_id)->delete();

        $this->checkout($this->customer(), $court, '2026-11-03 15:00', '2026-11-03 16:00', 100000)->assertStatus(422)->assertJsonValidationErrors('package');
        $this->assertSame(0, Reservation::count());
    }

    public function test_br_76_time_outside_service_hours_or_blocked_is_refused(): void
    {
        $court = $this->payableCourt();
        $user = $this->customer();

        // Before the venue opens at 06:00.
        $this->checkout($user, $court, '2026-11-03 05:00', '2026-11-03 06:30', 150000)->assertStatus(422);
        OperationalStop::create(['venue_id' => $court->venue_id, 'court_id' => $court->id, 'kind' => OperationalStop::KIND_MAINTENANCE, 'reason' => 'Sửa lưới', 'starts_at' => '2026-11-03 14:00', 'expected_end_at' => '2026-11-03 16:00', 'ended_at' => '2026-11-03 16:00']);
        $this->checkout($user, $court, '2026-11-03 15:00', '2026-11-03 16:00', 100000)->assertConflict();

        $this->assertSame(0, Reservation::count());
        $this->checkout($user, $court, '2026-11-03 16:00', '2026-11-03 17:00', 100000)->assertCreated();
    }

    public function test_br_76_court_whose_prices_do_not_cover_its_service_hours_is_not_bookable(): void
    {
        $this->enableDirectPayments();
        $court = $this->activeCourt($this->openVenue());
        $this->priceCourtWith($court, [['06:00', '20:00', 100000]]);
        $this->connectVenue($court->venue);

        // A gap at 20:00–22:00 stops the whole court, priced hours included (module 03 coverage rule).
        $this->checkout($this->customer(), $court, '2026-11-03 16:00', '2026-11-03 17:00', 100000)->assertNotFound();
        $this->assertSame(0, Reservation::count());
    }

    public function test_br_78_viewing_the_schedule_and_quoting_hold_nothing(): void
    {
        $court = $this->payableCourt();
        $user = $this->customer();

        $this->getJson("/api/availability?court_id={$court->id}&date=2026-11-03")->assertOk();
        $this->command($user, '/api/price-quotes', ['court_id' => $court->id, 'starts_at' => '2026-11-03 15:00', 'ends_at' => '2026-11-03 16:00'])
            ->assertCreated()->assertJsonPath('quote.total_amount', 100000);

        $this->assertSame(0, Reservation::count());
        $this->assertSame(1, PriceQuote::count());
        $free = collect($this->getJson("/api/availability?court_id={$court->id}&date=2026-11-03")->json())->firstWhere('startsAt', '2026-11-03T15:00:00+07:00');
        $this->assertTrue($free['available']);
        // Another customer still gets the quoted time.
        $this->checkout($this->customer(), $court, '2026-11-03 15:00', '2026-11-03 16:00', 100000)->assertCreated();
    }

    public function test_br_78_a_held_voucher_use_is_not_given_to_another_request(): void
    {
        $court = $this->payableCourt();
        $program = $this->publicVoucher($court, now()->addMonth(), ['max_uses' => 1]);
        $voucher = ['voucher_program_id' => $program->id];

        $this->checkout($this->customer(), $court, '2026-11-03 15:00', '2026-11-03 16:00', 80000, $voucher)->assertCreated();
        $refused = $this->checkout($this->customer(), $court, '2026-11-03 17:00', '2026-11-03 18:00', 80000, $voucher);

        $this->assertContains($refused->status(), [409, 422], $refused->getContent());
        $this->assertSame(1, Reservation::count());
        $this->assertSame(1, VoucherUse::count());
        $this->assertSame(VoucherUse::STATUS_HELD, VoucherUse::value('status'));
    }
}
