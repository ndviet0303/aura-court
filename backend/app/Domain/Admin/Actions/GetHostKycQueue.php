<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\HostKycRepositoryInterface;
use App\Domain\Admin\DTOs\HostKycFilterData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetHostKycQueue
{
    public function __construct(
        private readonly HostKycRepositoryInterface $kycRepository,
    ) {}

    public function execute(HostKycFilterData $filter): LengthAwarePaginator
    {
        return $this->kycRepository->paginate(
            status: $filter->status,
            search: $filter->search,
            perPage: $filter->perPage
        );
    }
}
