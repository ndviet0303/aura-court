<?php

namespace Tests\Feature\Module04;

use App\Models\OwnerPaymentAttempt;
use App\Models\PriceQuote;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BooksCourts;
use Tests\TestCase;

/** BR-73, BR-75, FR-71: one court, one continuous interval on the 30-minute grid, at least 30 minutes, not past. */
class BookingIntervalTest extends TestCase
{
    use BooksCourts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 09:00:00');
    }

    public function test_br_75_half_hour_boundaries_make_one_interval_booking(): void
    {
        $court = $this->payableCourt();

        $code = $this->checkout($this->customer(), $court, '2026-11-03 15:30', '2026-11-03 17:00', 150000)
            ->assertCreated()->assertJsonPath('reservation.status', 'held')->json('reservation.code');

        $reservation = Reservation::where('code', $code)->firstOrFail();
        $this->assertSame(150000, (int) $reservation->total_amount);
        $slots = ReservationSlot::where('reservation_id', $reservation->id)->get();
        $this->assertCount(1, $slots);
        $this->assertSame('2026-11-03 15:30', $slots[0]->starts_at->setTimezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-03 17:00', $slots[0]->ends_at->setTimezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i'));
        $this->assertSame(1, ReservationEntitlement::where('reservation_id', $reservation->id)->count());
    }

    public function test_br_75_thirty_minute_minimum_and_end_after_start(): void
    {
        $court = $this->payableCourt();
        $user = $this->customer();

        $this->checkout($user, $court, '2026-11-03 15:00', '2026-11-03 15:30', 50000)->assertCreated();
        $this->checkout($user, $court, '2026-11-03 16:00', '2026-11-03 16:00', 0)->assertStatus(422)->assertJsonPath('code', 'INVALID_BOOKING_TIME');
        $this->checkout($user, $court, '2026-11-03 17:00', '2026-11-03 16:00', 0)->assertStatus(422)->assertJsonPath('code', 'INVALID_BOOKING_TIME');
        $this->assertSame(1, Reservation::count());
    }

    public function test_br_75_off_grid_times_are_refused_without_records(): void
    {
        $court = $this->payableCourt();
        $user = $this->customer();

        $this->checkout($user, $court, '2026-11-03 15:25', '2026-11-03 16:30', 200000)->assertStatus(422)->assertJsonPath('code', 'INVALID_BOOKING_TIME');
        $this->checkout($user, $court, '2026-11-03 15:00', '2026-11-03 16:10', 200000)->assertStatus(422)->assertJsonPath('code', 'INVALID_BOOKING_TIME');
        $this->command($user, '/api/price-quotes', ['court_id' => $court->id, 'starts_at' => '2026-11-03 15:25', 'ends_at' => '2026-11-03 16:00'])
            ->assertStatus(422)->assertJsonPath('code', 'INVALID_BOOKING_TIME');

        $this->assertSame(0, Reservation::count());
        $this->assertSame(0, PriceQuote::count());
        $this->assertSame(0, OwnerPaymentAttempt::count());
    }

    public function test_br_73_a_list_of_start_times_is_refused(): void
    {
        $court = $this->payableCourt();

        $this->command($this->customer(), '/api/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => ['2026-11-03 15:00', '2026-11-03 17:00'],
            'expected_total' => 200000,
        ])->assertStatus(422)->assertJsonPath('code', 'INVALID_BOOKING_TIME');
        $this->assertSame(0, Reservation::count());
    }

    public function test_br_73_one_account_books_several_intervals_each_checked_on_its_own(): void
    {
        $court = $this->payableCourt();
        $user = $this->customer();

        $this->checkout($user, $court, '2026-11-03 15:00', '2026-11-03 16:00', 100000)->assertCreated();
        $this->checkout($user, $court, '2026-11-03 17:00', '2026-11-03 18:00', 100000)->assertCreated();
        // The second request of the same account overlapping its own hold is refused like anyone's.
        $this->checkout($user, $court, '2026-11-03 15:30', '2026-11-03 16:30', 100000)->assertStatus(409);
        $this->assertSame(2, Reservation::where('user_id', $user->id)->count());
    }

    public function test_br_75_past_start_is_refused(): void
    {
        $court = $this->payableCourt();

        $this->checkout($this->customer(), $court, '2026-11-02 08:30', '2026-11-02 09:30', 100000)
            ->assertStatus(422)->assertJsonPath('code', 'INVALID_BOOKING_TIME');
    }

    public function test_br_75_interval_spans_one_play_date_and_may_end_at_midnight(): void
    {
        $court = $this->activeCourt($this->openVenue([], '06:00', '23:59'));
        $this->priceCourt($court);
        $this->enableDirectPayments();
        $this->connectVenue($court->venue);
        $user = $this->customer();

        $this->checkout($user, $court, '2026-11-03 23:00', '2026-11-04 00:30', 150000)->assertStatus(422)->assertJsonPath('code', 'INVALID_BOOKING_TIME');
        $this->checkout($user, $court, '2026-11-03 23:00', '2026-11-04 00:00', 100000)->assertCreated();
    }

    public function test_br_75_court_interval_length_does_not_shape_customer_bookings(): void
    {
        $court = $this->payableCourt();
        $this->assertSame(60, (int) $court->slot_duration);

        $this->checkout($this->customer(), $court, '2026-11-03 16:30', '2026-11-03 17:30', 100000)->assertCreated();
    }

    public function test_fr_70_availability_uses_the_30_minute_grid_and_marks_a_booking_busy(): void
    {
        $court = $this->payableCourt();
        $this->checkout($this->customer(), $court, '2026-11-03 19:00', '2026-11-03 21:00', 200000)->assertCreated();

        $slots = collect($this->getJson("/api/availability?court_id={$court->id}&date=2026-11-03")->assertOk()->json());
        $busy = $slots->filter(fn (array $slot) => ! $slot['available'])->map(fn (array $slot) => substr($slot['startsAt'], 11, 5))->values()->all();

        $this->assertSame(['19:00', '19:30', '20:00', '20:30'], $busy);
    }
}
