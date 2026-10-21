<?php

namespace App\Domain\Pricing\Actions;

use App\Contracts\Repositories\PriceTableRepositoryInterface;
use App\Models\PriceTable;

/** A retired table takes no new link or version; courts already on it keep its prices (design D2). */
class RetirePriceTable
{
    public function __construct(private readonly PriceTableRepositoryInterface $priceTables) {}

    public function execute(PriceTable $table): PriceTable
    {
        return $this->priceTables->retire($table);
    }
}
