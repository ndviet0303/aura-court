<?php

namespace App\Http\Controllers\Partner;

use App\Contracts\Repositories\OwnerFinanceReportRepositoryInterface;
use App\Contracts\Repositories\OwnerFinanceTransactionRepositoryInterface;
use App\Domain\Finance\DTOs\OwnerFinanceFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\ListOwnerFinanceTransactionsRequest;
use App\Http\Requests\Partner\ShowOwnerFinanceReportRequest;
use App\Http\Resources\Finance\OwnerFinanceReportResource;
use App\Http\Resources\Finance\OwnerFinanceTransactionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The organization owner's booking finance (D12). These path-id routes have no tenant context, so the requests check
 * owner rights against the path organization, as the package routes do.
 */
class OwnerFinanceController extends Controller
{
    public function __construct(
        private readonly OwnerFinanceTransactionRepositoryInterface $transactions,
        private readonly OwnerFinanceReportRepositoryInterface $reports,
    ) {}

    public function transactions(ListOwnerFinanceTransactionsRequest $request, int $organizationId): AnonymousResourceCollection
    {
        return OwnerFinanceTransactionResource::collection(
            $this->transactions->paginate(OwnerFinanceFilterData::fromRequest($request, $organizationId))
        );
    }

    public function report(ShowOwnerFinanceReportRequest $request, int $organizationId): OwnerFinanceReportResource
    {
        return OwnerFinanceReportResource::make($this->reports->metrics(OwnerFinanceFilterData::fromRequest($request, $organizationId)));
    }
}
