<?php

namespace Tests\Feature\Pricing;

use App\Domain\Pricing\DTOs\RentalQuote;
use App\Models\Court;
use App\Models\Organization;
use App\Models\PriceTable;
use App\Models\Venue;
use App\Models\VenueOperatingSchedule;
use App\Services\Pricing\RentalPriceCalculator;
use App\Services\Reservation\VenueOperatingCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\PricesCourts;
use Tests\TestCase;

/** Per-minute rental calculation (design D4, D5, D6, D15). */
class RentalPriceCalculatorTest extends TestCase
{
    use PricesCourts;
    use RefreshDatabase;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 08:00', 'Asia/Ho_Chi_Minh'));
        $organization = Organization::create(['name' => 'Engine Club', 'slug' => 'engine-'.Str::lower(Str::random(8)), 'status' => 'active']);
        $venue = Venue::create(['organization_id' => $organization->id, 'name' => 'Engine Venue', 'slug' => 'engine-venue-'.Str::lower(Str::random(8)), 'is_active' => true]);
        $this->court = Court::create(['venue_id' => $venue->id, 'name' => 'Engine Court', 'is_active' => true]);
    }

    public function test_booking_across_two_intervals_is_charged_per_minute(): void
    {
        $table = $this->priceCourtWith($this->court, [['10:00', '16:00', 100000], ['16:00', '22:00', 140000]]);

        $quote = $this->quote('2026-10-26 15:30', '2026-10-26 17:00');

        $this->assertSame([190000, 0, 190000], [$quote->rental, $quote->discount, $quote->total]);
        $this->assertNull($quote->snapshot['voucher']);
        $this->assertSame(
            [[30, 100000, '2026-10-26T08:30:00+00:00'], [60, 140000, '2026-10-26T09:00:00+00:00']],
            array_map(fn (array $part): array => [$part['minutes'], $part['price_per_hour'], $part['starts_at']], $quote->snapshot['parts']),
        );
        $this->assertSame([$table->id], array_values(array_unique(array_column($quote->snapshot['parts'], 'price_table_id'))));
    }

    public function test_eighty_minutes_drop_the_fraction(): void
    {
        $this->priceCourt($this->court, 100000);

        $this->assertSame(133333, $this->quote('2026-10-26 10:00', '2026-10-26 11:20')->rental);
    }

    public function test_fraction_is_dropped_once_after_adding_every_part(): void
    {
        $this->priceCourtWith($this->court, [['10:00', '16:00', 100001], ['16:00', '22:00', 140001]]);

        $this->assertSame(120001, $this->quote('2026-10-26 15:30', '2026-10-26 16:30')->rental);
    }

    public function test_holiday_prices_replace_normal_prices_on_listed_dates(): void
    {
        $this->priceCourt($this->court, 100000);
        $this->priceCourtHolidays($this->court, ['2027-01-01'], [['06:00', '20:00', 180000]]);

        $this->assertSame(270000, $this->quote('2027-01-01 18:00', '2027-01-01 19:30')->rental);
        $this->assertSame(100000, $this->quote('2027-01-02 18:00', '2027-01-02 19:00')->rental);
        // Normal prices do not fill the hours the holiday table leaves out.
        $this->assertRejected('2027-01-01 19:30', '2027-01-01 20:30', 'Khung giờ 01/01 20:00 chưa mở bán.');
    }

    public function test_unpriced_minute_is_rejected(): void
    {
        $this->priceCourtWith($this->court, [['06:00', '22:00', 100000]]);

        $this->assertRejected('2026-10-26 21:30', '2026-10-26 22:30', 'Khung giờ 26/10 22:00 chưa mở bán.');
    }

    public function test_version_published_after_confirmation_does_not_apply(): void
    {
        $table = $this->priceCourt($this->court, 140000);
        $this->publishPriceVersion($table, [['00:00', '23:59', 999000]], now()->addHour(), publishedAt: now()->addHour());

        $this->assertSame(140000, $this->quote('2026-10-26 10:00', '2026-10-26 11:00')->rental);

        $this->travel(2)->hours();
        $this->assertSame(999000, $this->quote('2026-10-26 10:00', '2026-10-26 11:00')->rental);
    }

    public function test_version_taking_effect_inside_a_booking_prices_its_own_minutes(): void
    {
        $table = $this->priceCourt($this->court, 140000);
        $this->publishPriceVersion($table, [['00:00', '23:59', 160000]], CarbonImmutable::parse('2026-10-26 18:00', 'Asia/Ho_Chi_Minh'));

        $quote = $this->quote('2026-10-26 17:00', '2026-10-26 19:00');

        $this->assertSame(300000, $quote->rental);
        $this->assertCount(2, array_unique(array_column($quote->snapshot['parts'], 'price_table_version_id')));
    }

    public function test_link_switch_inside_a_booking_splits_it(): void
    {
        $switch = CarbonImmutable::parse('2026-10-26 16:00', 'Asia/Ho_Chi_Minh');
        $first = $this->createPriceTable($this->court, PriceTable::KIND_NORMAL, [['00:00', '23:59', 100000]]);
        $second = $this->createPriceTable($this->court, PriceTable::KIND_NORMAL, [['00:00', '23:59', 160000]]);
        $this->linkPriceTable($first, $this->court, now()->subDay(), $switch);
        $this->linkPriceTable($second, $this->court, $switch);

        $quote = $this->quote('2026-10-26 15:00', '2026-10-26 17:00');

        $this->assertSame(260000, $quote->rental);
        $this->assertSame([$first->id, $second->id], array_column($quote->snapshot['parts'], 'price_table_id'));
    }

    public function test_interval_and_service_day_ending_at_2359_charge_the_last_full_hour(): void
    {
        VenueOperatingSchedule::create([
            'venue_id' => $this->court->venue_id,
            'scope_key' => 'venue',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'weekly_hours' => array_fill_keys(range(1, 7), ['closed' => false, 'intervals' => [['start' => '06:00', 'end' => '23:59']]]),
        ]);
        $this->priceCourtWith($this->court, [['06:00', '23:59', 120000]]);
        [$start, $end] = [CarbonImmutable::parse('2026-10-26 23:00', 'Asia/Ho_Chi_Minh'), CarbonImmutable::parse('2026-10-27 00:00', 'Asia/Ho_Chi_Minh')];

        $this->assertTrue(app(VenueOperatingCalendar::class)->isOpen($this->court, $start->toMutable(), $end->toMutable()));
        $quote = $this->quote('2026-10-26 23:00', '2026-10-27 00:00');
        $this->assertSame(120000, $quote->rental);
        $this->assertSame(60, $quote->snapshot['parts'][0]['minutes']);
    }

    public function test_interval_crossing_midnight_is_rejected(): void
    {
        $this->priceCourt($this->court, 100000);

        $this->assertRejected('2026-10-26 23:30', '2026-10-27 00:30', 'Khung giờ 26/10 23:30 phải kết thúc trong cùng ngày.');
    }

    private function quote(string $startsAt, string $endsAt): RentalQuote
    {
        return app(RentalPriceCalculator::class)->quote(
            $this->court,
            CarbonImmutable::parse($startsAt, 'Asia/Ho_Chi_Minh'),
            CarbonImmutable::parse($endsAt, 'Asia/Ho_Chi_Minh'),
            null,
        );
    }

    private function assertRejected(string $startsAt, string $endsAt, string $message): void
    {
        try {
            $this->quote($startsAt, $endsAt);
            $this->fail('Expected the quote to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(422, $exception->status);
            $this->assertSame([$message], $exception->errors()['slots']);
        }
    }
}
