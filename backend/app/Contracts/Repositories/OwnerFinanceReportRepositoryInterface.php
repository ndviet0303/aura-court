<?php

namespace App\Contracts\Repositories;

use App\Domain\Finance\DTOs\OwnerFinanceFilterData;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Collection;

interface OwnerFinanceReportRepositoryInterface
{
    /**
     * D12: the five separate metrics of the period, keyed received, completed, unfinished, no_show and incident, each with
     * its amount, its count of distinct booking codes and its rows. A row is a booking with `court.venue` and `slots`
     * loaded, plus `finance_at` (the time that put it in the period) and `finance_amount` (what the metric adds).
     * D14: an export reads this same result.
     *
     * @return array<string, array{amount: int, count: int, rows: Collection<int, Reservation>}>
     */
    public function metrics(OwnerFinanceFilterData $filter): array;
}
