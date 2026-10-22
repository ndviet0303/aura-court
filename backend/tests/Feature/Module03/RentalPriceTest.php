<?php

namespace Tests\Feature\Module03;

use App\Models\Court;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Services\Pricing\RentalPriceCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsVenues;
use Tests\Concerns\OffersVouchers;
use Tests\TestCase;

/**
 * The rental before any voucher: per-minute parts, the half-open boundaries, the floor, holidays, and new versions
 * against confirmed bookings (FR-56, FR-58, BR-53..BR-56, BR-67; UC-3.5, UC-3.7). Now is Monday 19/10/2026 08:00.
 */
class RentalPriceTest extends TestCase
{
    use BuildsVenues;
    use OffersVouchers;
    use RefreshDatabase;

    private Venue $venue;

    private Court $court;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 08:00', 'Asia/Ho_Chi_Minh'));
        $this->venue = $this->openVenue();
        $this->court = $this->activeCourt($this->venue)->load('venue');
        $this->customer = $this->customer($this->court);
    }

    public function test_br_55_fr_58_uc_3_7_a_booking_across_price_intervals_is_charged_per_minute(): void
    {
        $this->priceCourtWith($this->court, [['06:00', '16:00', 100000], ['16:00', '22:00', 140000]]);

        $quote = $this->quote('2026-10-26 15:30', '2026-10-26 17:00')->assertCreated()->json('quote');

        $this->assertSame(190000, (int) $quote['subtotal']);
        $this->assertSame(190000, (int) $quote['total_amount']);
        $parts = collect($quote['slot_snapshot'])->flatMap(fn (array $slot): array => $slot['parts'])->map(fn (array $part): array => [$part['minutes'], $part['price_per_hour']])->all();
        $this->assertSame([[30, 100000], [60, 140000]], $parts);
    }

    public function test_br_55_a_booking_starts_and_ends_on_the_30_minute_grid(): void
    {
        $this->priceCourt($this->court, 100000);

        $this->quote('2026-10-26 10:15', '2026-10-26 11:15')->assertUnprocessable();
        $this->checkout($this->customer, $this->court, '2026-10-26 10:00', '2026-10-26 10:45', 75000)->assertUnprocessable();
        $this->assertSame(0, Reservation::count());
    }

    public function test_br_54_an_interval_end_belongs_to_the_next_interval(): void
    {
        $this->priceCourtWith($this->court, [['06:00', '16:00', 100000], ['16:00', '22:00', 140000]]);

        $this->assertSame(50000, $this->rental('2026-10-26 15:30', '2026-10-26 16:00'));
        $this->assertSame(70000, $this->rental('2026-10-26 16:00', '2026-10-26 16:30'));
    }

    public function test_br_67_parts_are_summed_before_the_fraction_is_dropped(): void
    {
        $this->priceCourtWith($this->court, [['06:00', '16:00', 100001], ['16:00', '22:00', 100001]]);

        // 30 × 100001 / 60 = 50000.5 each; summed first, 100001 — never rounded up, never floored per part.
        $this->assertSame(50000, $this->rental('2026-10-26 15:30', '2026-10-26 16:00'));
        $this->assertSame(100001, $this->rental('2026-10-26 15:30', '2026-10-26 16:30'));
    }

    public function test_br_52_fr_58_unpriced_time_cannot_be_quoted(): void
    {
        // Coverage is checked when links change; a court linked around that check can still have a gap. It then takes
        // no booking at all, and the engine refuses the unpriced minutes instead of pricing them some other way.
        $this->priceCourtWith($this->court, [['06:00', '20:00', 100000]]);

        $this->quote('2026-10-26 10:00', '2026-10-26 11:00')->assertNotFound();
        $this->expectException(ValidationException::class);
        $this->rental('2026-10-26 19:30', '2026-10-26 20:30');
    }

    public function test_br_53_holiday_prices_win_on_their_date_and_never_fall_back_to_normal_prices(): void
    {
        $this->priceCourt($this->court, 100000);
        $this->priceCourtHolidays($this->court, ['2026-10-27'], [['06:00', '12:00', 180000]]);

        $this->assertSame(180000, $this->rental('2026-10-27 10:00', '2026-10-27 11:00'));
        $this->assertSame(100000, $this->rental('2026-10-26 10:00', '2026-10-26 11:00'));
        // 12:00–22:00 of the holiday has no holiday price, and the normal price does not stand in.
        $this->expectException(ValidationException::class);
        $this->rental('2026-10-27 13:00', '2026-10-27 14:00');
    }

    public function test_fr_56_br_56_uc_3_5_a_new_version_prices_play_from_its_effective_time_and_keeps_the_old_one(): void
    {
        $table = $this->priceCourt($this->court, 100000);
        $owner = $this->ownerOf($this->venue->organization);

        $this->actingAs($owner)->postJson("/api/admin/price-tables/{$table->id}/versions", [
            'effective_at' => '2026-10-25T00:00:00+07:00',
            'intervals' => [['weekdays' => [1, 2, 3, 4, 5, 6, 7], 'start' => '00:00', 'end' => '23:59', 'price_per_hour' => 150000]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.versions.0.state', 'scheduled')
            ->assertJsonPath('data.versions.1.state', 'active');

        $this->assertSame(100000, $this->rental('2026-10-24 10:00', '2026-10-24 11:00'));
        $this->assertSame(150000, $this->rental('2026-10-26 10:00', '2026-10-26 11:00'));
        $this->assertSame(2, $table->versions()->count());
    }

    public function test_br_56_fr_56_a_booking_confirmed_before_a_price_change_keeps_its_amount(): void
    {
        $table = $this->priceCourt($this->court, 100000);
        $id = $this->checkout($this->customer, $this->court, '2026-10-26 10:00', '2026-10-26 11:00', 100000)->assertCreated()->json('id')
            ?? Reservation::sole()->id;
        $this->travel(1)->hours();

        $this->actingAs($this->ownerOf($this->venue->organization))->postJson("/api/admin/price-tables/{$table->id}/versions", [
            'intervals' => [['weekdays' => [1, 2, 3, 4, 5, 6, 7], 'start' => '00:00', 'end' => '23:59', 'price_per_hour' => 150000]],
        ])->assertCreated();

        $reservation = Reservation::with('slots')->findOrFail($id);
        $this->assertSame('confirmed', $reservation->status);
        $this->assertSame(100000, (int) $reservation->total_amount);
        $this->assertSame(100000, (int) $reservation->slots->sole()->getAttribute('unit_price'));
        // The engine still prices that booking as confirmed at its time; a booking confirmed now pays the new price.
        $start = CarbonImmutable::parse('2026-10-26 10:00', 'Asia/Ho_Chi_Minh');
        $this->assertSame(100000, app(RentalPriceCalculator::class)->rentalConfirmedAt($this->court, $start, $start->addHour(), $reservation->confirmed_at)->rental);
        $this->assertSame(150000, $this->rental('2026-10-26 10:00', '2026-10-26 11:00'));
    }

    public function test_br_56_br_68_a_price_raised_before_confirmation_asks_the_customer_to_review(): void
    {
        $table = $this->priceCourt($this->court, 100000);
        $this->publishPriceVersion($table, [['00:00', '23:59', 120000]], now());

        $this->checkout($this->customer, $this->court, '2026-10-26 10:00', '2026-10-26 11:00', 100000)
            ->assertStatus(409)
            ->assertJsonPath('code', 'PRICE_CHANGED')
            ->assertJsonPath('total', 120000);
        $this->assertSame(0, Reservation::count());
    }

    private function quote(string $start, string $end): TestResponse
    {
        return $this->actingAs($this->customer)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/price-quotes', [
            'court_id' => $this->court->id,
            'starts_at' => $start.':00+07:00',
            'ends_at' => $end.':00+07:00',
        ]);
    }

    private function rental(string $start, string $end): int
    {
        return app(RentalPriceCalculator::class)->quote(
            $this->court,
            CarbonImmutable::parse($start, 'Asia/Ho_Chi_Minh'),
            CarbonImmutable::parse($end, 'Asia/Ho_Chi_Minh'),
            null,
        )->rental;
    }
}
