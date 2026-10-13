<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Resources\MatchPostResource;
use App\Models\Court;
use App\Models\MatchInvitation;
use App\Models\MatchPost;
use App\Models\OrganizationMember;
use App\Models\PlayerProfile;
use App\Models\ReputationDispute;
use App\Models\User;
use App\Services\Infrastructure\IdempotencyService;
use App\Services\Social\MatchService;
use App\Services\Social\ReputationService;
use Illuminate\Http\Request;

class SocialController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private readonly MatchService $matches, private readonly ReputationService $reputation, private readonly IdempotencyService $idempotency) {}

    public function profile(Request $request)
    {
        $organization = $this->organization($request);

        return response()->json(PlayerProfile::firstOrCreate(['organization_id' => $organization->id, 'user_id' => $request->user()->id], ['skill_level' => 1, 'reputation_score' => 60]));
    }

    public function updateProfile(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate(['skill_level' => 'required|integer|min:1|max:5', 'city' => 'nullable|string|max:120', 'latitude' => 'nullable|numeric|between:-90,90', 'longitude' => 'nullable|numeric|between:-180,180', 'location_precision_km' => 'nullable|integer|min:1|max:50', 'availability' => 'nullable|array', 'preferred_positions' => 'nullable|array', 'visibility' => 'nullable|in:organization,private']);

        return $this->idempotency->run($request, fn () => response()->json(['profile' => $this->matches->profile($request->user(), $organization->id, $data)]));
    }

    public function index(Request $request)
    {
        $organization = null;
        try {
            $organization = $this->organization($request);
        } catch (\Throwable $e) {
            $organization = null;
        }

        $explicitOrgId = $request->header('X-Organization-Id')
            ?? $request->header('X-Organization-ID')
            ?? $request->input('organization_id');

        $orgId = $explicitOrgId ? (int) $explicitOrgId : $organization?->id;

        return response()->json(['matches' => MatchPostResource::collection($this->matches->discover($orgId, $request->all()))]);
    }

    public function show(Request $request, MatchPost $post)
    {
        return response()->json(['match' => MatchPostResource::make($this->matches->show($post, $request->user()))]);
    }

    public function create(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'court_id' => 'nullable|integer',
            'reservation_id' => 'nullable|integer',
            'entitlement_id' => 'nullable|integer',
            'is_owner_hosted' => 'nullable|boolean',
            'category' => 'nullable|string|max:64',
            'skill_levels' => 'nullable|array',
            'skill_levels.*' => 'string',
            'title' => 'required|string|max:180',
            'visibility' => 'nullable|in:public,invite_only',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date',
            'registration_deadline' => 'nullable|date',
            'approval_required' => 'nullable|boolean',
            'city' => 'nullable|string|max:120',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'min_skill' => 'nullable|integer|min:1|max:5',
            'max_skill' => 'nullable|integer|min:1|max:5',
            'required_participants' => 'required|integer|min:1|max:100',
            'availability' => 'nullable|array',
            'metadata' => 'nullable|array',
        ]);
        $data['visibility'] = $data['visibility'] ?? 'public';

        return $this->idempotency->run($request, fn () => response()->json(['match' => MatchPostResource::make($this->matches->createPost($request->user(), $organization->id, $data))], 201));
    }

    public function update(Request $request, MatchPost $post)
    {
        $data = $request->validate([
            'court_id' => 'nullable|integer',
            'title' => 'sometimes|string|max:180',
            'visibility' => 'sometimes|in:public,invite_only',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'sometimes|date',
            'registration_deadline' => 'nullable|date',
            'required_participants' => 'sometimes|integer|min:1|max:100',
            'category' => 'sometimes|string|max:64',
            'skill_levels' => 'sometimes|array',
            'skill_levels.*' => 'string',
            'availability' => 'nullable|array',
            'metadata' => 'nullable|array',
        ]);

        return $this->idempotency->run($request, fn () => response()->json(['match' => MatchPostResource::make($this->matches->updatePost($post, $request->user(), $data))]));
    }

    public function endSession(Request $request, MatchPost $post)
    {
        return $this->idempotency->run($request, fn () => response()->json(['match' => MatchPostResource::make($this->matches->endSession($post, $request->user()))]));
    }

    public function getBill(Request $request, MatchPost $post)
    {
        return response()->json(['bill' => $this->matches->getBill($post, $request->user())]);
    }

    public function saveDraftBill(Request $request, MatchPost $post)
    {
        return $this->idempotency->run($request, fn () => response()->json(['bill' => $this->matches->saveDraftBill($post, $request->user(), $request->all())]));
    }

    public function finalizeBill(Request $request, MatchPost $post)
    {
        return $this->idempotency->run($request, fn () => response()->json(['bill' => $this->matches->finalizeBill($post, $request->user(), $request->all())]));
    }

    public function getDefaultRates(Request $request, MatchPost $post)
    {
        $court = $post->court ?? ($post->court_id ? Court::find($post->court_id) : null);
        abort_unless($court, 404, 'Không tìm thấy sân.');

        return response()->json(['rates' => $this->matches->getDefaultRates($court, $post->starts_at)]);
    }

    public function invite(Request $request, MatchPost $post)
    {
        $organization = $this->organization($request);
        abort_unless($post->organization_id === $organization->id, 404);
        $data = $request->validate([
            'invitee_id' => 'nullable|integer',
            'invitee_ids' => 'nullable|array',
            'invitee_ids.*' => 'integer',
        ]);

        return $this->idempotency->run($request, function () use ($post, $request, $data) {
            if (! empty($data['invitee_ids'])) {
                $batch = $this->matches->inviteMany($post, $request->user(), $data['invitee_ids']);
                $first = $batch['invitations'][0] ?? null;

                return response()->json(array_merge($batch, ['invitation' => $first]), 201);
            }

            abort_unless(! empty($data['invitee_id']), 422, 'invitee_id or invitee_ids is required.');
            $invitation = $this->matches->invite($post, $request->user(), (int) $data['invitee_id']);

            return response()->json([
                'invitation' => $invitation,
                'results' => [
                    ['invitee_id' => (int) $data['invitee_id'], 'status' => 'invited', 'invitation_id' => $invitation->id],
                ],
                'summary' => [
                    'total' => 1,
                    'invited' => 1,
                    'failed' => 0,
                ],
            ], 201);
        });
    }

    public function join(Request $request, MatchPost $post)
    {
        $organization = null;
        try {
            $organization = $this->organization($request);
        } catch (\Throwable $e) {
            $organization = $post->organization;
        }

        if (! $organization) {
            $organization = $post->organization;
        }

        abort_unless($post->organization_id === $organization?->id, 404);

        if (! $organization->hasMember($request->user()->id)) {
            OrganizationMember::firstOrCreate(
                ['organization_id' => $organization->id, 'user_id' => $request->user()->id],
                ['role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']
            );
        }

        return $this->idempotency->run($request, fn () => response()->json(['participation' => $this->matches->join($post, $request->user())], 201));
    }

    public function approveParticipant(Request $request, MatchPost $match)
    {
        $data = $request->validate(['user_id' => 'required|integer']);

        return $this->idempotency->run($request, fn () => response()->json([
            'participation' => $this->matches->approveParticipant($match, $request->user(), (int) $data['user_id']),
            'message' => 'Participant approved.',
        ]));
    }

    public function rejectParticipant(Request $request, MatchPost $match)
    {
        $data = $request->validate(['user_id' => 'required|integer']);

        return $this->idempotency->run($request, fn () => response()->json([
            'participation' => $this->matches->rejectParticipant($match, $request->user(), (int) $data['user_id']),
            'message' => 'Participant rejected.',
        ]));
    }

    public function closeRoster(Request $request, MatchPost $match)
    {
        return $this->idempotency->run($request, fn () => response()->json([
            'match' => MatchPostResource::make($this->matches->closeRoster($match, $request->user())),
            'message' => 'Roster closed.',
        ]));
    }

    public function recordAttendance(Request $request, MatchPost $match)
    {
        $data = $request->validate([
            'user_id' => 'required|integer',
            'status' => 'required|in:present,late,absent,withdrawn',
            'notes' => 'nullable|string|max:1000',
        ]);

        return $this->idempotency->run($request, fn () => response()->json([
            'participation' => $this->matches->recordAttendance($match, $request->user(), (int) $data['user_id'], $data['status'], $data['notes'] ?? null),
            'message' => 'Attendance recorded.',
        ]));
    }

    public function respond(Request $request, MatchInvitation $invitation)
    {
        $organization = $this->organization($request);
        abort_unless($invitation->organization_id === $organization->id, 404);
        $data = $request->validate(['status' => 'required|in:accepted,declined']);

        return $this->idempotency->run($request, fn () => response()->json(['invitation' => $this->matches->respond($invitation, $request->user(), $data['status'])]));
    }

    public function recordActivity(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate(['user_id' => 'required|integer', 'type' => 'required|in:attendance,late_cancellation,no_show', 'source_type' => 'required|string|max:120', 'source_id' => 'required|integer']);

        return $this->idempotency->run($request, fn () => response()->json(['profile' => $this->reputation->record(User::findOrFail($data['user_id']), $organization->id, $data['type'], $data['source_type'], $data['source_id'], $request->user()->id)]));
    }

    public function fileDispute(Request $request, int $activity)
    {
        $organization = $this->organization($request);
        $data = $request->validate(['reason' => 'required|string|max:2000']);

        return $this->idempotency->run($request, fn () => response()->json(['dispute' => $this->reputation->fileDispute($request->user(), $organization->id, $activity, $data['reason'])], 201));
    }

    public function resolveDispute(Request $request, ReputationDispute $dispute)
    {
        $organization = $this->organization($request);
        abort_unless($dispute->organization_id === $organization->id, 404);
        $data = $request->validate(['decision' => 'required|in:upheld,rejected', 'resolution' => 'required|string|max:2000']);

        return $this->idempotency->run($request, fn () => response()->json(['dispute' => $this->reputation->resolveDispute($dispute, $request->user(), $data['decision'], $data['resolution'])]));
    }
}
