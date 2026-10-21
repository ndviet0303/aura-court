<?php

namespace Tests\Feature\Venue;

use App\Domain\Pricing\Actions\ChangeCourtPriceTableLinks;
use App\Domain\Pricing\DTOs\PriceTableLinkChange;
use App\Models\Court;
use App\Models\Organization;
use App\Models\PriceTable;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PricesCourts;
use Tests\TestCase;

class PricedCourtVisibilityTest extends TestCase
{
    use PricesCourts;
    use RefreshDatabase;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organization::create(['name' => 'Priced Org', 'slug' => 'priced-org-'.uniqid(), 'status' => 'active', 'partner_status' => 'approved']);
        $this->venue = Venue::create(['organization_id' => $org->id, 'name' => 'Priced Venue', 'slug' => 'priced-venue-'.uniqid(), 'status' => 'published', 'is_active' => true]);
    }

    private function court(string $name): Court
    {
        return Court::create(['venue_id' => $this->venue->id, 'sport_type' => 'badminton', 'name' => $name, 'slug' => 'court-'.uniqid(), 'is_active' => true]);
    }

    public function test_court_without_a_normal_link_in_effect_is_hidden_and_not_bookable(): void
    {
        $unpriced = $this->court('Unpriced Court');
        $holidayOnly = $this->court('Holiday Only Court');
        $ended = $this->court('Ended Link Court');
        $priced = $this->court('Priced Court');
        $this->priceCourt($priced);
        $this->priceCourtHolidays($holidayOnly, [now()->addDay()->toDateString()], [['00:00', '23:59', 150000]]);
        $this->linkPriceTable($this->createPriceTable($ended, PriceTable::KIND_NORMAL, [['00:00', '23:59', 100000]]), $ended, now()->subDay(), now()->subMinute());

        $ids = collect($this->getJson('/api/courts')->assertOk()->json())->pluck('id')->all();
        $this->assertSame([$priced->id], $ids);

        $this->getJson("/api/courts/{$unpriced->slug}")->assertNotFound();
        $this->getJson("/api/availability?court_id={$unpriced->id}&date=".now()->addDay()->toDateString())->assertNotFound();
        $this->actingAs(User::factory()->create())
            ->withHeader('Idempotency-Key', uniqid('unpriced-'))
            ->postJson('/api/reservations', ['court_id' => $unpriced->id, 'slots' => [now()->addDay()->setTime(10, 0)->toIso8601String()], 'expected_total' => 100000])
            ->assertNotFound();
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_public_court_and_venue_reads_do_not_expose_the_organization_row(): void
    {
        $court = $this->court('Public Court');
        $this->priceCourt($court);
        $this->venue->organization->update(['tax_code' => '0312345678']);

        $this->getJson('/api/courts')->assertOk()
            ->assertJsonPath('0.venue.organization_id', $this->venue->organization_id)
            ->assertJsonMissingPath('0.venue.organization')
            ->assertDontSee('0312345678');
        $this->getJson("/api/courts/{$court->slug}")->assertOk()
            ->assertJsonPath('venue.id', $this->venue->id)
            ->assertJsonMissingPath('venue.organization')
            ->assertDontSee('0312345678');
        $this->getJson("/api/venues/{$this->venue->slug}")->assertOk()
            ->assertJsonPath('id', $this->venue->id)
            ->assertJsonMissingPath('organization')
            ->assertDontSee('0312345678');
    }

    public function test_new_price_table_link_refreshes_the_cached_public_list(): void
    {
        $court = $this->court('Late Priced Court');
        $table = $this->createPriceTable($court, PriceTable::KIND_NORMAL, [['00:00', '23:59', 100000]]);
        $this->getJson('/api/courts')->assertOk()->assertJsonCount(0);

        app(ChangeCourtPriceTableLinks::class)->execute(PriceTableLinkChange::apply($table, [$court->id]));

        $this->getJson('/api/courts')->assertOk()->assertJsonPath('0.id', $court->id);
    }

    public function test_public_list_query_count_does_not_grow_with_courts(): void
    {
        // Returns [query count, listed courts] for an uncached request.
        $measure = function (): array {
            Cache::flush();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $listed = count($this->getJson('/api/courts')->assertOk()->json());
            DB::disableQueryLog();

            return [count(DB::getQueryLog()), $listed];
        };

        $table = $this->priceCourt([$this->court('Court 1'), $this->court('Court 2')]);
        [$fewQueries, $fewListed] = $measure();
        foreach (range(3, 10) as $number) {
            $this->linkPriceTable($table, $this->court("Court {$number}"));
        }
        [$manyQueries, $manyListed] = $measure();

        $this->assertSame([2, 10], [$fewListed, $manyListed]);
        $this->assertSame($fewQueries, $manyQueries);
    }
}
