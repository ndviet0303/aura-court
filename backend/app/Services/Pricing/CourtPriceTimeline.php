<?php

namespace App\Services\Pricing;

use App\Models\CourtPriceTableLink;
use App\Models\PriceTableInterval;
use App\Models\PriceTableVersion;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Which versions price one court over time for a booking confirmed at $confirmedAt (design D6): at play time t,
 * each kind's link in effect at t and, of its table, the latest version published by $confirmedAt whose
 * effective time is at or before t. Shared by the rental calculation and the coverage check.
 */
final class CourtPriceTimeline
{
    /** @param  Collection<int, CourtPriceTableLink>  $links  one court's links with priceTable.versions.intervals loaded */
    public function __construct(private readonly Collection $links, private readonly CarbonInterface $confirmedAt) {}

    /**
     * The instants strictly inside ($from, $until) where a link starts or ends or a version takes effect, in order.
     * Each is taken at the next whole minute: prices change between minutes, as versionAt() reads them.
     *
     * @return list<CarbonImmutable>
     */
    public function changesBetween(CarbonInterface $from, ?CarbonInterface $until): array
    {
        $points = [];
        foreach ($this->links as $link) {
            $points[] = $link->starts_at;
            $points[] = $link->ends_at;
            foreach ($link->priceTable->versions as $version) {
                $points[] = $version->effective_at;
            }
        }

        return collect($points)
            ->filter()
            ->map(fn (CarbonInterface $point): CarbonImmutable => CarbonImmutable::instance($point)->ceilMinute())
            ->filter(fn (CarbonImmutable $point): bool => $point > $from && ($until === null || $point < $until))
            ->unique(fn (CarbonImmutable $point): int => $point->getTimestamp())
            ->sortBy(fn (CarbonImmutable $point): int => $point->getTimestamp())
            ->values()
            ->all();
    }

    /** The version that prices play at $at through the court's link of the given kind, if any. */
    public function versionAt(string $kind, CarbonInterface $at): ?PriceTableVersion
    {
        $link = $this->links->first(fn (CourtPriceTableLink $link): bool => $link->kind === $kind
            && $link->starts_at <= $at
            && ($link->ends_at === null || $link->ends_at > $at));

        return $link?->priceTable->versions
            ->filter(fn (PriceTableVersion $version): bool => $version->published_at <= $this->confirmedAt && $version->effective_at <= $at)
            ->sortBy([['published_at', 'desc'], ['id', 'desc']])
            ->first();
    }

    /**
     * The version and its intervals (by start) that price a local date: on a date the holiday version lists, only
     * its intervals of that date count (design D4); otherwise the normal version's of the date's weekday.
     *
     * @return array{0: ?PriceTableVersion, 1: list<PriceTableInterval>}
     */
    public static function pricing(?PriceTableVersion $normal, ?PriceTableVersion $holiday, string $date, int $weekday): array
    {
        $holidayIntervals = $holiday?->intervals->filter(fn (PriceTableInterval $interval): bool => in_array($date, $interval->dates ?? [], true));
        if ($holidayIntervals !== null && $holidayIntervals->isNotEmpty()) {
            return [$holiday, $holidayIntervals->values()->all()];
        }

        return [$normal, $normal?->intervals->filter(fn (PriceTableInterval $interval): bool => in_array($weekday, $interval->weekdays ?? [], true))->values()->all() ?? []];
    }
}
