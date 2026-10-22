<?php

namespace App\Domain\Pricing\Actions;

use App\Contracts\Repositories\PriceTableRepositoryInterface;
use App\Domain\Pricing\DTOs\PublishPriceTableVersionData;
use App\Events\CourtPricesTookEffect;
use App\Models\PriceTable;
use App\Services\Pricing\PriceCoverageService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publishes a new version of a table (design D1, D6): it prices play from max(effective_at, now) for bookings
 * confirmed from now on, over any version scheduled later, and earlier versions stay as they were. Every court
 * linked to the table now or later must stay covered (D3), checked in the same transaction, so a gap publishes
 * nothing.
 */
class PublishPriceTableVersion
{
    public function __construct(
        private readonly PriceTableRepositoryInterface $priceTables,
        private readonly PriceCoverageService $coverage,
    ) {}

    public function execute(PublishPriceTableVersionData $data): PriceTable
    {
        [$table, $effectiveAt, $courtIds] = DB::transaction(function () use ($data): array {
            // Courts before the table, the order link changes use, so a link change or a publish of a court's other
            // table waits for this one and each coverage check reads the other's commit (D3).
            $courtIds = $this->priceTables->courtIdsLinkedFrom($data->priceTableId, CarbonImmutable::now());
            $this->priceTables->lockCourts($courtIds);
            $table = $this->priceTables->findForUpdate($data->priceTableId) ?? abort(404);
            if ($table->status === PriceTable::STATUS_RETIRED) {
                throw ValidationException::withMessages(['price_table' => 'Bảng giá đã ngừng sử dụng nên không công bố được phiên bản mới.']);
            }
            // The time after the locks keeps publications of one table in order. A link committed before the table
            // lock gets its court locked too (a deadlock here is retried).
            $now = CarbonImmutable::now();
            $linked = $this->priceTables->courtIdsLinkedFrom($data->priceTableId, $now);
            $this->priceTables->lockCourts(array_values(array_diff($linked, $courtIds)));
            $courtIds = $linked;

            $version = $this->priceTables->publishVersion($table, $now, ($data->effectiveAt ?? $now)->max($now), $data->intervals);
            // ponytail: every court counts until module 2 adds courts.status; inactive courts are then left out.
            $this->coverage->assertCovered($courtIds, $now);

            return [$table, CarbonImmutable::instance($version->effective_at), $courtIds];
        }, 3);

        // The public court list is cached by this key and carries each court's from_price.
        DB::afterCommit(fn () => Cache::increment('courts_version'));
        if ($courtIds !== []) {
            CourtPricesTookEffect::dispatch($courtIds, $effectiveAt);
        }

        return $table;
    }
}
