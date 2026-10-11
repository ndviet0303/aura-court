<?php

namespace App\Domain\Finance\Actions;

use App\Contracts\Repositories\ReconciliationRepositoryInterface;
use App\Models\Organization;

class GetReconciliationDetail
{
    public function __construct(
        private readonly ReconciliationRepositoryInterface $reconciliationRepository
    ) {}

    public function execute(Organization $organization, int $runId): array
    {
        $run = $this->reconciliationRepository->findForOrganization($organization, $runId);

        if (! $run) {
            abort(404, 'Reconciliation run not found.');
        }

        return $this->reconciliationRepository->getDetailForRun($organization, $run);
    }
}
