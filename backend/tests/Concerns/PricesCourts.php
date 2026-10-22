<?php

namespace Tests\Concerns;

use App\Models\Court;
use App\Models\CourtPriceTableLink;
use App\Models\PriceTable;
use App\Models\PriceTableVersion;
use App\Services\Reservation\VenueOperatingCalendar;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Price tables for tests. An interval is [start H:i, end H:i, price per hour, days?]: days are ISO weekdays on a
 * normal table (every day when left out) or Y-m-d dates on a holiday table (the table's dates when left out), and
 * 23:59 as an end means the end of the day. Tables are published and linked from a day ago, so they price any
 * booking a test makes.
 *
 *     $this->priceCourt($court, 120000);                 // 120.000 ₫/h all day, every day
 *     $this->priceCourtWith([$court, $other], [['10:00', '16:00', 100000], ['16:00', '22:00', 140000, [1, 2, 3, 4, 5]]]);
 *     $this->priceCourtHolidays($court, ['2027-01-01'], [['06:00', '23:59', 180000]]);
 */
trait PricesCourts
{
    /** @param  Court|list<Court>  $courts */
    protected function priceCourt(Court|array $courts, int $pricePerHour = 100000): PriceTable
    {
        return $this->priceCourtWith($courts, [['00:00', '23:59', $pricePerHour]]);
    }

    /**
     * @param  Court|list<Court>  $courts
     * @param  list<array{0: string, 1: string, 2: int, 3?: list<int>}>  $intervals
     */
    protected function priceCourtWith(Court|array $courts, array $intervals): PriceTable
    {
        $table = $this->createPriceTable($courts, PriceTable::KIND_NORMAL, $intervals);
        $this->linkPriceTable($table, $courts);

        return $table;
    }

    /**
     * @param  Court|list<Court>  $courts
     * @param  list<string>  $dates
     * @param  list<array{0: string, 1: string, 2: int, 3?: list<string>}>  $intervals
     */
    protected function priceCourtHolidays(Court|array $courts, array $dates, array $intervals): PriceTable
    {
        $table = $this->createPriceTable($courts, PriceTable::KIND_HOLIDAY, array_map(fn (array $interval): array => $interval + [3 => $dates], $intervals));
        $this->linkPriceTable($table, $courts);

        return $table;
    }

    /**
     * A table of the courts' venue with one version published a day ago; not linked.
     *
     * @param  Court|list<Court>  $courts
     * @param  list<array{0: string, 1: string, 2: int, 3?: list<int|string>}>  $intervals
     */
    protected function createPriceTable(Court|array $courts, string $kind, array $intervals): PriceTable
    {
        $court = is_array($courts) ? $courts[0] : $courts;
        $table = PriceTable::create(['venue_id' => $court->venue_id, 'name' => "Bảng giá {$kind}", 'kind' => $kind, 'status' => 'active']);
        $this->publishPriceVersion($table, $intervals, now()->subDay(), now()->subDay());

        return $table;
    }

    /**
     * Another version of the table, published now unless $publishedAt is given. As on publication, an effective
     * time before the publication becomes the publication time.
     *
     * @param  list<array{0: string, 1: string, 2: int, 3?: list<int|string>}>  $intervals
     */
    protected function publishPriceVersion(PriceTable $table, array $intervals, CarbonInterface $effectiveAt, ?CarbonInterface $publishedAt = null): PriceTableVersion
    {
        $publishedAt ??= now();
        $version = $table->versions()->create(['published_at' => $publishedAt, 'effective_at' => $effectiveAt->max($publishedAt)]);
        foreach ($intervals as $interval) {
            $version->intervals()->create([
                $table->kind === PriceTable::KIND_NORMAL ? 'weekdays' : 'dates' => $interval[3] ?? ($table->kind === PriceTable::KIND_NORMAL ? [1, 2, 3, 4, 5, 6, 7] : []),
                'start_minute' => VenueOperatingCalendar::minuteOfDay($interval[0]),
                'end_minute' => VenueOperatingCalendar::minuteOfDay($interval[1], true),
                'price_per_hour' => $interval[2],
            ]);
        }

        return $version;
    }

    /**
     * Links the courts to the table over [$startsAt, $endsAt): from a day ago and open-ended by default.
     *
     * @param  Court|list<Court>  $courts
     */
    protected function linkPriceTable(PriceTable $table, Court|array $courts, ?CarbonInterface $startsAt = null, ?CarbonInterface $endsAt = null): void
    {
        foreach (is_array($courts) ? $courts : [$courts] as $court) {
            CourtPriceTableLink::create([
                'court_id' => $court->id,
                'price_table_id' => $table->id,
                'kind' => $table->kind,
                'starts_at' => $startsAt ?? now()->subDay(),
                'ends_at' => $endsAt,
            ]);
        }
        // The public court list is cached by courts_version; the link action bumps it the same way.
        Cache::increment('courts_version');
    }
}
