<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\DisputeRepositoryInterface;
use App\Models\User;

class GetDisputeStats
{
    public function __construct(
        private readonly DisputeRepositoryInterface $disputeRepository,
    ) {}

    public function execute(?User $user = null, ?int $organizationId = null): array
    {
        return $this->disputeRepository->getStats(
            user: $user,
            organizationId: $organizationId
        );
    }
}
