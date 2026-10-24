<?php

namespace App\Http\Controllers\Tournament;

use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Domain\Tournament\Actions\AllocateTournamentCourt;
use App\Domain\Tournament\Actions\DrawTournament;
use App\Domain\Tournament\Actions\GrantTournamentRole;
use App\Domain\Tournament\Actions\LockRoster;
use App\Domain\Tournament\Actions\ReleaseTournamentCourt;
use App\Domain\Tournament\Actions\RevokeTournamentRole;
use App\Domain\Tournament\Support\TournamentBoard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tournament\CourtTimeRequest;
use App\Http\Requests\Tournament\GrantRoleRequest;
use App\Http\Resources\Tournament\TournamentBoardResource;
use App\Http\Resources\Tournament\TournamentDetailResource;
use App\Models\Tournament;
use App\Models\TournamentCourtAllocation;
use App\Models\TournamentGrant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/** UC-7.18, UC-7.28–UC-7.30: reserved court time, roles, the roster lock and the draw. */
class TournamentSetupController extends Controller
{
    public function __construct(private readonly TournamentRepositoryInterface $tournaments) {}

    public function allocate(CourtTimeRequest $request, Tournament $tournament, AllocateTournamentCourt $action): JsonResponse
    {
        $allocation = $action->execute($tournament, $request->user(), (int) $request->validated('court_id'), Carbon::parse($request->validated('starts_at')), Carbon::parse($request->validated('ends_at')));

        return response()->json(['allocation' => [
            'id' => $allocation->id,
            'court' => $allocation->court->only(['id', 'code']),
            'starts_at' => $allocation->starts_at->toIso8601String(),
            'ends_at' => $allocation->ends_at->toIso8601String(),
        ]], 201);
    }

    public function release(Request $request, Tournament $tournament, TournamentCourtAllocation $allocation, ReleaseTournamentCourt $action): Response
    {
        $action->execute($tournament, $request->user(), $allocation);

        return response()->noContent();
    }

    public function grant(GrantRoleRequest $request, Tournament $tournament, GrantTournamentRole $action): JsonResponse
    {
        $grant = $action->execute($tournament, $request->user(), (string) $request->validated('role'), (string) $request->validated('email'));

        return response()->json(['grant' => ['id' => $grant->id, 'role' => $grant->role, 'user' => $grant->user->only(['id', 'name', 'email'])]], 201);
    }

    public function revoke(Request $request, Tournament $tournament, TournamentGrant $grant, RevokeTournamentRole $action): Response
    {
        $action->execute($tournament, $request->user(), $grant);

        return response()->noContent();
    }

    public function lockRoster(Request $request, Tournament $tournament, LockRoster $action): JsonResponse
    {
        return response()->json(['tournament' => TournamentDetailResource::make($this->tournaments->summary($action->execute($tournament, $request->user())))]);
    }

    public function draw(Request $request, Tournament $tournament, DrawTournament $action, TournamentBoard $board): JsonResponse
    {
        $tournament = $action->execute($tournament, $request->user());

        return response()->json([
            'tournament' => TournamentDetailResource::make($this->tournaments->summary($tournament)),
            ...TournamentBoardResource::make($board->of($tournament), false)->resolve($request),
        ]);
    }
}
