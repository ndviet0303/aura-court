<?php

namespace App\Domain\Pricing\Actions;

use App\Contracts\Repositories\PriceTableRepositoryInterface;
use App\Domain\Pricing\DTOs\CreatePriceTableData;
use App\Domain\Subscription\Actions\EvaluatePlatformEntitlement;
use App\Models\PriceTable;
use Illuminate\Support\Facades\DB;

/**
 * Creating a price table publishes its first version (design D1); an effective time that is not in the future
 * takes effect at the publication. A new table has no links yet, so it needs no coverage check and leaves the
 * cached public court list alone.
 */
class CreatePriceTable
{
    public function __construct(
        private readonly PriceTableRepositoryInterface $priceTables,
        private readonly EvaluatePlatformEntitlement $entitlement,
    ) {}

    public function execute(CreatePriceTableData $data): PriceTable
    {
        $entitlement = $this->entitlement->execute($data->organizationId);
        if (! $entitlement['is_valid']) {
            abort(response()->json([
                'message' => 'Cần gói dịch vụ nền tảng còn hạn để tạo bảng giá.',
                'code' => $entitlement['reason'] ?? 'SUBSCRIPTION_REQUIRED',
            ], 403));
        }

        return DB::transaction(function () use ($data): PriceTable {
            $table = $this->priceTables->create(['venue_id' => $data->venueId, 'name' => $data->name, 'kind' => $data->kind]);
            $publishedAt = now();
            $this->priceTables->publishVersion($table, $publishedAt, $data->effectiveAt->max($publishedAt), $data->intervals);

            return $table;
        });
    }
}
