<?php

namespace App\Domain\Pricing\Actions;

use App\Contracts\Repositories\PriceTableRepositoryInterface;
use App\Domain\Pricing\DTOs\PriceTableLinkChange;
use App\Models\PriceTable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A retired table takes no new link or version; courts already on it keep its prices (design D2). With a
 * replacement, the chosen courts switch to it at the switch time in the same transaction (UC-3.6 A-1), so a gap or
 * a refused replacement retires nothing.
 */
class RetirePriceTable
{
    public function __construct(
        private readonly PriceTableRepositoryInterface $priceTables,
        private readonly ChangeCourtPriceTableLinks $links,
    ) {}

    /** @param  list<int>  $courtIds */
    public function execute(PriceTable $table, ?int $replacementId = null, array $courtIds = [], ?CarbonInterface $switchAt = null): PriceTable
    {
        return DB::transaction(function () use ($table, $replacementId, $courtIds, $switchAt): PriceTable {
            if ($replacementId !== null) {
                // Read without a lock: the link change locks the courts, then the replacement, as every link change does.
                $replacement = $this->priceTables->forVenue((int) $table->venue_id)->firstWhere('id', $replacementId);
                if ($replacement === null || $replacement->kind !== $table->kind) {
                    throw ValidationException::withMessages(['replacement_table_id' => 'Bảng giá thay thế phải cùng cơ sở và cùng loại.']);
                }
                $this->links->execute(PriceTableLinkChange::apply($replacement, $courtIds, $switchAt));
            }

            return $this->priceTables->retire($table);
        });
    }
}
