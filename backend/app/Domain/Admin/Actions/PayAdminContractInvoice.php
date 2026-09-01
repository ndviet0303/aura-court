<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\RecurringContractRepositoryInterface;
use App\Models\MonthlyInvoice;

class PayAdminContractInvoice
{
    public function __construct(
        private readonly RecurringContractRepositoryInterface $contracts,
    ) {}

    public function execute(int $seriesId, int $invoiceId, string $paymentReference): MonthlyInvoice
    {
        return $this->contracts->payInvoice($seriesId, $invoiceId, $paymentReference);
    }
}
