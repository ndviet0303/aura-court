<?php

use App\Exceptions\PriceCoverageIncompleteException;
use App\Models\Court;
use App\Services\Pricing\PriceCoverageService;
use App\Services\Pricing\RentalPriceCalculator;
use App\Services\Reservation\VenueOperatingCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * align-module-03-pricing-promotions, Migration Plan step 1: the prices of price_rules and court_price_slots move to
 * price tables linked from now on. Their models are gone when this runs, so the old resolver is replayed here on
 * query builder rows: tier date_exception > holiday > peak > weekly > base, then scope court > venue + sport > venue
 * > organization, priority, version, id; a legacy slot sits in the weekly tier between court and venue rules and
 * counts only at its court's slot length. Each minute takes the price that ranking gives it.
 *
 * Per venue, courts with the same weekday prices share a normal table. Each date a holiday or date_exception rule
 * names for a court is written for the whole day, its other hours at the old peak/weekly/base price, since a
 * holiday table never falls back to the normal one (design D4); courts with the same dates share a holiday table.
 *
 * In the same transaction, every half-hour slot the old model priced over the next 14 days, every converted holiday
 * date and the week after each future effective date of a rule must cost the same through RentalPriceCalculator,
 * and the new links must cover the courts' opening hours (D3). Any difference or gap aborts with the list.
 */
