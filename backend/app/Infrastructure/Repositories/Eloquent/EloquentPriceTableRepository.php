<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\PriceTableRepositoryInterface;
use App\Models\Court;
use App\Models\CourtPriceTableLink;
use App\Models\PriceTable;
use App\Models\PriceTableVersion;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentPriceTableRepository implements PriceTableRepositoryInterface
{
    public function linksForCourts(array $courtIds, CarbonInterface $from, ?CarbonInterface $until = null): Collection
    {
        // Link times are stored in UTC; a bound in another timezone would be compared by its clock.
        $from = CarbonImmutable::instance($from)->utc();

        return CourtPriceTableLink::query()
            ->with(['priceTable.versions.intervals' => fn ($query) => $query->orderBy('start_minute')])
            ->whereIn('court_id', $courtIds)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $from))
            ->when($until, fn ($query) => $query->where('starts_at', '<', CarbonImmutable::instance($until)->utc()))
            ->orderBy('starts_at')
            ->get();
    }

    public function forVenue(int $venueId): Collection
    {
        return PriceTable::query()->with($this->currentLinks())->where('venue_id', $venueId)->orderBy('id')->get();
    }

    public function loadDetail(PriceTable $table): PriceTable
    {
        return $table->load([
            'versions' => fn ($query) => $query->orderByDesc('published_at')->orderByDesc('id'),
            'versions.intervals' => fn ($query) => $query->orderBy('id'),
            ...$this->currentLinks(),
        ]);
    }

    public function create(array $attributes): PriceTable
    {
        return PriceTable::create($attributes + ['status' => PriceTable::STATUS_ACTIVE]);
    }

    public function publishVersion(PriceTable $table, CarbonInterface $publishedAt, CarbonInterface $effectiveAt, array $intervals): PriceTableVersion
    {
        $version = $table->versions()->create(['published_at' => $publishedAt, 'effective_at' => $effectiveAt]);
        $version->intervals()->createMany($intervals);

        return $version;
    }

    public function retire(PriceTable $table): PriceTable
    {
        $table->update(['status' => PriceTable::STATUS_RETIRED]);

        return $table;
    }

    public function findForUpdate(int $id): ?PriceTable
    {
        return PriceTable::query()->whereKey($id)->lockForUpdate()->first();
    }

    public function courtIdsLinkedFrom(int $tableId, CarbonInterface $at): array
    {
        $at = CarbonImmutable::instance($at)->utc();

        return CourtPriceTableLink::query()
            ->where('price_table_id', $tableId)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $at))
            ->distinct()
            ->orderBy('court_id')
            ->pluck('court_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function lockCourts(array $courtIds): Collection
    {
        return Court::query()->whereIn('id', $courtIds)->orderBy('id')->lockForUpdate()->get();
    }

    public function cutLinks(array $courtIds, string $kind, CarbonInterface $at): void
    {
        $at = CarbonImmutable::instance($at)->utc();
        $links = fn () => CourtPriceTableLink::query()->whereIn('court_id', $courtIds)->where('kind', $kind);

        $links()->where('starts_at', '>=', $at)->delete();
        $links()->where('starts_at', '<', $at)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $at))
            ->update(['ends_at' => $at]);
    }

    public function createLinks(PriceTable $table, array $courtIds, CarbonInterface $startsAt): void
    {
        foreach ($courtIds as $courtId) {
            CourtPriceTableLink::create([
                'court_id' => $courtId,
                'price_table_id' => $table->id,
                'kind' => $table->kind,
                'starts_at' => $startsAt,
            ]);
        }
    }

    /** @return array<int|string, \Closure|string> */
    private function currentLinks(): array
    {
        return [
            'links' => fn ($query) => $query
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', CarbonImmutable::now('UTC')))
                ->orderBy('court_id')
                ->orderBy('starts_at'),
            'links.court',
        ];
    }
}
