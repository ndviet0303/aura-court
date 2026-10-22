<?php

namespace App\Http\Controllers\Partner;

use App\Contracts\Repositories\VenueLifecycleRepositoryInterface;
use App\Domain\Venue\Actions\CreateDraftVenue;
use App\Domain\Venue\Actions\UpdateDraftVenue;
use App\Domain\Venue\DTOs\CreateDraftVenueData;
use App\Http\Controllers\Concerns\AuthorizesVenueRead;
use App\Http\Controllers\Controller;
use App\Http\Requests\Venue\SaveVenueRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerVenueController extends Controller
{
    use AuthorizesVenueRead;

    public function __construct(
        private readonly VenueLifecycleRepositoryInterface $venueRepo,
    ) {}

    public function index(Request $request, int $organizationId): JsonResponse
    {
        $user = $request->user();
        $this->authorizeOrganizationRead($request, $organizationId);
        // BR-20: the owner lists every venue of the organization, staff only the venues assigned to them.
        $paginator = $this->venueRepo->paginateByOrganization($organizationId, $user->getAuthorizedVenueIds($organizationId), 20);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, int $organizationId, int $venueId): JsonResponse
    {
        return response()->json([
            'data' => $this->authorizeVenueRead($request, $organizationId, $venueId),
        ]);
    }

    public function store(
        SaveVenueRequest $request,
        int $organizationId,
        CreateDraftVenue $action
    ): JsonResponse {
        $dto = CreateDraftVenueData::fromRequest($request, $organizationId);
        $venue = $action->execute($dto);

        return response()->json([
            'data' => $venue,
            'message' => 'Tạo cơ sở thể thao thành công. Vui lòng thiết lập giờ hoạt động rồi mở cơ sở để nhận đặt sân.',
        ], 201);
    }

    public function update(
        SaveVenueRequest $request,
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
}
