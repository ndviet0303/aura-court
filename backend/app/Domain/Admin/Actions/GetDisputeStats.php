<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\DisputeRepositoryInterface;

class GetDisputeStats
{
    public function __construct(
        private readonly DisputeRepositoryInterface $disputeRepository,
    ) {}

    public function execute(): array
    {
        return $this->disputeRepository->getStats();
    }
}
