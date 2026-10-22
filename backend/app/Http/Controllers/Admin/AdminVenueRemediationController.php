<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\VenueSuspensionRepositoryInterface;
use App\Domain\Venue\Actions\ReviewSuspensionRemediation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListVenueRemediationsRequest;
use App\Http\Requests\Admin\ReviewVenueRemediationRequest;
use App\Http\Resources\Venue\SuspensionRemediationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** D14: the platform admin's remediation queue and review. */
class AdminVenueRemediationController extends Controller
{
    public function __construct(private readonly VenueSuspensionRepositoryInterface $suspensions) {}

    public function index(ListVenueRemediationsRequest $request): AnonymousResourceCollection
    {
        return SuspensionRemediationResource::collection(
            $this->suspensions->paginateRequests($request->validated('status'), (int) $request->validated('per_page', 20))
        );
    }

    /** The submission with its decision and every submission of that decision, oldest first. */
    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->present($id)]);
    }

    public function review(ReviewVenueRemediationRequest $request, int $id, ReviewSuspensionRemediation $action): JsonResponse
    {
        $remediation = $action->execute($request->user(), $id, $request->validated('decision'), $request->validated('feedback'));

        return response()->json([
            'message' => $remediation->status === 'approved' ? 'Đã chấp thuận và gỡ đình chỉ.' : 'Đã ghi nhận phản hồi cho chủ cơ sở.',
            'data' => $this->present($id),
        ]);
    }

    private function present(int $id): SuspensionRemediationResource
    {
        $suspension = $this->suspensions->detail((int) $this->suspensions->remediation($id)->venue_suspension_id);
        // A clone, so the submission inside $suspension->remediations does not point back at its decision (a cycle).
        $remediation = clone $suspension->remediations->firstWhere('id', $id);

        return new SuspensionRemediationResource($remediation->setRelation('suspension', $suspension));
    }
}
