<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\HostKycRepositoryInterface;
use App\Domain\Admin\Actions\GetHostKycQueue;
use App\Domain\Admin\Actions\GetHostKycStats;
use App\Domain\Admin\Actions\ReviewHostKyc;
use App\Domain\Admin\DTOs\HostKycFilterData;
use App\Domain\Admin\DTOs\ReviewHostKycData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListHostKycRequest;
use App\Http\Requests\Admin\ReviewHostKycRequest;
use App\Http\Resources\Admin\HostKycResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminKycController extends Controller
{
    public function __construct(
        private readonly GetHostKycQueue $getQueue,
        private readonly GetHostKycStats $getStats,
        private readonly ReviewHostKyc $reviewKyc,
        private readonly HostKycRepositoryInterface $kycRepository,
    ) {}

    public function index(ListHostKycRequest $request): AnonymousResourceCollection
    {
        $filter = HostKycFilterData::fromRequest($request);
        $records = $this->getQueue->execute($filter);

        return HostKycResource::collection($records);
    }

    public function stats(Request $request): JsonResponse
    {
        $stats = $this->getStats->execute();

        return response()->json(['stats' => $stats]);
    }

    public function show(int $id): JsonResponse
    {
        $record = $this->kycRepository->findById($id);

        if (! $record) {
            return response()->json(['message' => 'Hồ sơ KYC không tồn tại.'], 404);
        }

        return response()->json(['data' => HostKycResource::make($record)]);
    }

    public function review(ReviewHostKycRequest $request, int $id): JsonResponse
    {
        $dto = ReviewHostKycData::fromRequest($request, $id);
        $updated = $this->reviewKyc->execute($dto);

        $messages = [
            'approve' => 'Hồ sơ KYC đã được phê duyệt và kích hoạt chủ sân thành công.',
            'reject' => 'Đã từ chối hồ sơ KYC và gửi thông báo lý do đến chủ sân.',
            'request_supplement' => 'Đã gửi yêu cầu bổ sung thông tin hồ sơ cho chủ sân.',
        ];

        return response()->json([
            'message' => $messages[$dto->decision] ?? 'Đã cập nhật trạng thái hồ sơ.',
            'data' => HostKycResource::make($updated),
        ]);
    }
}
