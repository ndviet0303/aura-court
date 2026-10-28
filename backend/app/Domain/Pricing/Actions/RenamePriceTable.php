<?php

namespace App\Domain\Pricing\Actions;

use App\Contracts\Repositories\PriceTableRepositoryInterface;
use App\Models\PriceTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Renames a table that is still in use (UC-3.5 step 3); prices change only by a new version (design D1). */
class RenamePriceTable
{
    public function __construct(private readonly PriceTableRepositoryInterface $priceTables) {}

    public function execute(int $tableId, string $name): PriceTable
    {
        return DB::transaction(function () use ($tableId, $name): PriceTable {
            $table = $this->priceTables->findForUpdate($tableId) ?? abort(404);
            if ($table->status === PriceTable::STATUS_RETIRED) {
                throw ValidationException::withMessages(['price_table' => 'Bảng giá đã ngừng sử dụng nên không đổi tên được.']);
            }

            return $this->priceTables->rename($table, $name);
        });
    }
}
