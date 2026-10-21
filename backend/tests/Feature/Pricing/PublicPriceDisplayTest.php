<?php

namespace Tests\Feature\Pricing;

use App\Contracts\Repositories\PriceTableRepositoryInterface;
use App\Models\Court;
use App\Models\Organization;
use App\Models\PriceTable;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\PricesCourts;
use Tests\TestCase;

/** "Giá từ" on the public court list and detail (design D18): the lowest price a booking confirmed now could pay. */
class PublicPriceDisplayTest extends TestCase
{
    use PricesCourts;
    use RefreshDatabase;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 08:00', 'Asia/Ho_Chi_Minh'));
        $org = Organization::create(['name' => 'Price Org', 'slug' => 'price-org-'.uniqid(), 'status' => 'active', 'partner_status' => 'approved']);
        $venue = Venue::create(['organization_id' => $org->id, 'name' => 'Price Venue', 'slug' => 'price-venue-'.uniqid(), 'status' => 'published', 'is_active' => true]);
        $this->court = Court::create(['venue_id' => $venue->id, 'sport_type' => 'badminton', 'name' => 'Price Court', 'slug' => 'price-court-'.uniqid(), 'is_active' => true]);
    }

    public function test_list_and_detail_show_the_lowest_price_including_holidays_still_ahead(): void
    {
        $this->priceCourtWith($this->court, [['06:00', '17:00', 100000], ['17:00', '22:00', 150000]]);
        $this->priceCourtHolidays($this->court, ['2026-10-18'], [['06:00', '22:00', 50000], ['06:00', '22:00', 90000, ['2027-01-01']]]);

        $this->assertSame([90000, 90000], $this->fromPrices());
    }

    public function test_court_whose_table_has_no_published_price_shows_none(): void
    {
        $this->linkPriceTable(PriceTable::create(['venue_id' => $this->court->venue_id, 'name' => 'Chưa công bố', 'kind' => PriceTable::KIND_NORMAL, 'status' => 'active']), $this->court);

        $this->assertSame([null, null], $this->fromPrices());
    }

    public function test_cheaper_version_published_now_shows_on_the_cached_list_after_the_courts_version_bump(): void
    {
        $table = $this->priceCourt($this->court, 100000);
        $this->getJson('/api/courts')->assertOk()->assertJsonPath('0.from_price', 100000);

        app(PriceTableRepositoryInterface::class)->publishVersion($table, now(), now(), [
            ['weekdays' => [1, 2, 3, 4, 5, 6, 7], 'dates' => null, 'start_minute' => 0, 'end_minute' => 1440, 'price_per_hour' => 80000],
        ]);
        Cache::increment('courts_version');

        $this->getJson('/api/courts')->assertOk()->assertJsonPath('0.from_price', 80000);
    }

    public function test_superseded_version_no_longer_counts_and_a_scheduled_one_does(): void
    {
        $table = $this->priceCourt($this->court, 100000);
        $this->publishPriceVersion($table, [['00:00', '23:59', 120000]], now());
        $this->assertSame([120000, 120000], $this->fromPrices());

        $this->publishPriceVersion($table, [['00:00', '23:59', 70000]], now()->addDay());

        $this->assertSame([70000, 70000], $this->fromPrices());
    }

    /** @return array{0: ?int, 1: ?int} from_price on the uncached list, then on the detail */
    private function fromPrices(): array
    {
        Cache::flush();

        return [
            $this->getJson('/api/courts')->assertOk()->json('0.from_price'),
            $this->getJson("/api/courts/{$this->court->slug}")->assertOk()->json('from_price'),
        ];
    }
}
