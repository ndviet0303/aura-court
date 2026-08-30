<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\HostKycRepositoryInterface;

class GetHostKycStats
{
    public function __construct(
        private readonly HostKycRepositoryInterface $kycRepository,
    ) {}

    public function execute(): array
    {
        return $this->kycRepository->getStats();
    }
}
