<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\ReconciliationService;
use App\Services\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunDailyReconciliation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $organizationId) {}

    public function handle(ReconciliationService $reconciliation, TenantContext $tenantContext): void
    {
        $organization = Organization::findOrFail($this->organizationId);
        $tenantContext->executeInContext($organization, fn () => $reconciliation->run($organization));
    }
}
