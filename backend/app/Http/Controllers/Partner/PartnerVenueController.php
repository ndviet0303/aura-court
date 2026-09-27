<?php

namespace App\Http\Controllers\Partner;

use App\Contracts\Repositories\VenueLifecycleRepositoryInterface;
use App\Domain\Venue\Actions\CreateDraftVenue;
use App\Domain\Venue\Actions\EvaluateVenueReadiness;
use App\Domain\Venue\Actions\SubmitVenueForPublication;
use App\Domain\Venue\Actions\TransitionVenueLifecycle;
use App\Domain\Venue\Actions\UpdateDraftVenue;
use App\Domain\Venue\DTOs\CreateDraftVenueData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\TransitionVenueLifecycleRequest;
use App\Http\Requests\Partner\UpdateDraftVenueRequest;
use App\Http\Requests\Venue\CreateDraftVenueRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerVenueController extends Controller
{
    public function index(
        Request $request,
        int $organizationId,
        VenueLifecycleRepositoryInterface $venueRepo
    ): JsonResponse {
        $paginator = $venueRepo->paginateByOrganization($organizationId, 20);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(
        Request $request,
        int $organizationId,
        int $venueId,
        VenueLifecycleRepositoryInterface $venueRepo
    ): JsonResponse {
        $venue = $venueRepo->findById($venueId);
        if (! $venue || (int) $venue->organization_id !== $organizationId) {
            return response()->json(['message' => 'Cơ sở thể thao không tồn tại.'], 404);
        }

        return response()->json([
            'data' => $venue,
        ]);
    }

    public function readiness(
        Request $request,
        int $organizationId,
        int $venueId,
        EvaluateVenueReadiness $action
    ): JsonResponse {
        $result = $action->execute($venueId);

        return response()->json([
            'data' => $result,
        ]);
    }

    public function store(
        CreateDraftVenueRequest $request,
        int $organizationId,
        CreateDraftVenue $action
    ): JsonResponse {
        $dto = CreateDraftVenueData::fromRequest($request, $organizationId);
        $venue = $action->execute($dto);

        return response()->json([
            'data' => $venue,
            'message' => 'Tạo bản nháp cơ sở thể thao thành công. Vui lòng thiết lập sân và bảng giá trước khi gửi duyệt xuất bản.',
        ], 201);
    }

    public function update(
        UpdateDraftVenueRequest $request,
        int $organizationId,
        int $venueId,
        UpdateDraftVenue $action
    ): JsonResponse {
        $updated = $action->execute(
            venueId: $venueId,
            actorId: (int) $request->user()->id,
            attributes: $request->validated()
        );

        return response()->json([
            'data' => $updated,
            'message' => 'Cập nhật thông tin cơ sở thể thao thành công.',
        ]);
    }

    public function submitPublication(
        Request $request,
        int $organizationId,
        int $venueId,
        SubmitVenueForPublication $action
    ): JsonResponse {
        $revision = $action->execute($venueId, (int) $request->user()->id);

        return response()->json([
            'data' => $revision,
            'message' => 'Đã gửi cơ sở vào hàng đợi xét duyệt xuất bản thành công.',
        ], 201);
    }

    public function transitionLifecycle(
        TransitionVenueLifecycleRequest $request,
        int $organizationId,
        int $venueId,
        TransitionVenueLifecycle $action
    ): JsonResponse {
        $updated = $action->execute(
            venueId: $venueId,
            actorId: (int) $request->user()->id,
            targetStatus: (string) $request->validated('status'),
            reason: $request->validated('reason'),
            isPlatformAdmin: false
        );

        return response()->json([
            'data' => $updated,
            'message' => 'Cập nhật trạng thái vòng đời cơ sở thành công.',
        ]);
    }
}
