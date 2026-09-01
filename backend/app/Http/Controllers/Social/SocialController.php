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
        return PlayerProfile::firstOrCreate(['organization_id' => $organization->id, 'user_id' => $request->user()->id], ['skill_level' => 1, 'reputation_score' => 60]);
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

        $orgId = $organization?->id;
        if (! $orgId) {
            $orgId = \App\Models\Organization::where('status', 'active')->value('id') ?? 1;
        }

        return response()->json(['matches' => $this->matches->discover($orgId, $request->all())]);
    }

    public function show(Request $request, MatchPost $post)
    {
        return response()->json(['match' => $this->matches->show($post, $request->user())]);
    }


    public function create(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate(['title' => 'required|string|max:180', 'visibility' => 'required|in:public,invite_only', 'starts_at' => 'required|date|after:now', 'ends_at' => 'required|date|after:starts_at', 'city' => 'nullable|string|max:120', 'latitude' => 'nullable|numeric', 'longitude' => 'nullable|numeric', 'min_skill' => 'nullable|integer|min:1|max:5', 'max_skill' => 'nullable|integer|min:1|max:5', 'required_participants' => 'required|integer|min:2|max:100', 'availability' => 'nullable|array']);
        return $this->idempotency->run($request, fn () => response()->json(['match' => $this->matches->createPost($request->user(), $organization->id, $data)], 201));
    }

    public function invite(Request $request, MatchPost $post)
    {
        $organization = $this->organization($request);
        abort_unless($post->organization_id === $organization->id, 404);
        $data = $request->validate(['invitee_id' => 'required|integer']);
        return $this->idempotency->run($request, fn () => response()->json(['invitation' => $this->matches->invite($post, $request->user(), $data['invitee_id'])], 201));
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
