<?php

namespace App\Services\Payment;

use App\Services\Finance\FinancialPostingService;

use App\Models\ProviderEvent;

class ProviderEventHandler
{
    public function __construct(private readonly FinancialPostingService $financials) {}

    public function handle(ProviderEvent $event): void
    {
        $this->financials->postProviderCollection($event);
    }
}
