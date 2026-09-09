<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;

use App\Http\Controllers\Concerns\ResolvesV2Organization;
use App\Models\MatchInvitation;
use App\Models\MatchPost;
use App\Models\PlayerProfile;
use App\Models\ReputationDispute;
use App\Models\User;
use App\Services\Infrastructure\IdempotencyService;
use App\Services\Social\MatchService;
use App\Services\Social\ReputationService;
use Illuminate\Http\Request;

class SocialController extends Controller
{
    use ResolvesV2Organization;

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

        return response()->json(['matches' => $this->matches->discover($orgId, $request->all())]);
    }

    public function show(Request $request, MatchPost $post)
    {
        return response()->json(['match' => $this->matches->show($post, $request->user())]);
    }


    public function create(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'title' => 'required|string|max:180',
            'visibility' => 'required|in:public,invite_only',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date',
            'city' => 'nullable|string|max:120',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'min_skill' => 'nullable|integer|min:1|max:5',
            'max_skill' => 'nullable|integer|min:1|max:5',
            'required_participants' => 'required|integer|min:2|max:100',
            'availability' => 'nullable|array',
            'metadata' => 'nullable|array',
        ]);
        return $this->idempotency->run($request, fn () => response()->json(['match' => $this->matches->createPost($request->user(), $organization->id, $data)], 201));
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
            if (!empty($data['invitee_ids'])) {
                $batch = $this->matches->inviteMany($post, $request->user(), $data['invitee_ids']);
                $first = $batch['invitations'][0] ?? null;

                return response()->json(array_merge($batch, ['invitation' => $first]), 201);
            }

            abort_unless(!empty($data['invitee_id']), 422, 'invitee_id or invitee_ids is required.');
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
        $organization = $this->organization($request);
        abort_unless($post->organization_id === $organization->id, 404);
        return $this->idempotency->run($request, fn () => response()->json(['participation' => $this->matches->join($post, $request->user())], 201));
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
