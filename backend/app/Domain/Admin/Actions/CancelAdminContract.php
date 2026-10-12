<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\RecurringContractRepositoryInterface;
use App\Models\RecurringSeries;
use App\Models\User;
use App\Services\Booking\RecurringReleaseService;
use App\Services\Infrastructure\TenantContext;
use Illuminate\Support\Facades\DB;

class CancelAdminContract
{
    public function __construct(
        private readonly RecurringContractRepositoryInterface $contracts,
        private readonly RecurringReleaseService $releases,
        private readonly TenantContext $tenants,
    ) {}

    public function execute(RecurringSeries $contract, User $actor, string $reason): RecurringSeries
    {
        // The release follows tenant-scoped relations, so it runs in the contract's organization, not the caller's.
        $this->tenants->executeInContext($contract->organization, fn () => DB::transaction(
            fn () => $this->releases->cancelSeries((int) $contract->id, $actor, $reason)
        ));

        return $this->contracts->findById((int) $contract->id);
    }
}
