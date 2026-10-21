<?php

namespace App\Services\Pricing;

use App\Contracts\Repositories\PriceTableRepositoryInterface;
use App\Exceptions\PriceCoverageIncompleteException;
use App\Models\Court;
use App\Models\PriceTable;
use App\Models\PriceTableInterval;
use App\Models\PriceTableVersion;
use App\Models\VenueOperatingSchedule;
use App\Models\VenueScheduleException;
use App\Services\Reservation\VenueOperatingCalendar;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Price coverage (design D3): from a time on, every minute a court can be booked (its schedule and exceptions,
 * as VenueOperatingCalendar reads them) has a price for a booking confirmed now. Each segment in which links and
 * versions stay the same is walked once per weekday, plus each date a holiday version or a schedule exception
 * names, never day by day into the future.
 *
 * A missing item is {court_id, court_name, weekday (ISO 1-7) or date (Y-m-d), start, end} with H:i times; an
 * end of day reads 23:59 (D15).
 */
class PriceCoverageService
{
    public function __construct(
        private readonly PriceTableRepositoryInterface $priceTables,
        private readonly VenueOperatingCalendar $calendar,
    ) {}

    /** @return list<array{court_id: int, court_name: string, weekday: ?int, date: ?string, start: string, end: string}> */
    public function missingItems(Court $court, CarbonInterface $from): array
    {
        return $this->missingByCourt([(int) $court->id], $from)[(int) $court->id] ?? [];
    }

    /**
     * The ids of the courts with a gap, in a fixed number of queries for any number of courts.
     *
     * @param  list<int>  $courtIds
     * @return list<int>
     */
    public function uncoveredCourtIds(array $courtIds, CarbonInterface $from): array
    {
        return array_keys(array_filter($this->missingByCourt($courtIds, $from)));
    }

    /**
     * 422 PRICE_COVERAGE_INCOMPLETE listing every gap of the courts. Price publications and link changes call it
     * inside their transaction, after writing, so a failure rolls the change back.
     *
     * ponytail: every court counts as active or paused until module 2 adds courts.status (is_active false is
     * backfilled to paused); callers then leave inactive courts out.
     *
     * @param  list<int>  $courtIds
     */
    public function assertCovered(array $courtIds, CarbonInterface $from): void
    {
        $missing = array_merge(...array_values($this->missingByCourt($courtIds, $from)));
        if ($missing !== []) {
            throw new PriceCoverageIncompleteException($missing);
        }
    }

    /**
     * @param  list<int>  $courtIds
     * @return array<int, list<array{court_id: int, court_name: string, weekday: ?int, date: ?string, start: string, end: string}>>
     */
    private function missingByCourt(array $courtIds, CarbonInterface $from): array
    {
        $courts = Court::query()->with('venue')->whereIn('id', $courtIds)->orderBy('id')->get();
        if ($courts->isEmpty()) {
            return [];
        }
        $from = CarbonImmutable::instance($from)->ceilMinute();
        $links = $this->priceTables->linksForCourts($courts->modelKeys(), $from)->groupBy('court_id');
        $firstDate = $courts->map(fn (Court $court): string => $from->setTimezone($this->timezone($court))->toDateString())->min();
        $calendars = $this->calendar->calendarsFor($courts, $firstDate);

        return $courts->mapWithKeys(fn (Court $court): array => [(int) $court->id => $this->courtItems(
            $court,
            new CourtPriceTimeline($links->get($court->id, collect()), now()),
            $calendars[(int) $court->id],
            $from,
        )])->all();
    }

    /**
     * @param  array{schedule: ?VenueOperatingSchedule, exceptions: array<string, VenueScheduleException>}  $calendar
     * @return list<array{court_id: int, court_name: string, weekday: ?int, date: ?string, start: string, end: string}>
     */
    private function courtItems(Court $court, CourtPriceTimeline $timeline, array $calendar, CarbonImmutable $from): array
    {
        $timezone = $this->timezone($court);
        $bounds = [$from, ...$timeline->changesBetween($from, null)];
        $gaps = [];
        foreach ($bounds as $index => $segmentStart) {
            $gaps = [...$gaps, ...$this->segmentGaps(
                $calendar,
                $timeline->versionAt(PriceTable::KIND_NORMAL, $segmentStart),
                $timeline->versionAt(PriceTable::KIND_HOLIDAY, $segmentStart),
                $segmentStart->setTimezone($timezone),
                isset($bounds[$index + 1]) ? $bounds[$index + 1]->setTimezone($timezone) : null,
            )];
        }

        return collect($gaps)
            ->unique(fn (array $gap): string => implode('|', $gap))
            ->sortBy(fn (array $gap): string => sprintf('%s|%d|%04d', $gap['date'] ?? '', $gap['weekday'] ?? 0, $gap['start']))
            ->map(fn (array $gap): array => [
                'court_id' => (int) $court->id,
                'court_name' => (string) $court->name,
                'weekday' => $gap['weekday'],
                'date' => $gap['date'],
                'start' => self::clock($gap['start']),
                'end' => self::clock($gap['end']),
            ])
            ->values()
            ->all();
    }

