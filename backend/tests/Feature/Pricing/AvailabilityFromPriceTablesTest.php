<?php

namespace Tests\Feature\Pricing;

use App\Models\Reservation;
use App\Models\Venue;
use App\Models\VenueOperatingSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BooksDirectOwnerCourt;
use Tests\TestCase;

/** Availability from the service schedule, priced by the engine, unpriced times left out (design D18). */
class AvailabilityFromPriceTablesTest extends TestCase
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

    public function test_court_open_until_2359_offers_a_free_bookable_2300_slot(): void
    {
        $this->openDaily($this->fixture['venue'], '06:00', '23:59');

        $this->assertSame(['price' => 200000, 'available' => true], $this->times()['23:00']);
        $this->checkoutFor($this->fixture['payer'], $this->fixture['organization'], $this->fixture['court'], [$this->at('23:00')])->assertCreated();
    }

    public function test_hours_without_a_price_are_not_offered_and_earlier_hours_show_the_engine_price(): void
    {
        // The table covers until 22:00; the schedule was extended to 23:59 afterwards.
        $this->publishPriceVersion($this->fixture['table'], [['06:00', '18:00', 100000], ['18:00', '22:00', 160000]], now());
        $this->openDaily($this->fixture['venue'], '06:00', '23:59');

        $times = $this->times();

        $this->assertSame(['06:00', '21:00'], [array_key_first($times), array_key_last($times)]);
        $this->assertCount(16, $times);
        $this->assertSame([100000, 160000], [$times['17:00']['price'], $times['21:00']['price']]);
    }

    public function test_court_priced_only_by_a_price_table_has_free_times(): void
    {
        $times = $this->times();

        $this->assertCount(24, $times);
        $this->assertSame([true], array_values(array_unique(array_column($times, 'available'))));
    }

    public function test_expired_hold_counts_as_free(): void
    {
        $code = $this->checkoutFor($this->fixture['payer'], $this->fixture['organization'], $this->fixture['court'], [$this->at('10:00')])
            ->assertCreated()->json('reservation.code');
        $this->assertFalse($this->times()['10:00']['available']);

        Reservation::where('code', $code)->update(['hold_expires_at' => now()->subMinute()]);

        $this->assertTrue($this->times()['10:00']['available']);
    }

    public function test_two_hour_booking_takes_both_of_its_hours_only(): void
    {
        $this->checkoutFor($this->fixture['payer'], $this->fixture['organization'], $this->fixture['court'], [$this->at('19:00'), $this->at('20:00')])->assertCreated();

        $times = $this->times();

        $this->assertSame([true, false, false, true], array_map(fn (string $time): bool => $times[$time]['available'], ['18:00', '19:00', '20:00', '21:00']));
    }

    public function test_court_opening_at_half_past_offers_half_past_slots_priced_per_minute(): void
    {
        $this->publishPriceVersion($this->fixture['table'], [['06:30', '18:00', 100000], ['18:00', '22:00', 160000]], now());
        $this->openDaily($this->fixture['venue'], '06:30', '22:00');

        $times = $this->times();

        $this->assertSame(['06:30', '20:30'], [array_key_first($times), array_key_last($times)]);
        $this->assertSame(['price' => 130000, 'available' => true], $times['17:30']);
    }

    /** @return array<string, array{price: int, available: bool}> by local start H:i on 21/10/2026 */
    private function times(): array
    {
        $times = $this->getJson("/api/availability?court_id={$this->fixture['court']->id}&date=2026-10-21")->assertOk()->json();

        return collect($times)->mapWithKeys(fn (array $time): array => [
            CarbonImmutable::parse($time['startsAt'])->setTimezone('Asia/Ho_Chi_Minh')->format('H:i') => ['price' => $time['price'], 'available' => $time['available']],
        ])->all();
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse("2026-10-21 {$time}", 'Asia/Ho_Chi_Minh');
    }

    private function openDaily(Venue $venue, string $opens, string $closes): void
    {
        VenueOperatingSchedule::create([
            'venue_id' => $venue->id, 'scope_key' => 'venue', 'timezone' => 'Asia/Ho_Chi_Minh', 'version' => 1,
            'weekly_hours' => array_fill_keys(range(1, 7), ['closed' => false, 'intervals' => [['start' => $opens, 'end' => $closes]]]),
        ]);
    }
}
