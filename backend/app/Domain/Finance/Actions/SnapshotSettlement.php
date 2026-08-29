<?php

namespace App\Domain\Finance\Actions;

use App\Models\SettlementSnapshot;
use App\Services\Finance\SettlementService;

class SnapshotSettlement
{
    public function __construct(
        private readonly SettlementService $settlements,
    ) {}

    public function execute(int $organizationId, string $periodStart, string $periodEnd, int $actorId): SettlementSnapshot
    {
        return $this->settlements->snapshot($organizationId, $periodStart, $periodEnd, $actorId);
    }
}
