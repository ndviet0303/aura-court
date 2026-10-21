<?php

namespace Tests\Feature\Pricing;

use App\Exceptions\PriceCoverageIncompleteException;
use App\Models\Court;
use App\Models\Organization;
use App\Models\Venue;
use App\Models\VenueOperatingSchedule;
use App\Models\VenueScheduleException;
use App\Services\Pricing\PriceCoverageService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\PricesCourts;
use Tests\Concerns\PricingRoles;
use Tests\TestCase;

/** Price coverage of the bookable time of courts (design D3) and its owner-only endpoint (D25). */
class PriceCoverageTest extends TestCase
{
    use PricesCourts;
    use PricingRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 08:00', 'Asia/Ho_Chi_Minh'));
    }

    public function test_hours_open_but_unpriced_are_listed_once_per_weekday(): void
    {
        $venue = $this->venue('06:00', '23:59');
        $court = $this->court($venue);
        $this->priceCourtWith($court, [['06:00', '22:00', 100000]]);

        $items = $this->coverage()->missingItems($court, now());

        $this->assertSame(array_map(fn (int $weekday): array => [
            'court_id' => $court->id, 'court_name' => 'Sân 1', 'weekday' => $weekday, 'date' => null, 'start' => '22:00', 'end' => '23:59',
        ], range(1, 7)), $items);
    }

    public function test_court_priced_over_its_opening_hours_is_covered(): void
    {
        $court = $this->court($this->venue('06:00', '22:00'));
        $this->priceCourtWith($court, [['06:00', '16:00', 100000], ['16:00', '22:00', 140000]]);

        $this->assertSame([], $this->coverage()->missingItems($court, now()));
        $this->coverage()->assertCovered([$court->id], now());
    }

    public function test_holiday_hours_left_out_are_listed_by_date(): void
    {
        $court = $this->court($this->venue('06:00', '22:00'));
        $this->priceCourtWith($court, [['06:00', '22:00', 100000]]);
        $this->priceCourtHolidays($court, ['2027-01-01'], [['06:00', '20:00', 180000]]);

        $items = $this->coverage()->missingItems($court, now());

        // Normal prices do not fill the holiday's missing hours (D4).
        $this->assertSame([['weekday' => null, 'date' => '2027-01-01', 'start' => '20:00', 'end' => '22:00']], $this->slots($items));
    }

    public function test_exception_hours_beyond_the_prices_are_listed_by_date(): void
    {
        $venue = $this->venue('06:00', '22:00');
        $court = $this->court($venue);
        $this->priceCourtWith($court, [['06:00', '22:00', 100000]]);
        VenueScheduleException::create([
            'venue_id' => $venue->id, 'scope_key' => 'venue', 'exception_date' => '2026-11-20', 'name' => 'Giải đấu',
            'type' => 'special_hours', 'intervals' => [['start' => '05:00', 'end' => '23:00']], 'timezone' => 'Asia/Ho_Chi_Minh',
        ]);

        $this->assertSame([
            ['weekday' => null, 'date' => '2026-11-20', 'start' => '05:00', 'end' => '06:00'],
            ['weekday' => null, 'date' => '2026-11-20', 'start' => '22:00', 'end' => '23:00'],
        ], $this->slots($this->coverage()->missingItems($court, now())));
    }

    public function test_version_taking_effect_later_is_checked_from_its_effective_time(): void
    {
        $court = $this->court($this->venue('06:00', '23:59'));
        $table = $this->priceCourt($court, 100000);
        $this->publishPriceVersion($table, [['06:00', '22:00', 120000]], now()->addDays(3));

        $this->assertSame(
            array_map(fn (int $weekday): array => ['weekday' => $weekday, 'date' => null, 'start' => '22:00', 'end' => '23:59'], range(1, 7)),
            $this->slots($this->coverage()->missingItems($court, now())),
        );
    }

    public function test_batch_lists_exactly_the_gapped_courts_in_a_fixed_number_of_queries(): void
    {
        $scheduled = $this->venue('06:00', '22:00');
        $allDay = $this->venue();
        $courts = [$this->court($scheduled), $this->court($scheduled), $this->court($allDay), $this->court($allDay), $this->court($allDay)];
        // The second venue has no schedule, so it opens all day and 06:00-22:00 leaves $courts[2] short.
        $this->priceCourtWith($courts[0], [['06:00', '22:00', 100000]]);
        $this->priceCourtWith($courts[1], [['06:00', '21:00', 100000]]);
        $this->priceCourtWith($courts[2], [['06:00', '22:00', 100000]]);
        $this->priceCourt([$courts[3], $courts[4]], 100000);
        $ids = array_map(fn (Court $court): int => $court->id, $courts);

        $this->assertSame([$courts[1]->id, $courts[2]->id], $this->coverage()->uncoveredCourtIds($ids, now()));
        foreach ($courts as $court) {
            $this->assertSame(
                in_array($court->id, [$courts[1]->id, $courts[2]->id], true),
                $this->coverage()->missingItems($court, now()) !== [],
            );
        }
        $this->assertSame($this->queriesFor([$courts[1]->id]), $this->queriesFor($ids));
    }

    public function test_assert_covered_reports_every_gap_as_price_coverage_incomplete(): void
    {
        $venue = $this->venue('06:00', '22:00');
        [$covered, $gapped] = [$this->court($venue), $this->court($venue)];
        $this->priceCourtWith($covered, [['06:00', '22:00', 100000]]);
        $this->priceCourtWith($gapped, [['06:00', '21:00', 100000]]);

        try {
            $this->coverage()->assertCovered([$covered->id, $gapped->id], now());
            $this->fail('Expected PRICE_COVERAGE_INCOMPLETE.');
        } catch (PriceCoverageIncompleteException $exception) {
            $response = $exception->render(request());
            $this->assertSame(422, $response->getStatusCode());
            $this->assertSame('PRICE_COVERAGE_INCOMPLETE', $response->getData(true)['code']);
            $this->assertSame([$gapped->id], array_values(array_unique(array_column($response->getData(true)['missing_items'], 'court_id'))));
            $this->assertCount(7, $response->getData(true)['missing_items']);
        }
    }

    public function test_owner_reads_the_price_coverage_of_a_court(): void
    {
        $venue = $this->pricingVenue();
        [$gapped, $covered] = [$this->court($venue), $this->court($venue)];
        $this->priceCourtWith($gapped, [['06:00', '22:00', 100000]]);
        $this->priceCourt($covered, 100000);
        $owner = $this->ownerOf($venue->organization);

        $response = $this->actingAs($owner)->getJson("/api/admin/courts/{$gapped->id}/price-coverage")
            ->assertOk()
            ->assertJsonPath('data.court_id', $gapped->id)
            ->assertJsonPath('data.covered', false)
            ->assertJsonPath('data.missing_items.0', ['court_id' => $gapped->id, 'court_name' => 'Sân 1', 'weekday' => 1, 'date' => null, 'start' => '00:00', 'end' => '06:00']);
        // The venue has no schedule, so it opens all day: both ends of each weekday are unpriced.
        $this->assertCount(14, $response->json('data.missing_items'));

        $this->actingAs($owner)->getJson("/api/admin/courts/{$covered->id}/price-coverage")
            ->assertOk()
            ->assertExactJson(['data' => ['court_id' => $covered->id, 'covered' => true, 'missing_items' => []]]);
    }

    public function test_staff_and_another_organizations_owner_cannot_read_price_coverage(): void
    {
        $venue = $this->pricingVenue();
        $court = $this->court($venue);
        $this->priceCourt($court, 100000);

        foreach ([$this->staffOf($venue), $this->ownerOfAnotherOrganization()] as $user) {
            $this->actingAs($user)->getJson("/api/admin/courts/{$court->id}/price-coverage")->assertForbidden();
        }
    }

    private function coverage(): PriceCoverageService
    {
        return app(PriceCoverageService::class);
    }

    /** @param  list<int>  $courtIds */
    private function queriesFor(array $courtIds): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->coverage()->uncoveredCourtIds($courtIds, now());
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function slots(array $items): array
    {
        return array_map(fn (array $item): array => array_diff_key($item, ['court_id' => 0, 'court_name' => 0]), $items);
    }

    /** A venue open every day over [$opens, $closes), or with no schedule (open all day) when left out. */
    private function venue(?string $opens = null, ?string $closes = null): Venue
    {
        $organization = Organization::create(['name' => 'Coverage Club', 'slug' => 'coverage-'.Str::lower(Str::random(8)), 'status' => 'active']);
        $venue = Venue::create(['organization_id' => $organization->id, 'name' => 'Coverage Venue', 'slug' => 'coverage-venue-'.Str::lower(Str::random(8)), 'is_active' => true]);
        if ($opens !== null) {
            VenueOperatingSchedule::create([
                'venue_id' => $venue->id,
                'scope_key' => 'venue',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'weekly_hours' => array_fill_keys(range(1, 7), ['closed' => false, 'intervals' => [['start' => $opens, 'end' => $closes]]]),
            ]);
        }

        return $venue;
    }

    private function court(Venue $venue): Court
    {
        $number = Court::where('venue_id', $venue->id)->count() + 1;

        return Court::create(['venue_id' => $venue->id, 'name' => "Sân {$number}", 'is_active' => true]);
    }
}
