<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\DisputeRepositoryInterface;
use App\Domain\Admin\Actions\ArbitrateDispute;
use App\Domain\Admin\Actions\GetDisputeQueue;
use App\Domain\Admin\Actions\GetDisputeStats;
use App\Domain\Admin\Actions\NotifyDisputeFiled;
use App\Domain\Admin\DTOs\ArbitrateDisputeData;
use App\Domain\Admin\DTOs\DisputeFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ArbitrateDisputeRequest;
use App\Http\Requests\Admin\ListDisputeRequest;
use App\Http\Requests\Admin\StoreDisputeRequest;
use App\Http\Resources\Admin\DisputeCaseResource;
use App\Models\DisputeCase;
use App\Models\OrganizationMember;
use App\Models\Reservation;
use App\Services\Social\PeerToPeerResaleService;
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
        private readonly NotifyDisputeFiled $notifyDisputeFiled,
        private readonly PeerToPeerResaleService $resale,
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
            'resolve' => 'Đã ban hành phán quyết và giải quyết tranh chấp.',
            'dismiss' => 'Đã bác bỏ khiếu nại và lưu vết kiểm toán.',
            'investigate_further' => 'Đã chuyển trạng thái yêu cầu điều tra bổ sung.',
        ];

        return response()->json([
            'message' => $messages[$dto->decision] ?? 'Đã cập nhật kết quả phán quyết.',
            'data' => DisputeCaseResource::make($updated),
        ]);
    }

    public function store(StoreDisputeRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $reservation = null;
        if (! empty($data['reservation_id'])) {
            $reservation = Reservation::query()->withoutGlobalScope('tenant_isolation')
                ->with(['court.venue.organization', 'slots', 'legacyMapping' => fn ($q) => $q->withoutGlobalScope('tenant_isolation')])
                ->findOrFail($data['reservation_id']);

            // Party check: the reservation owner or an entitlement holder on it
            $isParty = (int) $reservation->user_id === (int) $user->id
                || $reservation->entitlements()->where('user_id', $user->id)->exists();
            abort_unless($isParty, 403, 'Unauthorized booking party.');
            // Filing window check: If the reservation ended > 30 days ago, abort 422
            $endedAt = $reservation->ends_at ?? $reservation->created_at;
            if ($endedAt && $endedAt->isBefore(now()->subDays(30))) {
                abort(422, 'Filing window has expired for this booking.');
            }

            // Duplicate check: If an open dispute already exists for this reservation and complainant, reject with 409
            $existingDispute = DisputeCase::query()
                ->where('reservation_id', $reservation->id)
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

        $disputeCode = 'DSP-'.strtoupper(Str::random(10));
        $court = $reservation?->court ?? (($data['reference_type'] ?? null) === 'marketplace_listing' ? $this->resale->disputeCourt((int) ($data['reference_id'] ?? 0), $user) : null);
        $venue = $court?->venue;
        $org = $venue?->organization;

        $dispute = DisputeCase::create([
            'dispute_code' => $disputeCode,
            'organization_id' => $org?->id,
            'venue_id' => $venue?->id,
            'booking_id' => $reservation?->legacyMapping->value('legacy_booking_id'),
            'reservation_id' => $reservation?->id,
            'reference_type' => $reservation ? 'reservation' : ($data['reference_type'] ?? 'booking'),
            'reference_id' => $reservation ? $reservation->id : ($data['reference_id'] ?? null),
            'court_name' => $court?->name ?? 'N/A',
            'facility_name' => $venue?->name ?? 'N/A',
            'facility_operator_name' => $org?->name,
            'dispute_type' => $data['dispute_type'],
            'severity' => $severity,
            'title' => $data['title'],
            'summary' => $data['summary'],
            'status' => $status,
            'sla_deadline' => $slaDeadline,
            'amount_disputed' => (int) ($data['amount_disputed'] ?? $reservation?->total_amount ?? 0),
            'currency' => 'VND',
            'complainant_id' => $user->id,
            'complainant_name' => $user->name,
            'complainant_phone' => $user->phone,
            'complainant_reputation_score' => 100,
            'respondent_id' => $org ? $this->disputeRepository->findRespondentIdForOrganization($org->id) : null,
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

        $this->notifyDisputeFiled->execute($dispute);

        return response()->json([
            'message' => 'Dispute filed successfully.',
            'data' => DisputeCaseResource::make($dispute->setRelation('reservation', $reservation)),
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
        $isRespondent = $this->disputeRepository->isRespondent($dispute, $user);
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

        $isParty = ($user->id === $dispute->complainant_id || $this->disputeRepository->isRespondent($dispute, $user));
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
