<?php

namespace App\Services;

use App\Models\ProviderEvent;

class ProviderEventHandler
{
    public function __construct(private readonly FinancialPostingService $financials) {}

    public function handle(ProviderEvent $event): void
    {
        $this->financials->postProviderCollection($event);
    }
}
