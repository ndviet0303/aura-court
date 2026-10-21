<?php

namespace App\Domain\Pricing\Actions;

use App\Contracts\Repositories\PriceTableRepositoryInterface;
use App\Domain\Pricing\DTOs\PublishPriceTableVersionData;
use App\Domain\Subscription\Actions\EvaluatePlatformEntitlement;
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
        private readonly EvaluatePlatformEntitlement $entitlement,
    ) {}

    public function execute(PublishPriceTableVersionData $data): PriceTable
    {
        $entitlement = $this->entitlement->execute($data->organizationId);
        if (! $entitlement['is_valid']) {
            abort(response()->json([
                'message' => 'Cần gói dịch vụ nền tảng còn hạn để công bố bảng giá.',
                'code' => $entitlement['reason'] ?? 'SUBSCRIPTION_REQUIRED',
            ], 403));
        }

        [$table, $effectiveAt, $courtIds] = DB::transaction(function () use ($data): array {
            // Only the table row: link changes lock their courts before this same row, so the order stays court first.
            $table = $this->priceTables->findForUpdate($data->priceTableId) ?? abort(404);
            if ($table->status === PriceTable::STATUS_RETIRED) {
                throw ValidationException::withMessages(['price_table' => 'Bảng giá đã ngừng sử dụng nên không công bố được phiên bản mới.']);
            }

            $now = CarbonImmutable::now();
            $version = $this->priceTables->publishVersion($table, $now, ($data->effectiveAt ?? $now)->max($now), $data->intervals);
            $courtIds = $this->priceTables->courtIdsLinkedFrom($table, $now);
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
