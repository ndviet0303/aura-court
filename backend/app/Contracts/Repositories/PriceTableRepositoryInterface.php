<?php

namespace App\Contracts\Repositories;

use App\Models\Court;
use App\Models\CourtPriceTableLink;
use App\Models\PriceTable;
use App\Models\PriceTableVersion;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

interface PriceTableRepositoryInterface
{
    /**
     * The courts' links in effect at some instant of [$from, $until), open-ended without $until, with each
     * linked table's versions and their intervals (by start) loaded. Four queries for any number of courts.
     *
     * @param  list<int>  $courtIds
     * @return Collection<int, CourtPriceTableLink>
     */
    public function linksForCourts(array $courtIds, CarbonInterface $from, ?CarbonInterface $until = null): Collection;

    /**
     * The venue's tables, oldest first, each with its links in effect now or later and their courts.
     *
     * @return Collection<int, PriceTable>
     */
    public function forVenue(int $venueId): Collection;

    /** Loads the table's versions (newest first) with their intervals, and its links in effect now or later with their courts. */
    public function loadDetail(PriceTable $table): PriceTable;

    /** @param  array{venue_id: int, name: string, kind: string}  $attributes */
    public function create(array $attributes): PriceTable;

    /**
     * @param  list<array{weekdays: ?list<int>, dates: ?list<string>, start_minute: int, end_minute: int, price_per_hour: int}>  $intervals
     */
    public function publishVersion(PriceTable $table, CarbonInterface $publishedAt, CarbonInterface $effectiveAt, array $intervals): PriceTableVersion;

    public function retire(PriceTable $table): PriceTable;

    public function findForUpdate(int $id): ?PriceTable;

    /**
     * The courts that exist among $courtIds, locked in id order so link changes of one court run one at a time.
     *
     * @param  list<int>  $courtIds
     * @return Collection<int, Court>
     */
    public function lockCourts(array $courtIds): Collection;

    /**
     * Ends the courts' $kind links in effect at $at there and deletes those that start at or after it (design D2).
     *
     * @param  list<int>  $courtIds
     */
    public function cutLinks(array $courtIds, string $kind, CarbonInterface $at): void;

    /**
     * Open-ended links of the courts to the table from $startsAt.
     *
     * @param  list<int>  $courtIds
     */
    public function createLinks(PriceTable $table, array $courtIds, CarbonInterface $startsAt): void;
}