    /**
     * Gaps of a segment [$start, $end) whose versions stay the same: each date that a schedule exception or the
     * holiday version names on its own, every other day once per weekday.
     *
     * @param  array{schedule: ?VenueOperatingSchedule, exceptions: array<string, VenueScheduleException>}  $calendar
     * @return list<array{weekday: ?int, date: ?string, start: int, end: int}>
     */
    private function segmentGaps(array $calendar, ?PriceTableVersion $normal, ?PriceTableVersion $holiday, CarbonImmutable $start, ?CarbonImmutable $end): array
    {
        $firstDate = $start->toDateString();
        $lastDate = $end?->toDateString();
        $firstMinute = $start->hour * 60 + $start->minute;
        $lastMinute = $end ? $end->hour * 60 + $end->minute : 1440;
        // The segment's part of a local date, empty on the date it ends at midnight.
        $window = fn (string $date): array => [$date === $firstDate ? $firstMinute : 0, $date === $lastDate ? $lastMinute : 1440];

        $special = collect(array_keys($calendar['exceptions']))
            ->concat($holiday ? $holiday->intervals->flatMap(fn (PriceTableInterval $interval): array => $interval->dates ?? []) : [])
            ->unique()
            ->filter(fn (string $date): bool => $date >= $firstDate && ($lastDate === null || $date <= $lastDate))
            ->values()
            ->all();

        $gaps = [];
        foreach ($special as $date) {
            $weekday = CarbonImmutable::parse($date)->dayOfWeekIso;
            $open = VenueOperatingCalendar::openMinutes($calendar['schedule'], $calendar['exceptions'][$date] ?? null, $weekday);
            foreach (self::uncovered(self::clip($open, ...$window($date)), CourtPriceTimeline::pricing($normal, $holiday, $date, $weekday)[1]) as [$from, $to]) {
                $gaps[] = ['weekday' => null, 'date' => $date, 'start' => $from, 'end' => $to];
            }
        }

        // Other days repeat by weekday, so one whole day of each weekday stands for the rest of the segment.
        $windows = [];
        $whole = [];
        for ($day = $start->startOfDay(); count($whole) < 7 && ($lastDate === null || $day->toDateString() <= $lastDate); $day = $day->addDay()) {
            [$from, $to] = $window($day->toDateString());
            if ($from >= $to || in_array($day->toDateString(), $special, true)) {
                continue;
            }
            if ($from === 0 && $to === 1440) {
                $whole[$day->dayOfWeekIso] = true;
            }
            $windows[$day->dayOfWeekIso][] = [$from, $to];
        }
        foreach ($windows as $weekday => $ranges) {
            $open = VenueOperatingCalendar::openMinutes($calendar['schedule'], null, $weekday);
            $intervals = CourtPriceTimeline::pricing($normal, null, '', $weekday)[1];
            foreach (isset($whole[$weekday]) ? [[0, 1440]] : $ranges as [$from, $to]) {
                foreach (self::uncovered(self::clip($open, $from, $to), $intervals) as [$gapFrom, $gapTo]) {
                    $gaps[] = ['weekday' => $weekday, 'date' => null, 'start' => $gapFrom, 'end' => $gapTo];
                }
            }
        }

        return $gaps;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $ranges
     * @return list<array{0: int, 1: int}>
     */
    private static function clip(array $ranges, int $from, int $to): array
    {
        return array_values(array_filter(
            array_map(fn (array $range): array => [max($range[0], $from), min($range[1], $to)], $ranges),
            fn (array $range): bool => $range[0] < $range[1],
        ));
    }

    /**
     * The parts of the open ranges that no interval prices.
     *
     * @param  list<array{0: int, 1: int}>  $open
     * @param  list<PriceTableInterval>  $intervals  by start
     * @return list<array{0: int, 1: int}>
     */
    private static function uncovered(array $open, array $intervals): array
    {
        $gaps = [];
        foreach ($open as [$cursor, $end]) {
            foreach ($intervals as $interval) {
                if ($interval->end_minute <= $cursor) {
                    continue;
                }
                if ($interval->start_minute >= $end) {
                    break;
                }
                if ($interval->start_minute > $cursor) {
                    $gaps[] = [$cursor, $interval->start_minute];
                }
                $cursor = $interval->end_minute;
            }
            if ($cursor < $end) {
                $gaps[] = [$cursor, $end];
            }
        }

        return $gaps;
    }

    private static function clock(int $minute): string
    {
        return $minute === 1440 ? '23:59' : sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }

    private function timezone(Court $court): string
    {
        // ponytail: schedules carry their own timezone; every venue and schedule today is Asia/Ho_Chi_Minh.
        return $court->venue?->timezone ?: config('app.timezone');
    }
}
