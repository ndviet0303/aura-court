<?php

namespace App\Services\Reservation;

use App\Contracts\Repositories\PriceTableRepositoryInterface;
use App\Models\Court;
use App\Models\CourtPriceTableLink;
use App\Models\PriceTable;
use App\Models\PriceTableVersion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/** Whether courts are priced now and from what price (design D18, D28); a court not priced is not public or bookable. */
class CourtPriceRuleService
{
    public function __construct(private readonly PriceTableRepositoryInterface $priceTables) {}

    public function isPriced(Court $court): bool
    {
        return isset($this->normalTables([$court])[(int) $court->id]);
    }

    /**
     * @param  iterable<Model>  $courts  courts; only their ids are read
     * @return array<int, bool> ids of the courts with a normal link in effect now, as keys
     */
    public function pricedCourtIds(iterable $courts): array
    {
        return array_map(fn (): bool => true, $this->normalTables($courts));
    }

    /**
     * The table of each court's normal link in effect now, in two queries for any number of courts.
     *
     * @param  iterable<Model>  $courts  courts; only their ids are read
     * @return array<int, PriceTable>
     */
    public function normalTables(iterable $courts): array
    {
        $now = now()->utc();

        return CourtPriceTableLink::query()
            ->with('priceTable')
            ->whereIn('court_id', collect($courts)->pluck('id'))
            ->where('kind', PriceTable::KIND_NORMAL)
            ->where('starts_at', '<=', $now)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->get()
            ->mapWithKeys(fn (CourtPriceTableLink $link): array => [(int) $link->court_id => $link->priceTable])
            ->all();
    }

    /**
     * The lowest price per hour a booking confirmed now could pay on each court (design D18): over its links in
     * effect now or later, each version that still prices some play time from now on (D6), and holiday prices of
     * the dates that version still prices. Courts with none are left out. Courts need `venue` loaded for their local date.
     *
     * @param  iterable<Court>  $courts
     * @return array<int, int>
     */
    public function fromPrices(iterable $courts): array
    {
        $now = CarbonImmutable::now();
        $timezoneOf = collect($courts)->mapWithKeys(fn (Court $court): array => [
            (int) $court->id => $court->venue?->timezone ?: config('app.timezone'),
        ]);
        $prices = [];
        foreach ($this->priceTables->linksForCourts($timezoneOf->keys()->all(), $now) as $link) {
            $courtId = (int) $link->court_id;
            $versions = $link->priceTable->versions
                ->filter(fn (PriceTableVersion $version): bool => $version->published_at <= $now)
                ->sortBy([['published_at', 'asc'], ['id', 'asc']])
                ->values();
            foreach ($versions as $index => $version) {
                // The first play time this version could price; a later publication in effect by then replaces it.
                $playFrom = max($now, $link->starts_at, $version->effective_at);
                $laterVersions = $versions->slice($index + 1);
                if (($link->ends_at !== null && $link->ends_at <= $playFrom)
                    || $laterVersions->contains(fn (PriceTableVersion $later): bool => $later->effective_at <= $playFrom)) {
                    continue;
                }
                // It prices until the link ends or the next publication takes effect, whichever comes first.
                // ponytail: holiday dates are matched by local date, so a date the window only partly covers still counts.
                $until = $laterVersions->pluck('effective_at')->push($link->ends_at)->filter()->min();
                $timezone = $timezoneOf[$courtId];
                $fromDate = CarbonImmutable::instance($playFrom)->setTimezone($timezone)->toDateString();
                $untilDate = $until ? CarbonImmutable::instance($until)->subSecond()->setTimezone($timezone)->toDateString() : null;
                foreach ($version->intervals as $interval) {
                    if ($link->kind === PriceTable::KIND_HOLIDAY && ! collect($interval->dates)->contains(
                        fn (string $date): bool => $date >= $fromDate && ($untilDate === null || $date <= $untilDate),
                    )) {
                        continue;
                    }
                    $prices[$courtId] = min($prices[$courtId] ?? PHP_INT_MAX, (int) $interval->price_per_hour);
                }
            }
        }

        return $prices;
    }
}
