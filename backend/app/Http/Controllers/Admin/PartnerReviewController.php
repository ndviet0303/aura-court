<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\HostKycRepositoryInterface;
use App\Domain\Partner\Actions\ApprovePartnerApplication;
use App\Domain\Partner\Actions\ClearDuplicateIdentityInvestigation;
use App\Domain\Partner\Actions\RestorePartner;
use App\Domain\Partner\Actions\ReviewPartnerApplication;
use App\Domain\Partner\Actions\SuspendPartner;
use App\Domain\Partner\Actions\TerminatePartner;
use App\Domain\Partner\DTOs\ReviewPartnerApplicationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\ReviewPartnerApplicationRequest;
use App\Http\Resources\Partner\PartnerApplicationResource;
use App\Models\HostKyc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerReviewController extends Controller
{
    public function __construct(
        private readonly HostKycRepositoryInterface $kycRepo,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = HostKyc::query()->with(['user', 'organization', 'reviewer']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('business_type')) {
            $query->where('business_type', $request->input('business_type'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('kyc_code', 'like', $search)
                    ->orWhere('business_name', 'like', $search)
                    ->orWhere('legal_name', 'like', $search)
                    ->orWhere('representative_name', 'like', $search)
                    ->orWhere('contact_email', 'like', $search)
                    ->orWhere('contact_phone', 'like', $search);
            });
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $paginator = $query->latest('id')->paginate($perPage);

        return response()->json([
            'data' => PartnerApplicationResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $application = $this->kycRepo->findById($id);
        if (!$application) {
            return response()->json(['message' => 'Hồ sơ đối tác không tồn tại.'], 404);
        }

        $application->load(['user', 'reviewer', 'organization', 'documents', 'consents', 'reviewHistories.reviewer']);

        return response()->json([
            'data' => PartnerApplicationResource::make($application),
        ]);
    }

    public function review(
        ReviewPartnerApplicationRequest $request,
        int $id,
        ReviewPartnerApplication $reviewAction,
        ApprovePartnerApplication $approveAction
    ): JsonResponse {
        $dto = ReviewPartnerApplicationData::fromRequest($request, $id);

        if ($dto->decision === 'approve') {
            $result = $approveAction->execute($dto);
        } else {
            $result = $reviewAction->execute($dto);
        }

        return response()->json([
            'data' => PartnerApplicationResource::make($result),
            'message' => match ($dto->decision) {
                'approve' => 'Phê duyệt hồ sơ đối tác và khởi tạo câu lạc bộ thành công.',
                'revision_required' => 'Đã gửi yêu cầu bổ sung/sửa đổi thông tin đến đối tác.',
                'reject' => 'Đã từ chối hồ sơ đối tác.',
            },
        ]);
    }

    public function clearInvestigation(
        Request $request,
        int $id,
        ClearDuplicateIdentityInvestigation $action
    ): JsonResponse {
        $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $updated = $action->execute(
            kycId: $id,
            adminUserId: $request->user()->id,
            clearanceNotes: $request->input('notes')
        );

        return response()->json([
            'data' => PartnerApplicationResource::make($updated),
            'message' => 'Đã gỡ cờ nghi vấn trùng lặp định danh cho hồ sơ.',
        ]);
    }

    public function suspend(
        Request $request,
        int $organizationId,
        SuspendPartner $action
    ): JsonResponse {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $org = $action->execute(
            organizationId: $organizationId,
            actorId: $request->user()->id,
            reason: $request->string('reason')
        );

        return response()->json([
            'data' => [
                'id' => $org->id,
                'name' => $org->name,
                'partner_status' => $org->partner_status,
                'partner_status_reason' => $org->partner_status_reason,
                'partner_status_changed_at' => $org->partner_status_changed_at?->toIso8601String(),
            ],
            'message' => 'Đã tạm ngừng hoạt động đối tác.',
        ]);
    }

    public function restore(
        Request $request,
        int $organizationId,
        RestorePartner $action
    ): JsonResponse {
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $result = $action->execute(
            organizationId: $organizationId,
            actorId: $request->user()->id,
            notes: $request->input('notes')
        );

        return response()->json([
            'data' => [
                'id' => $result['organization']->id,
                'name' => $result['organization']->name,
                'partner_status' => $result['organization']->partner_status,
                'has_valid_entitlement' => $result['has_valid_entitlement'],
                'new_bookings_enabled' => $result['new_bookings_enabled'],
            ],
            'message' => 'Đã khôi phục hoạt động đối tác.',
        ]);
    }

    public function terminate(
        Request $request,
        int $organizationId,
        TerminatePartner $action
    ): JsonResponse {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $org = $action->execute(
            organizationId: $organizationId,
            actorId: $request->user()->id,
            reason: $request->string('reason')
        );

        return response()->json([
            'data' => [
                'id' => $org->id,
                'name' => $org->name,
                'partner_status' => $org->partner_status,
                'partner_status_reason' => $org->partner_status_reason,
            ],
            'message' => 'Đã chấm dứt hợp đồng hợp tác đối tác.',
        ]);
    }
}
