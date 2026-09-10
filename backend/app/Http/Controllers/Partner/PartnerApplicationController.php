<?php

namespace App\Http\Controllers\Partner;

use App\Contracts\Repositories\HostKycRepositoryInterface;
use App\Domain\Partner\Actions\SavePartnerApplication;
use App\Domain\Partner\Actions\StartPartnerApplication;
use App\Domain\Partner\Actions\SubmitPartnerApplication;
use App\Domain\Partner\DTOs\SavePartnerApplicationData;
use App\Domain\Partner\DTOs\StartPartnerApplicationData;
use App\Domain\Partner\DTOs\SubmitPartnerApplicationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\SavePartnerApplicationRequest;
use App\Http\Requests\Partner\StartPartnerApplicationRequest;
use App\Http\Requests\Partner\SubmitPartnerApplicationRequest;
use App\Http\Resources\Partner\PartnerApplicationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerApplicationController extends Controller
{
    public function __construct(
        private readonly HostKycRepositoryInterface $kycRepo,
    ) {}

    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $application = $this->kycRepo->findCurrentByUserId($user->id);

        if (! $application) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => PartnerApplicationResource::make($application)]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $application = $this->kycRepo->findById($id);

        if (! $application || $application->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Không tìm thấy hồ sơ đăng ký đối tác.'], 404);
        }

        return response()->json(['data' => PartnerApplicationResource::make($application)]);
    }

    public function start(StartPartnerApplicationRequest $request, StartPartnerApplication $action): JsonResponse
    {
        $dto = StartPartnerApplicationData::from($request);
        $application = $action->execute($dto);

        return response()->json([
            'data' => PartnerApplicationResource::make($application),
            'message' => 'Khởi tạo hồ sơ đối tác thành công.',
        ], 201);
    }

    public function save(SavePartnerApplicationRequest $request, int $id, SavePartnerApplication $action): JsonResponse
    {
        $dto = SavePartnerApplicationData::from($request, $id);
        $application = $action->execute($dto);

        return response()->json([
            'data' => PartnerApplicationResource::make($application),
            'message' => 'Lưu thông tin hồ sơ thành công.',
        ]);
    }

    public function completeness(Request $request, int $id): JsonResponse
    {
        $application = $this->kycRepo->findById($id);

        if (! $application || $application->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Không tìm thấy hồ sơ đăng ký đối tác.'], 404);
        }

        $evaluation = PartnerCompletenessPolicy::evaluate($application);

        return response()->json(['data' => $evaluation]);
    }

    public function submit(SubmitPartnerApplicationRequest $request, int $id, SubmitPartnerApplication $action): JsonResponse
    {
        $dto = SubmitPartnerApplicationData::from($request, $id);
        $application = $action->execute($dto);

        return response()->json([
            'data' => PartnerApplicationResource::make($application),
            'message' => 'Nộp hồ sơ đối tác thành công. Hồ sơ đang được xét duyệt.',
        ]);
    }

    public function history(Request $request, int $id): JsonResponse
    {
        $application = $this->kycRepo->findById($id);

        if (! $application || $application->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Không tìm thấy hồ sơ đăng ký đối tác.'], 404);
        }

        return response()->json([
            'data' => $application->reviewHistories,
        ]);
    }
}
