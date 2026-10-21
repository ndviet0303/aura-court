<?php

namespace Tests\Feature\Pricing;

use App\Models\Venue;
use App\Models\VenueOperatingSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BooksDirectOwnerCourt;
use Tests\TestCase;

/** Court quotes priced by the engine, one call per run of adjacent slots (design D5, D29). */
class PriceQuoteFromPriceTablesTest extends TestCase
{
    use BooksDirectOwnerCourt;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 08:00', 'Asia/Ho_Chi_Minh'));
        $this->fixture = $this->directOwnerCourt();
    }

    public function test_slot_across_a_price_change_on_a_court_opening_at_half_past_is_charged_per_minute(): void
    {
        $this->openDaily($this->fixture['venue'], '06:30', '22:00');
        $this->prices([['06:30', '18:00', 100000], ['18:00', '22:00', 160000]]);

        $quote = $this->quote(['17:30'])->assertCreated()->json('quote');

        $this->assertSame(130000, $quote['total_amount']);
        $this->assertSame(130000, $quote['subtotal']);
        $this->assertNull($quote['benefit_snapshot']);
        $this->assertSame([[30, 100000], [30, 160000]], array_map(
            fn (array $part): array => [$part['minutes'], $part['price_per_hour']],
            $quote['slot_snapshot'][0]['parts'],
        ));
    }

    public function test_adjacent_slots_drop_the_fraction_once_and_the_last_slot_takes_the_rest(): void
    {
        $this->prices([['00:00', '16:00', 100001], ['16:00', '17:00', 140000], ['17:00', '23:59', 100001]]);

        $quote = $this->quote(['15:30', '16:30'])->assertCreated()->json('quote');

        // 120000.5 + 120000.5: the run is 240001, while each slot alone would drop its half.
        $this->assertSame(240001, $quote['total_amount']);
        $this->assertSame([120000, 120001], array_column($quote['slot_snapshot'], 'unit_price'));
    }

    public function test_quote_keeps_its_token_and_fifteen_minute_expiry(): void
    {
        $quote = $this->quote(['10:00'])->assertCreated()->json('quote');

        $this->assertTrue(Str::isUuid($quote['quote_token']));
        $this->assertSame('open', $quote['status']);
        $this->assertSame(200000, $quote['total_amount']);
        $this->assertTrue(now()->addMinutes(15)->equalTo(CarbonImmutable::parse($quote['expires_at'])));
    }

    public function test_last_slot_of_a_day_open_until_2359_is_quoted_for_its_full_hour(): void
    {
        $this->openDaily($this->fixture['venue'], '06:00', '23:59');

        $this->quote(['23:00'])->assertCreated()->assertJsonPath('quote.total_amount', 200000);
    }

    public function test_slot_across_midnight_is_outside_the_operating_hours(): void
    {
        $this->openDaily($this->fixture['venue'], '06:00', '23:59');

        $this->quote(['23:30'])->assertUnprocessable()->assertJsonPath('errors.slots.0', 'Khung giờ đã chọn nằm ngoài lịch hoạt động của sân.');
        $this->assertDatabaseCount('price_quotes', 0);
    }

    /** Local start times on 21/10/2026. */
    private function quote(array $times): TestResponse
    {
        $starts = array_map(fn (string $time): CarbonImmutable => CarbonImmutable::parse("2026-10-21 {$time}", 'Asia/Ho_Chi_Minh'), $times);

        return $this->quoteFor($this->fixture['payer'], $this->fixture['organization'], $this->fixture['court'], $starts);
    }

    /** Replaces the fixture's 200.000 ₫/h all day from now on. */
    private function prices(array $intervals): void
    {
        $this->publishPriceVersion($this->fixture['table'], $intervals, now());
    }

    private function openDaily(Venue $venue, string $opens, string $closes): void
    {
        VenueOperatingSchedule::create([
            'venue_id' => $venue->id, 'scope_key' => 'venue', 'timezone' => 'Asia/Ho_Chi_Minh', 'version' => 1,
            'weekly_hours' => array_fill_keys(range(1, 7), ['closed' => false, 'intervals' => [['start' => $opens, 'end' => $closes]]]),
        ]);
    }
}
