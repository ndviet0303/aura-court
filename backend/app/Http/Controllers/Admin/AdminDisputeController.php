<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\DisputeRepositoryInterface;
use App\Domain\Admin\Actions\ArbitrateDispute;
use App\Domain\Admin\Actions\GetDisputeQueue;
use App\Domain\Admin\Actions\GetDisputeStats;
use App\Domain\Admin\DTOs\ArbitrateDisputeData;
use App\Domain\Admin\DTOs\DisputeFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ArbitrateDisputeRequest;
use App\Http\Requests\Admin\ListDisputeRequest;
use App\Http\Resources\Admin\DisputeCaseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminDisputeController extends Controller
{
    public function __construct(
        private readonly GetDisputeQueue $getQueue,
        private readonly GetDisputeStats $getStats,
        private readonly ArbitrateDispute $arbitrateDispute,
        private readonly DisputeRepositoryInterface $disputeRepository,
    ) {}

    public function index(ListDisputeRequest $request): AnonymousResourceCollection
    {
        $filter = DisputeFilterData::fromRequest($request);
        $records = $this->getQueue->execute($filter);

        return DisputeCaseResource::collection($records);
    }

    public function stats(Request $request): JsonResponse
    {
        $stats = $this->getStats->execute();

        return response()->json(['stats' => $stats]);
    }

    public function show(int $id): JsonResponse
    {
        $record = $this->disputeRepository->findById($id);

        if (!$record) {
            return response()->json(['message' => 'Hồ sơ tranh chấp không tồn tại.'], 404);
        }

        return response()->json(['data' => DisputeCaseResource::make($record)]);
    }

    public function arbitrate(ArbitrateDisputeRequest $request, int $id): JsonResponse
    {
        $dto = ArbitrateDisputeData::fromRequest($request, $id);
        $updated = $this->arbitrateDispute->execute($dto);

        $messages = [
            'resolve_and_refund' => 'Đã ban hành phán quyết, hoàn tiền và kích hoạt bồi thường thành công.',
            'resolve' => 'Đã ban hành phán quyết và giải quyết tranh chấp.',
            'dismiss' => 'Đã bác bỏ khiếu nại và lưu vết kiểm toán.',
            'investigate_further' => 'Đã chuyển trạng thái yêu cầu điều tra bổ sung.',
        ];

        return response()->json([
            'message' => $messages[$dto->decision] ?? 'Đã cập nhật kết quả phán quyết.',
            'data' => DisputeCaseResource::make($updated),
        ]);
    }
}
