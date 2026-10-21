<?php

namespace Tests\Feature\Pricing;

use App\Models\Court;
use App\Models\CourtPriceTableLink;
use App\Models\Organization;
use App\Models\PriceTable;
use App\Models\Venue;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\PricesCourts;
use Tests\TestCase;

/** Store-level rules of price tables and links (design D1, D2). */
class PriceTableSchemaTest extends TestCase
{
    use PricesCourts;
    use RefreshDatabase;

    public function test_store_rejects_two_overlapping_normal_links_on_one_court(): void
    {
        $court = $this->court();
        $this->priceCourt($court);
        $other = $this->createPriceTable($court, PriceTable::KIND_NORMAL, [['00:00', '23:59', 150000]]);

        $this->assertLinkRejected($other, $court, now()->addDays(3), null, 'court_price_table_links_no_overlap');

        $this->assertSame(1, CourtPriceTableLink::where('court_id', $court->id)->count());
    }

    public function test_adjacent_links_and_a_holiday_link_beside_a_normal_one_are_accepted(): void
    {
        $court = $this->court();
        $first = $this->createPriceTable($court, PriceTable::KIND_NORMAL, [['00:00', '23:59', 100000]]);
        $second = $this->createPriceTable($court, PriceTable::KIND_NORMAL, [['00:00', '23:59', 150000]]);
        $this->linkPriceTable($first, $court, now()->subDay(), now()->addDays(3));
        $this->linkPriceTable($second, $court, now()->addDays(3));
        $this->priceCourtHolidays($court, ['2027-01-01'], [['00:00', '23:59', 180000]]);

        $this->assertSame(3, CourtPriceTableLink::where('court_id', $court->id)->count());
    }

    public function test_store_rejects_a_link_whose_kind_differs_from_its_table(): void
    {
        $court = $this->court();
        $holiday = $this->createPriceTable($court, PriceTable::KIND_HOLIDAY, [['00:00', '23:59', 180000, ['2027-01-01']]]);

        try {
            DB::transaction(fn () => CourtPriceTableLink::create([
                'court_id' => $court->id, 'price_table_id' => $holiday->id, 'kind' => PriceTable::KIND_NORMAL, 'starts_at' => now(),
            ]));
            $this->fail('Expected the (price_table_id, kind) key to reject the link.');
        } catch (QueryException $exception) {
            $this->assertSame('23503', $exception->getCode());
        }
    }

    private function assertLinkRejected(PriceTable $table, Court $court, CarbonInterface $startsAt, ?CarbonInterface $endsAt, string $constraint): void
    {
        try {
            // Savepoint, so the failed insert does not abort the test transaction.
            DB::transaction(fn () => $this->linkPriceTable($table, $court, $startsAt, $endsAt));
        } catch (QueryException $exception) {
            $this->assertSame('23P01', $exception->getCode());
            $this->assertStringContainsString($constraint, $exception->getMessage());

            return;
        }

        $this->fail("Expected {$constraint} to reject the link.");
    }

    private function court(): Court
    {
        $organization = Organization::create(['name' => 'Schema Club', 'slug' => 'schema-'.Str::lower(Str::random(8)), 'status' => 'active']);
        $venue = Venue::create(['organization_id' => $organization->id, 'name' => 'Schema Venue', 'slug' => 'schema-venue-'.Str::lower(Str::random(8)), 'is_active' => true]);

        return Court::create(['venue_id' => $venue->id, 'name' => 'Schema Court', 'is_active' => true]);
    }
}