return new class extends Migration
{
    /** Old tiers, highest first. */
    private const TYPES = ['date_exception', 'holiday', 'peak', 'weekly', 'base'];

    private const REPORTED = 200;

    public function up(): void
    {
        // Every read returns nothing while pretending, so --pretend SQL would convert nothing and raise nothing.
        if (DB::connection()->pretending()) {
            throw new RuntimeException('Price conversion reads data and cannot be pretended; run artisan migrate (in the container or on a restore), not SQL from --pretend.');
        }
        $now = CarbonImmutable::now()->startOfMinute();
        DB::transaction(fn () => $this->convert($now));
        // The public court list is cached by this key and shows only courts with a normal link in effect.
        DB::afterCommit(fn () => Cache::increment('courts_version'));
    }

    public function down(): void
    {
        // The old rows were only read; the converted tables go with the price tables migration.
    }

    private function convert(CarbonImmutable $now): void
    {
        $rules = DB::table('price_rules')->where('is_active', true)->orderBy('id')->get();
        $slots = DB::table('court_price_slots')->where('is_active', true)->orderBy('id')->get()->groupBy('court_id');
        // A court already on price tables keeps them.
        $courts = Court::query()->with('venue')
            ->whereNotIn('id', DB::table('court_price_table_links')->select('court_id'))
            ->orderBy('id')
            ->get();

        $plans = [];
        $groups = [];
        foreach ($courts as $court) {
            $plan = [
                'court' => $court,
                'rules' => $rules->filter(fn (object $rule): bool => $this->appliesTo($rule, $court))->values(),
                'slots' => $slots->get($court->id, collect()),
                'tz' => $court->venue?->timezone ?: config('app.timezone'),
                'linked' => false,
            ];
            if ($plan['rules']->isEmpty() && $plan['slots']->isEmpty()) {
                continue;
            }
            $today = $now->setTimezone($plan['tz'])->startOfDay();
            $plan['holidays'] = $plan['rules']
                ->filter(fn (object $rule): bool => in_array($rule->type, ['date_exception', 'holiday'], true)
                    && $rule->exception_date !== null
                    && $rule->exception_date >= $today->toDateString()
                    && $this->appliesOn($rule, CarbonImmutable::parse($rule->exception_date, $plan['tz'])))
                ->pluck('exception_date')->unique()->sort()->values()->all();

            $weekdays = [];
            foreach (range(1, 7) as $weekday) {
                // The weekday's next date stands for it; the comparison catches effective dates that change it later.
                $date = $today->addDays(($weekday - $today->dayOfWeekIso + 7) % 7);
                $weekdays[$weekday] = $this->perHour($this->candidates($plan, $date, false), (int) $court->slot_duration);
            }
            $dates = [];
            foreach ($plan['holidays'] as $date) {
                $dates[$date] = $this->perHour($this->candidates($plan, CarbonImmutable::parse($date, $plan['tz']), true), (int) $court->slot_duration);
            }
            foreach (['normal' => $this->intervals($weekdays, 'weekdays'), 'holiday' => $this->intervals($dates, 'dates')] as $kind => $intervals) {
                if ($intervals !== []) {
                    $groups[(int) $court->venue_id][$kind][json_encode($intervals)][] = (int) $court->id;
                    $plan['linked'] = true;
                }
            }
            $plans[] = $plan;
        }

        $this->write($groups, $now);

        $problems = array_merge(...array_map(fn (array $plan): array => $this->compare($plan, $now), $plans));
        $linked = array_values(array_map(fn (array $plan): int => (int) $plan['court']->id, array_filter($plans, fn (array $plan): bool => $plan['linked'])));
        try {
            if ($linked !== []) {
                app(PriceCoverageService::class)->assertCovered($linked, $now);
            }
        } catch (PriceCoverageIncompleteException $exception) {
            foreach ($exception->missingItems as $item) {
                $problems[] = sprintf('court %d (%s) %s %s-%s: open without a price', $item['court_id'], $item['court_name'],
                    $item['date'] ?? 'weekday '.$item['weekday'], $item['start'], $item['end']);
            }
        }

        if ($problems !== []) {
            throw new RuntimeException('Price conversion aborted, nothing was changed. '.count($problems)." problem(s):\n"
                .implode("\n", array_slice($problems, 0, self::REPORTED)).(count($problems) > self::REPORTED ? "\n..." : ''));
        }
    }

    /** @param  array<int, array<string, array<string, list<int>>>>  $groups  venue id => kind => intervals JSON => court ids */
    private function write(array $groups, CarbonImmutable $now): void
    {
        $at = $now->utc()->format('Y-m-d H:i:s');
        $stamp = $now->format('Y-m-d H:i:s');
        foreach ($groups as $venueId => $kinds) {
            foreach ($kinds as $kind => $contents) {
                $number = 0;
                foreach ($contents as $content => $courtIds) {
                    $tableId = DB::table('price_tables')->insertGetId([
                        'venue_id' => $venueId,
                        'name' => ($kind === 'normal' ? 'Bảng giá ' : 'Ngày lễ ').++$number,
                        'kind' => $kind,
                        'status' => 'active',
                        'created_at' => $stamp,
                        'updated_at' => $stamp,
                    ]);
                    $versionId = DB::table('price_table_versions')->insertGetId([
                        'price_table_id' => $tableId, 'published_at' => $at, 'effective_at' => $at, 'created_at' => $stamp, 'updated_at' => $stamp,
                    ]);
                    DB::table('price_table_intervals')->insert(array_map(fn (array $interval): array => [
                        'price_table_version_id' => $versionId,
                        'weekdays' => $interval['weekdays'] === null ? null : json_encode($interval['weekdays']),
                        'dates' => $interval['dates'] === null ? null : json_encode($interval['dates']),
                        'start_minute' => $interval['start_minute'],
                        'end_minute' => $interval['end_minute'],
                        'price_per_hour' => $interval['price_per_hour'],
                        'created_at' => $stamp,
                        'updated_at' => $stamp,
                    ], json_decode($content, true)));
                    DB::table('court_price_table_links')->insert(array_map(fn (int $courtId): array => [
                        'court_id' => $courtId, 'price_table_id' => $tableId, 'kind' => $kind, 'starts_at' => $at, 'ends_at' => null, 'created_at' => $stamp, 'updated_at' => $stamp,
                    ], $courtIds));
                }
            }
        }
    }

    /**
     * Old price against the engine for every half-hour slot start from now on, over the next 14 days, the court's
     * holiday dates and the week after each future effective date of its rules.
     *
     * @param  array{court: Court, rules: Collection<int, object>, slots: Collection<int, object>, tz: string, linked: bool, holidays: list<string>}  $plan
     * @return list<string>
     */
    private function compare(array $plan, CarbonImmutable $now): array
    {
        $court = $plan['court'];
        $today = $now->setTimezone($plan['tz'])->startOfDay();
        $dates = [...array_map(fn (int $offset): string => $today->addDays($offset)->toDateString(), range(0, 13)), ...$plan['holidays']];
        foreach ($plan['rules'] as $rule) {
            $until = $rule->effective_until === null ? null : CarbonImmutable::parse($rule->effective_until)->addDay()->toDateString();
            foreach (array_filter([$rule->effective_from, $until], fn (?string $date): bool => $date !== null && $date > $today->toDateString()) as $boundary) {
                array_push($dates, ...array_map(fn (int $offset): string => CarbonImmutable::parse($boundary)->addDays($offset)->toDateString(), range(0, 6)));
            }
        }

        $duration = (int) $court->slot_duration;
        $intervals = [];
        $old = [];
        foreach (array_unique($dates) as $date) {
            $day = CarbonImmutable::parse($date, $plan['tz']);
            $candidates = $this->candidates($plan, $day, true);
            for ($start = 0; $start + $duration <= 1440; $start += 30) {
                $price = $this->oldPrice($candidates, $start, $start + $duration);
                if ($day->addMinutes($start) >= $now && $price !== null) {
                    $intervals[] = [$day->addMinutes($start), $day->addMinutes($start + $duration)];
                    $old[] = $price;
                }
            }
        }

        $quotes = app(RentalPriceCalculator::class)->quoteEach($court, $intervals);
        $problems = [];
        foreach ($intervals as $key => [$startsAt]) {
            $new = isset($quotes[$key]) ? $quotes[$key]->rental : false;
            if ($new !== $old[$key]) {
                $problems[] = sprintf('court %d (%s) %s: old %s, price table %s', $court->id, $court->name, $startsAt->format('Y-m-d H:i'),
                    $old[$key] === false ? 'unpriced' : $old[$key], $new === false ? 'unpriced' : $new);
            }
        }

        return $problems;
    }

    /**
     * What the old resolver charged for [start, end): the best-ranked candidate of the highest tier touching it, if it
     * covers the slot and nothing better-ranked touches it. Null when it refused a slot across a price boundary,
     * which the engine now prices by the minute (design D5); false when nothing priced it.
     *
     * @param  list<array{type: int, rank: list<int>, window: array{0: int, 1: int}, price: int}>  $candidates
     */
    private function oldPrice(array $candidates, int $start, int $end): int|false|null
    {
        foreach (array_keys(self::TYPES) as $type) {
            $touching = array_filter($candidates, fn (array $candidate): bool => $candidate['type'] === $type
                && $candidate['window'][0] < $end && $candidate['window'][1] > $start);
            if ($touching === []) {
                continue;
            }
            $best = null;
            foreach ($touching as $candidate) {
                if ($candidate['window'][0] <= $start && $candidate['window'][1] >= $end && ($best === null || $candidate['rank'] > $best['rank'])) {
                    $best = $candidate;
                }
            }
            if ($best === null || collect($touching)->contains(fn (array $candidate): bool => $candidate['rank'] > $best['rank'])) {
                return null;
            }

            return $best['price'];
        }

        return false;
    }

    /**
     * Each minute at the price of the highest tier with a candidate there, then the best rank. A slot price becomes a
     * price per hour at the court's slot length, rounded up so that one slot still costs it; a free minute stays
     * unpriced, as a price table charges more than 0.
     *
     * @param  list<array{type: int, rank: list<int>, window: array{0: int, 1: int}, price: int}>  $candidates
     * @return list<array{0: int, 1: int, 2: int}> [start, end, price per hour]
     */
    private function perHour(array $candidates, int $duration): array
    {
        $bounds = [0, 1440];
        foreach ($candidates as $candidate) {
            array_push($bounds, ...$candidate['window']);
        }
        $bounds = array_unique($bounds);
        sort($bounds);

        $segments = [];
        foreach (array_slice($bounds, 0, -1) as $index => $from) {
            $to = $bounds[$index + 1];
            $best = null;
            foreach ($candidates as $candidate) {
                if ($candidate['window'][0] <= $from && $candidate['window'][1] >= $to
                    && ($best === null || $candidate['type'] < $best['type'] || ($candidate['type'] === $best['type'] && $candidate['rank'] > $best['rank']))) {
                    $best = $candidate;
                }
            }
            $price = $best === null ? 0 : intdiv($best['price'] * 60 + $duration - 1, $duration);
            if ($price <= 0) {
                continue;
            }
            $last = count($segments) - 1;
            if ($last >= 0 && $segments[$last][1] === $from && $segments[$last][2] === $price) {
                $segments[$last][1] = $to;
            } else {
                $segments[] = [$from, $to, $price];
            }
        }

        return $segments;
    }

    /**
     * Day segments as interval rows: one row per [start, end) and price, listing its days.
     *
     * @param  array<int|string, list<array{0: int, 1: int, 2: int}>>  $days  ISO weekday or Y-m-d => segments
     * @return list<array{weekdays: ?list<int>, dates: ?list<string>, start_minute: int, end_minute: int, price_per_hour: int}>
     */
    private function intervals(array $days, string $field): array
    {
        $rows = [];
        foreach ($days as $day => $segments) {
            foreach ($segments as [$start, $end, $price]) {
                $rows["{$start}-{$end}-{$price}"] ??= ['weekdays' => null, 'dates' => null, 'start_minute' => $start, 'end_minute' => $end, 'price_per_hour' => $price];
                $rows["{$start}-{$end}-{$price}"][$field][] = $day;
            }
        }

        return array_values($rows);
    }

    /**
     * The old resolver's candidates for a court on a local date: tier, rank, daily [start, end) minutes, slot price.
     *
     * @param  array{court: Court, rules: Collection<int, object>, slots: Collection<int, object>}  $plan
     * @return list<array{type: int, rank: list<int>, window: array{0: int, 1: int}, price: int}>
     */
    private function candidates(array $plan, CarbonImmutable $date, bool $withHolidays): array
    {
        $court = $plan['court'];
        $candidates = [];
        foreach ($plan['rules'] as $rule) {
            $type = array_search($rule->type, self::TYPES, true);
            if ($type === false || (! $withHolidays && $type < 2) || ! $this->appliesOn($rule, $date)) {
                continue;
            }
            $candidates[] = [
                'type' => $type,
                'rank' => [$this->scopeRank($rule, $court), (int) $rule->priority, (int) $rule->version, (int) $rule->id],
                // A base rule, or one without both bounds, spans the whole day.
                'window' => $rule->type === 'base' || $rule->starts_at === null || $rule->ends_at === null
                    ? [0, 1440]
                    : [VenueOperatingCalendar::minuteOfDay($rule->starts_at), VenueOperatingCalendar::minuteOfDay($rule->ends_at, true)],
                'price' => (int) $rule->price,
            ];
        }
        foreach ($plan['slots'] as $slot) {
            $window = [VenueOperatingCalendar::minuteOfDay($slot->starts_at), VenueOperatingCalendar::minuteOfDay($slot->ends_at, true)];
            // A legacy slot beats venue-wide rules but not a court rule, and prices only slots of its own length.
            if ((int) $slot->weekday === $date->dayOfWeekIso && (int) $court->slot_duration === $window[1] - $window[0]) {
                $candidates[] = ['type' => 3, 'rank' => [4, -1, 0, 0], 'window' => $window, 'price' => (int) $slot->price];
            }
        }

        return $candidates;
    }

    private function appliesTo(object $rule, Court $court): bool
    {
        return (int) $rule->organization_id === (int) $court->venue?->organization_id
            && ($rule->venue_id === null || (int) $rule->venue_id === (int) $court->venue_id)
            && ($rule->sport_type === null || $rule->sport_type === $court->sport_type)
            && ($rule->court_id === null || (int) $rule->court_id === (int) $court->id);
    }

    private function appliesOn(object $rule, CarbonImmutable $date): bool
    {
        $day = $date->toDateString();
        if (($rule->effective_from !== null && $day < $rule->effective_from) || ($rule->effective_until !== null && $day > $rule->effective_until)) {
            return false;
        }
        if (in_array($rule->type, ['date_exception', 'holiday'], true)) {
            return $rule->exception_date === $day;
        }

        return $rule->type !== 'weekly' || (int) $rule->weekday === $date->dayOfWeekIso;
    }

    private function scopeRank(object $rule, Court $court): int
    {
        return match (true) {
            $rule->court_id !== null && (int) $rule->court_id === (int) $court->id => 4,
            $rule->sport_type !== null && $rule->sport_type === $court->sport_type => 3,
            $rule->venue_id !== null && (int) $rule->venue_id === (int) $court->venue_id => 2,
            default => 1,
        };
    }
};
