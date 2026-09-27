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
use App\Models\Booking;
use App\Models\DisputeCase;
use App\Models\OrganizationMember;
use App\Models\OwnerRefundObligation;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Services\Infrastructure\OutboxService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class AdminDisputeController extends Controller
{
    public function __construct(
        private readonly GetDisputeQueue $getQueue,
        private readonly GetDisputeStats $getStats,
        private readonly ArbitrateDispute $arbitrateDispute,
        private readonly DisputeRepositoryInterface $disputeRepository,
        private readonly OutboxService $outbox,
    ) {}

    public function index(ListDisputeRequest $request): AnonymousResourceCollection
    {
        $filter = DisputeFilterData::fromRequest($request);
        $user = $request->user();
        $orgId = $request->filled('organization_id') ? (int) $request->input('organization_id') : null;
        $venueId = $request->filled('venue_id') ? (int) $request->input('venue_id') : null;

        $records = $this->getQueue->execute($filter, $user, $orgId, $venueId);

        return DisputeCaseResource::collection($records);
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $orgId = $request->filled('organization_id') ? (int) $request->input('organization_id') : null;
        $stats = $this->getStats->execute($user, $orgId);

        return response()->json(['stats' => $stats]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $record = $this->disputeRepository->findById($id);

        if (! $record || ($user && ! $this->disputeRepository->userCanAccess($record, $user))) {
            return response()->json(['message' => 'Hồ sơ tranh chấp không tồn tại.'], 404);
        }

        return response()->json(['data' => DisputeCaseResource::make($record)]);
    }

    public function arbitrate(ArbitrateDisputeRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $record = $this->disputeRepository->findById($id);

        if (! $record || ($user && ! $this->disputeRepository->userCanAccess($record, $user))) {
            return response()->json(['message' => 'Hồ sơ tranh chấp không tồn tại.'], 404);
        }
        if ($user && ((int) $user->id === (int) $record->complainant_id || (int) $user->id === (int) $record->respondent_id)) {
            abort(422, 'Conflict of interest: Reviewer cannot arbitrate a dispute in which they are an involved party.');
        }

        // Only platform admin or authorized organization owner of this dispute can arbitrate
        if ($user && ! $user->isPlatformAdmin()) {
            $isAuthorizedOwner = $record->organization_id
                && $user->hasRoleInOrganization($record->organization_id, [OrganizationMember::ROLE_ORGANIZATION_OWNER]);
            abort_unless($isAuthorizedOwner, 403, 'Bạn không có quyền phán quyết tranh chấp này.');
        }

        $dto = ArbitrateDisputeData::fromRequest($request, $id);
        $updated = $this->arbitrateDispute->execute($dto);

        $messages = [
            'resolve_and_refund' => 'Đã ban hành phán quyết và tạo nghĩa vụ hoàn tiền chờ chủ cơ sở thực hiện.',
            'resolve' => 'Đã ban hành phán quyết và giải quyết tranh chấp.',
            'dismiss' => 'Đã bác bỏ khiếu nại và lưu vết kiểm toán.',
            'investigate_further' => 'Đã chuyển trạng thái yêu cầu điều tra bổ sung.',
        ];

        return response()->json([
            'message' => $messages[$dto->decision] ?? 'Đã cập nhật kết quả phán quyết.',
            'data' => DisputeCaseResource::make($updated),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'title' => 'required|string|max:190',
            'summary' => 'required|string|min:10',
            'dispute_type' => 'required|in:booking_cancellation,court_quality,payment_issue,no_show_conflict,other',
            'severity' => 'nullable|in:low,medium,high,urgent',
            'booking_id' => 'nullable|integer|exists:bookings,id',
            'evidence' => 'nullable|array',
            'amount_disputed' => 'nullable|integer|min:0',
            'reference_type' => 'nullable|string|max:32',
            'reference_id' => 'nullable|integer',
        ]);

        $booking = null;
        if (! empty($data['booking_id'])) {
            $booking = Booking::with(['court.venue.organization', 'slots'])->findOrFail($data['booking_id']);

            // Party check
            $isBookingOwner = (int) $booking->user_id === (int) $user->id;
            $hasEntitlement = false;
            if (! $isBookingOwner) {
                $reservationIds = \App\Models\LegacyBookingMapping::where('legacy_booking_id', $booking->id)->pluck('reservation_id');
                $hasEntitlement = ReservationEntitlement::query()
                    ->where('user_id', $user->id)
                    ->whereIn('reservation_id', $reservationIds)
                    ->exists();
            }

            abort_unless($isBookingOwner || $hasEntitlement, 403, 'Unauthorized booking party.');
            // Filing window check: If booking ended > 30 days ago, abort 422
            $latestSlotEnd = $booking->slots->max('ends_at');
            $bookingEndedAt = $latestSlotEnd ? Carbon::parse($latestSlotEnd) : $booking->created_at;
            if ($bookingEndedAt && $bookingEndedAt->isBefore(now()->subDays(30))) {
                abort(422, 'Filing window has expired for this booking.');
            }

            // Duplicate check: If an open dispute already exists for this booking_id and complainant, reject with 409
            $existingDispute = DisputeCase::query()
                ->where('booking_id', $booking->id)
                ->where('complainant_id', $user->id)
                ->whereIn('status', ['pending', 'investigating', 'awaiting_evidence', 'awaiting_response'])
                ->first();

            if ($existingDispute) {
                return response()->json([
                    'message' => 'A dispute is already active for this booking.',
                    'dispute_id' => $existingDispute->id,
                    'existing_dispute_id' => $existingDispute->id,
                ], 409);
            }
        }

        $severity = $data['severity'] ?? 'medium';
        if (! $severity) {
            $severity = 'medium';
        }
        $evidence = $data['evidence'] ?? [];
        $isEvidenceEmpty = empty($evidence);
        $requiresEvidence = in_array($severity, ['high', 'urgent'], true) || $data['dispute_type'] === 'court_quality';

        $status = 'pending';
        $slaDeadline = null;
        $requestedEvidence = null;

        if ($isEvidenceEmpty && $requiresEvidence) {
            $status = 'awaiting_evidence';
            $slaDeadline = now()->addDays(3);
            $requestedEvidence = ['photos_or_video', 'detailed_statement'];
        }

        $disputeCode = 'DSP-' . strtoupper(Str::random(10));
        $court = $booking?->court;
        $venue = $court?->venue;
        $org = $venue?->organization;

        $dispute = DisputeCase::create([
            'dispute_code' => $disputeCode,
            'organization_id' => $org?->id,
            'venue_id' => $venue?->id,
            'booking_id' => $booking?->id,
            'reference_type' => $booking ? 'booking' : ($data['reference_type'] ?? 'booking'),
            'reference_id' => $booking ? $booking->id : ($data['reference_id'] ?? null),
            'court_name' => $court?->name ?? 'N/A',
            'facility_name' => $venue?->name ?? 'N/A',
            'facility_operator_name' => $org?->name,
            'dispute_type' => $data['dispute_type'],
            'severity' => $severity,
            'title' => $data['title'],
            'summary' => $data['summary'],
            'status' => $status,
            'sla_deadline' => $slaDeadline,
            'amount_disputed' => (int) ($data['amount_disputed'] ?? $booking?->total_amount ?? 0),
            'currency' => 'VND',
            'complainant_id' => $user->id,
            'complainant_name' => $user->name,
            'complainant_phone' => $user->phone,
            'complainant_reputation_score' => 100,
            'respondent_id' => $org?->user_id,
            'respondent_name' => $org?->name ?? 'Venue Partner',
            'respondent_reputation_score' => 100,
            'evidence' => $evidence ?: [],
            'requested_evidence' => $requestedEvidence,
            'timeline_events' => [
                [
                    'event' => 'dispute_filed',
                    'actor_id' => $user->id,
                    'actor_name' => $user->name,
                    'status' => $status,
                    'timestamp' => now()->toIso8601String(),
                ],
            ],
        ]);

        if ($status === 'awaiting_evidence') {
            $this->outbox->enqueue([
                'organization_id' => $dispute->organization_id,
                'message_key' => "dispute_evidence_requested:{$dispute->id}:" . now()->timestamp,
                'channel' => 'notification',
                'event_type' => 'dispute.evidence_requested',
                'aggregate_type' => DisputeCase::class,
                'aggregate_id' => $dispute->id,
            ], [
                'user_id' => $user->id,
                'recipient_id' => $user->id,
                'dispute_id' => $dispute->id,
                'dispute_code' => $dispute->dispute_code,
                'requested_evidence' => $requestedEvidence,
                'sla_deadline' => $slaDeadline->toIso8601String(),
                'title' => 'Yêu cầu bổ sung chứng cứ',
                'body' => 'Hồ sơ tranh chấp của bạn cần bổ sung chứng cứ xác thực.',
                'message' => 'Hồ sơ tranh chấp cần bổ sung tài liệu minh chứng trước thời hạn.',
            ]);
        }

        return response()->json([
            'message' => 'Dispute filed successfully.',
            'data' => DisputeCaseResource::make($dispute),
        ], 201);
    }

    public function submitEvidence(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $dispute = $this->disputeRepository->findById($id);

        if (! $dispute) {
            return response()->json(['message' => 'Hồ sơ tranh chấp không tồn tại.'], 404);
        }

        $isComplainant = (int) $user->id === (int) $dispute->complainant_id;
        $isRespondent = (int) $user->id === (int) $dispute->respondent_id;
        $isAdmin = $user->isPlatformAdmin();
        abort_unless($isComplainant || $isRespondent || $isAdmin, 403, 'Chỉ các bên liên quan mới có thể cung cấp chứng cứ.');

        $data = $request->validate([
            'explanation' => 'nullable|string',
            'evidence' => 'required|array',
            'is_private' => 'nullable|boolean',
        ]);

        $evidence = $dispute->evidence ?? [];
        $evidence[] = [
            'submitted_by' => $user->id,
            'submitted_by_name' => $user->name,
            'is_private' => (bool) ($data['is_private'] ?? false),
            'explanation' => $data['explanation'] ?? null,
            'items' => $data['evidence'],
            'submitted_at' => now()->toIso8601String(),
        ];

        $events = $dispute->timeline_events ?? [];
        $events[] = [
            'event' => 'evidence_submitted',
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'is_private' => (bool) ($data['is_private'] ?? false),
            'has_explanation' => ! empty($data['explanation']),
            'timestamp' => now()->toIso8601String(),
        ];

        $newStatus = $dispute->status;
        if ($dispute->status === 'awaiting_evidence') {
            $newStatus = 'investigating';
        }

        $dispute->update([
            'evidence' => $evidence,
            'timeline_events' => $events,
            'status' => $newStatus,
        ]);

        return response()->json([
            'message' => 'Evidence submitted successfully.',
            'data' => DisputeCaseResource::make($dispute->fresh()),
        ]);
    }

    public function reassign(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $record = $this->disputeRepository->findById($id);

        if (! $record || ($user && ! $this->disputeRepository->userCanAccess($record, $user))) {
            return response()->json(['message' => 'Hồ sơ tranh chấp không tồn tại.'], 404);
        }

        if ($user && ! $user->isPlatformAdmin()) {
            $isAuthorizedOwner = $record->organization_id
                && $user->hasRoleInOrganization($record->organization_id, [OrganizationMember::ROLE_ORGANIZATION_OWNER]);
            abort_unless($isAuthorizedOwner, 403, 'Bạn không có quyền chuyển người xử lý tranh chấp này.');
        }

        $data = $request->validate([
            'new_reviewer_id' => 'required|exists:users,id',
            'reason' => 'required|string|min:5',
        ]);

        $events = $record->timeline_events ?? [];
        $events[] = [
            'event' => 'reviewer_reassigned',
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'previous_reviewer_id' => $record->assigned_reviewer_id,
            'new_reviewer_id' => (int) $data['new_reviewer_id'],
            'reason' => $data['reason'],
            'timestamp' => now()->toIso8601String(),
        ];

        $record->update([
            'assigned_reviewer_id' => $data['new_reviewer_id'],
            'conflict_reassigned_at' => now(),
            'conflict_reassigned_by' => $user->id,
            'reassignment_reason' => $data['reason'],
            'timeline_events' => $events,
        ]);

        return response()->json([
            'message' => 'Reviewer reassigned successfully.',
            'data' => DisputeCaseResource::make($record->fresh()),
        ]);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $dispute = $this->disputeRepository->findById($id);

        if (! $dispute || ($user && ! $this->disputeRepository->userCanAccess($dispute, $user))) {
            return response()->json(['message' => 'Hồ sơ tranh chấp không tồn tại.'], 404);
        }

        if ($user && ! $user->isPlatformAdmin()) {
            $isAuthorizedOwner = $dispute->organization_id
                && $user->hasRoleInOrganization($dispute->organization_id, [OrganizationMember::ROLE_ORGANIZATION_OWNER]);
            abort_unless($isAuthorizedOwner, 403, 'Bạn không có quyền đóng tranh chấp này.');
        }

        if ($dispute->refund_obligation_id) {
            $obligation = OwnerRefundObligation::find($dispute->refund_obligation_id);
            if ($obligation && ! in_array($obligation->status, ['acknowledged', 'rejected'], true)) {
                abort(409, 'Cannot close dispute: pending refund obligation must be executed or settled first.');
            }
        }

        $events = $dispute->timeline_events ?? [];
        $events[] = [
            'event' => 'dispute_closed',
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'timestamp' => now()->toIso8601String(),
        ];

        $dispute->update([
            'status' => 'closed',
            'timeline_events' => $events,
        ]);

        return response()->json([
            'message' => 'Dispute closed successfully.',
            'data' => DisputeCaseResource::make($dispute->fresh()),
        ]);
    }

    public function reopen(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $dispute = $this->disputeRepository->findById($id);

        if (! $dispute || ($user && ! $this->disputeRepository->userCanAccess($dispute, $user))) {
            return response()->json(['message' => 'Hồ sơ tranh chấp không tồn tại.'], 404);
        }

        $isParty = ($user->id === $dispute->complainant_id || $user->id === $dispute->respondent_id);
        $isAdmin = $user->isPlatformAdmin();
        abort_unless($isParty || $isAdmin, 403, 'Unauthorized to reopen dispute.');

        abort_unless(in_array($dispute->status, ['resolved', 'dismissed', 'closed'], true), 422, 'Only resolved, dismissed, or closed disputes can be reopened.');

        $data = $request->validate([
            'reason' => 'required|string|min:5',
            'material_evidence' => 'required|array|min:1',
        ]);

        $history = $dispute->decision_history ?? [];
        $history[] = [
            'version' => (int) ($dispute->decision_version ?? 1),
            'reviewer_id' => $dispute->arbitrated_by,
            'decision' => $dispute->resolution_decision,
            'notes' => $dispute->arbitrator_notes,
            'refund_amount' => (int) ($dispute->refund_amount ?? 0),
            'status_before_reopen' => $dispute->status,
            'arbitrated_at' => $dispute->arbitrated_at ? $dispute->arbitrated_at->toIso8601String() : null,
        ];

        $evidence = $dispute->evidence ?? [];
        $evidence[] = [
            'submitted_by' => $user->id,
            'submitted_by_name' => $user->name,
            'type' => 'reopen_material_evidence',
            'reason' => $data['reason'],
            'items' => $data['material_evidence'],
            'submitted_at' => now()->toIso8601String(),
        ];

        $events = $dispute->timeline_events ?? [];
        $events[] = [
            'event' => 'dispute_reopened',
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'reason' => $data['reason'],
            'timestamp' => now()->toIso8601String(),
        ];

        $dispute->update([
            'decision_history' => $history,
            'decision_version' => (int) ($dispute->decision_version ?? 1) + 1,
            'status' => 'reopened',
            'reopened_at' => now(),
            'reopened_by' => $user->id,
            'reopen_reason' => $data['reason'],
            'evidence' => $evidence,
            'timeline_events' => $events,
        ]);

        return response()->json([
            'message' => 'Dispute reopened successfully.',
            'data' => DisputeCaseResource::make($dispute->fresh()),
        ]);
    }
}
