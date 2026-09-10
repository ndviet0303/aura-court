<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Venue\Actions\TransitionVenueLifecycle;
use App\Contracts\Repositories\VenueLifecycleRepositoryInterface;
use App\Domain\Venue\Actions\ReviewVenuePublication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Venue\ReviewVenuePublicationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminVenuePublicationController extends Controller
{
    public function queue(VenueLifecycleRepositoryInterface $venueRepo): JsonResponse
    {
        $paginator = $venueRepo->paginatePendingPublication(20);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(int $revisionId, VenueLifecycleRepositoryInterface $venueRepo): JsonResponse
    {
        $revision = $venueRepo->findPublicationRevision($revisionId);
        if (!$revision) {
            return response()->json(['message' => 'Yêu cầu xuất bản không tồn tại.'], 404);
        }

        return response()->json([
            'data' => $revision,
        ]);
    }

    public function review(
        ReviewVenuePublicationRequest $request,
        int $revisionId,
        ReviewVenuePublication $action
    ): JsonResponse {
        $updated = $action->execute(
            revisionId: $revisionId,
            reviewerId: (int) $request->user()->id,
            decision: (string) $request->validated('decision'),
            reason: $request->validated('reason'),
            feedbackFields: $request->validated('feedback_fields')
        );

        return response()->json([
            'data' => $updated,
            'message' => match ($updated->status) {
                'approved' => 'Phê duyệt xuất bản cơ sở thể thao thành công. Cơ sở đã hiển thị công khai.',
                'revision_required' => 'Đã gửi yêu cầu chỉnh sửa/bổ sung đến chủ câu lạc bộ.',
                'rejected' => 'Đã từ chối xuất bản cơ sở thể thao.',
            },
        ]);
    }

    public function transitionLifecycle(
        Request $request,
        int $venueId,
        TransitionVenueLifecycle $action
    ): JsonResponse {
        $request->validate([
            'status' => 'required|string|in:published,temporarily_closed,suspended,closed,archived',
            'reason' => 'nullable|string|max:500',
        ]);

        $updated = $action->execute(
            venueId: $venueId,
            actorId: (int) $request->user()->id,
            targetStatus: (string) $request->input('status'),
            reason: $request->input('reason'),
            isPlatformAdmin: true
        );

        return response()->json([
            'data' => $updated,
            'message' => 'Cập nhật trạng thái cơ sở bởi quản trị viên thành công.',
        ]);
    }
}
