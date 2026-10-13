<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\RecurringContractRepositoryInterface;
use App\Models\User;
use App\Services\Booking\RecurringReleaseService;
use App\Services\Infrastructure\TenantContext;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Finishes contracts the pre-fix admin path cancelled without releasing their future sessions. Each contract runs in
 * its organization's context and its own transaction; a dry run rolls each one back, after-commit broadcasts included.
 */
class RepairLegacyCancelledContracts
{
    public function __construct(
        private readonly RecurringContractRepositoryInterface $contracts,
        private readonly RecurringReleaseService $releases,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @return list<array{series_id: int, organization_id: int, status: string, past_live: int, released: int, voided: int, refunds: int, refund_amount: int}>
     */
    public function execute(User $actor, bool $apply): array
    {
        $rows = [];
        foreach ($this->contracts->legacyCancelRepairCandidates() as $series) {
            $result = $this->tenants->executeInContext($series->organization, function () use ($series, $actor, $apply): ?array {
                DB::beginTransaction();
                try {
                    $result = $this->releases->repairLegacyCancel((int) $series->id, $actor);
                    if ($apply) {
                        DB::commit();
                    } else {
                        DB::rollBack();
                    }

                    return $result;
                } catch (Throwable $e) {
                    DB::rollBack();
                    throw $e;
                }
            });
            if ($result !== null) {
                $rows[] = ['series_id' => (int) $series->id, 'organization_id' => (int) $series->organization_id, 'status' => (string) $series->status] + $result;
            }
        }

        return $rows;
    }
}
