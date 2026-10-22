<?php

namespace App\Contracts\Repositories;

use App\Domain\Finance\DTOs\OwnerFinanceFilterData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OwnerFinanceTransactionRepositoryInterface
{
    /**
     * D12: the organization's received booking-payment receipts whose transaction time is in the period, newest first.
     * Each receipt has `venue` loaded and a `reservation` relation set: its booking when one is identified, else null.
     */
    public function paginate(OwnerFinanceFilterData $filter): LengthAwarePaginator;
}
