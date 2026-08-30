<?php

namespace App\Domain\Finance\Actions;

use App\Contracts\Repositories\FinanceExceptionRepositoryInterface;
use App\Models\Organization;

class GetFinanceExceptionStats
{
    public function __construct(
        private readonly FinanceExceptionRepositoryInterface $exceptions,
    ) {}

    public function execute(Organization $organization): array
    {
        return $this->exceptions->getStatsForOrganization($organization);
    }
}
