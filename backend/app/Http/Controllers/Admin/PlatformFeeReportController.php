<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\PlatformFeeReportRepositoryInterface;
use App\Domain\Package\DTOs\PlatformFeeReportFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShowPlatformFeeReportRequest;
use App\Services\Package\PlatformFeeExport;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Platform fees collected by period, venue and term for the platform admin (D15). */
class PlatformFeeReportController extends Controller
{
    public function __construct(private readonly PlatformFeeReportRepositoryInterface $reports) {}

    public function show(ShowPlatformFeeReportRequest $request): JsonResponse
    {
        $filter = PlatformFeeReportFilterData::fromRequest($request);

        return response()->json([
            'data' => ['period' => ['from' => $filter->from, 'to' => $filter->to], ...$this->reports->report($filter)],
        ]);
    }

    public function export(ShowPlatformFeeReportRequest $request, PlatformFeeExport $export): StreamedResponse
    {
        return $export->report($request);
    }
}
