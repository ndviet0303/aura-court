<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Finance\Actions\AutoResolveReconciliation;
use App\Domain\Finance\Actions\GetReconciliationDetail;
use App\Domain\Finance\Actions\GetReconciliationRuns;
use App\Domain\Finance\Actions\GetReconciliationStats;
use App\Domain\Finance\Actions\TriggerReconciliation;
use App\Domain\Finance\DTOs\AutoResolveReconciliationData;
use App\Domain\Finance\DTOs\ReconciliationFilterData;
use App\Domain\Finance\DTOs\TriggerReconciliationData;
use App\Http\Controllers\Concerns\ResolvesV2Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\AutoResolveReconciliationRequest;
use App\Http\Requests\Finance\ReconciliationFilterRequest;
use App\Http\Requests\Finance\TriggerReconciliationRequest;
use App\Http\Resources\Finance\ReconciliationDetailResource;
use App\Http\Resources\Finance\ReconciliationRunResource;
use App\Http\Resources\Finance\ReconciliationStatsResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialReconciliationController extends Controller
{
    use ResolvesV2Organization;

    public function stats(Request $request, GetReconciliationStats $action): JsonResponse
    {
        $organization = $this->organization($request);
        $stats = $action->execute($organization);

        return response()->json(new ReconciliationStatsResource($stats));
    }

    public function index(ReconciliationFilterRequest $request, GetReconciliationRuns $action): JsonResponse
    {
        $organization = $this->organization($request);
        $filterData = ReconciliationFilterData::fromRequest($request);
        $runs = $action->execute($organization, $filterData);

        return response()->json(ReconciliationRunResource::collection($runs)->response()->getData(true));
    }

    public function show(Request $request, int $id, GetReconciliationDetail $action): JsonResponse
    {
        $organization = $this->organization($request);
        $detail = $action->execute($organization, $id);

        return response()->json(new ReconciliationDetailResource($detail));
    }

    public function trigger(TriggerReconciliationRequest $request, TriggerReconciliation $action): JsonResponse
    {
        $organization = $this->organization($request);
        $data = TriggerReconciliationData::fromRequest($request);
        $run = $action->execute($organization, $data);

        return response()->json(new ReconciliationRunResource($run), 201);
    }

    public function autoResolve(AutoResolveReconciliationRequest $request, AutoResolveReconciliation $action): JsonResponse
    {
        $organization = $this->organization($request);
        $data = AutoResolveReconciliationData::fromRequest($request);
        $result = $action->execute($organization, $data, $request->user());

        return response()->json($result);
    }
}
