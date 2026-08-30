<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\DTOs\TriggerReconciliationData;
use App\Models\Organization;
use App\Models\ReconciliationRun;
use App\Services\Finance\ReconciliationService;
use Carbon\Carbon;

class TriggerReconciliation
{
    public function __construct(
        private readonly ReconciliationService $reconciliationService
    ) {}

    public function execute(Organization $organization, TriggerReconciliationData $data): ReconciliationRun
    {
        $runDate = $data->runDate ? Carbon::parse($data->runDate) : Carbon::today();
        return $this->reconciliationService->run($organization, $runDate);
    }
}
