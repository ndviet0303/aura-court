<?php

namespace App\Http\Controllers\Partner;

use App\Contracts\Repositories\VenueSuspensionRepositoryInterface;
use App\Domain\Venue\Actions\SubmitSuspensionRemediation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\ReadVenueSuspensionsRequest;
use App\Http\Requests\Partner\SubmitRemediationRequest;
use App\Http\Resources\Venue\SuspensionRemediationResource;
use App\Http\Resources\Venue\VenueSuspensionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** D13/D14: the owner reads the venue's suspension decisions and submits remediation. */
class VenueSuspensionController extends Controller
{
    public function __construct(private readonly VenueSuspensionRepositoryInterface $suspensions) {}

    public function index(ReadVenueSuspensionsRequest $request, int $organizationId, int $venueId): AnonymousResourceCollection
    {
        $this->assertVenueInOrganization($organizationId, $venueId);

        return VenueSuspensionResource::collection($this->suspensions->forVenue($venueId));
    }

    public function show(ReadVenueSuspensionsRequest $request, int $organizationId, int $venueId, int $suspensionId): VenueSuspensionResource
    {
        $this->assertVenueInOrganization($organizationId, $venueId);
        $suspension = $this->suspensions->detail($suspensionId);
        abort_unless((int) $suspension->venue_id === $venueId, 404, 'Quyết định đình chỉ không thuộc cơ sở này.');

        return new VenueSuspensionResource($suspension);
    }

    public function storeRemediation(SubmitRemediationRequest $request, int $organizationId, int $venueId, int $suspensionId, SubmitSuspensionRemediation $action): JsonResponse
    {
        $remediation = $action->execute($request->user(), $organizationId, $venueId, $suspensionId, $request->validated('content'), $request->file('files', []));

        return response()->json([
            'message' => 'Đã gửi yêu cầu khắc phục. Quản trị viên nền tảng sẽ xem xét và phản hồi.',
            'data' => new SuspensionRemediationResource($remediation->load('submitter:id,name')),
        ], 201);
    }

    private function assertVenueInOrganization(int $organizationId, int $venueId): void
    {
        abort_unless((int) $this->suspensions->venue($venueId)->organization_id === $organizationId, 404, 'Cơ sở thể thao không tồn tại.');
    }
}
