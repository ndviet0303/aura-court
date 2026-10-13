<?php

namespace App\Http\Controllers\Finance;

use App\Contracts\Repositories\AuditLogRepositoryInterface;
use App\Domain\Finance\Actions\GetFinanceExceptions;
use App\Domain\Finance\Actions\GetFinanceExceptionStats;
use App\Domain\Finance\Actions\ResolveFinanceException;
use App\Domain\Finance\DTOs\FinanceExceptionFilterData;
use App\Domain\Finance\DTOs\ResolveFinanceExceptionData;
use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListFinanceExceptionsRequest;
use App\Http\Requests\Finance\ResolveFinanceExceptionRequest;
use App\Http\Resources\Finance\AuditLogResource;
use App\Http\Resources\Finance\FinanceExceptionResource;
use App\Services\Infrastructure\IdempotencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FinanceExceptionController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        private readonly GetFinanceExceptions $getExceptions,
        private readonly GetFinanceExceptionStats $getStats,
        private readonly ResolveFinanceException $resolveException,
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function index(ListFinanceExceptionsRequest $request): AnonymousResourceCollection
    {
        $organization = $this->organization($request);
        $filter = FinanceExceptionFilterData::fromRequest($request);
        $exceptions = $this->getExceptions->execute($organization, $filter);

        return FinanceExceptionResource::collection($exceptions);
    }

    public function stats(Request $request): JsonResponse
    {
        $organization = $this->organization($request);
        $stats = $this->getStats->execute($organization);

        return response()->json(['stats' => $stats]);
    }

    public function resolve(ResolveFinanceExceptionRequest $request, int $id): JsonResponse
    {
        $organization = $this->organization($request);
        $dto = ResolveFinanceExceptionData::fromRequest($request, $id);

        return $this->idempotency->run($request, function () use ($organization, $dto) {
            $resolved = $this->resolveException->execute($organization, $dto);

            return response()->json([
                'message' => 'Đã can thiệp và ghi nhận vào sổ cái kiểm toán thành công.',
                'exception' => FinanceExceptionResource::make($resolved),
            ]);
        });
    }

    public function auditLogs(Request $request): AnonymousResourceCollection
    {
        $action = $request->query('action');
        $perPage = (int) $request->query('per_page', 50);
        $logs = $this->auditLogs->paginate($action, $perPage);

        return AuditLogResource::collection($logs);
    }
}
