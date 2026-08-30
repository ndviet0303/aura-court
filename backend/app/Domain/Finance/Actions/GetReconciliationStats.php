<?php

namespace App\Domain\Finance\Actions;

use App\Contracts\Repositories\ReconciliationRepositoryInterface;
use App\Models\Organization;

class GetReconciliationStats
{
    public function __construct(
        private readonly ReconciliationRepositoryInterface $reconciliationRepository
    ) {}

    public function execute(Organization $organization): array
    {
        return $this->reconciliationRepository->getStatsForOrganization($organization);
    }
}
