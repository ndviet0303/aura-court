<?php

namespace App\Contracts\Repositories;

use App\Domain\Package\DTOs\PlatformFeeRequestFilterData;
use App\Models\PackagePaymentRequest;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PlatformFeeRequestRepositoryInterface
{
    /**
     * D15: requests of every organization, newest first, with `venue` and `organization.owner` loaded. The status filter
     * reads a pending request past its deadline as expired at $now, as PackagePaymentRequest::statusAt() does.
     * D14: an export reads this same query.
     *
     * @return LengthAwarePaginator<int, PackagePaymentRequest>
     */
    public function paginate(PlatformFeeRequestFilterData $filter, CarbonInterface $now): LengthAwarePaginator;

    /** A request by its code, with `venue`, `organization.owner` and every stored receipt in transfer order. */
    public function findByCode(string $requestCode): ?PackagePaymentRequest;
}
