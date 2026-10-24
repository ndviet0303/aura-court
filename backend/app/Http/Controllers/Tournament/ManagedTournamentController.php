<?php

namespace App\Http\Controllers\Tournament;

use App\Contracts\Repositories\TournamentPlayRepositoryInterface;
use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Domain\Tournament\Actions\CreateInternalTournament;
use App\Domain\Tournament\Actions\CreatePublicTournament;
use App\Domain\Tournament\Actions\PublishTournament;
use App\Domain\Tournament\Actions\StopTournament;
use App\Domain\Tournament\Actions\UpdateTournament;
use App\Domain\Tournament\DTOs\TournamentSetupData;
use App\Domain\Tournament\Rules\TournamentAccess;
use App\Domain\Tournament\Support\TournamentBoard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tournament\CreateInternalTournamentRequest;
use App\Http\Requests\Tournament\ReasonRequest;
use App\Http\Requests\Tournament\SaveTournamentRequest;
use App\Http\Resources\Tournament\ManagedTournamentResource;
use App\Http\Resources\Tournament\TournamentDetailResource;
use App\Http\Resources\Tournament\TournamentSummaryResource;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** UC-7.16–UC-7.19, FR-131: the tournaments an account manages, their setup, publication and stop. */
class ManagedTournamentController extends Controller
{
    public function __construct(private readonly TournamentRepositoryInterface $tournaments) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->tournaments->managedBy($request->user())
            ->map(fn (Tournament $tournament): array => TournamentSummaryResource::make($tournament)->resolve($request) + ['my_role' => TournamentAccess::role($tournament, $request->user())])
            ->filter(fn (array $row): bool => $row['my_role'] !== null);

        return response()->json(['data' => $data->values()]);
    }

    public function show(Request $request, Tournament $tournament, TournamentBoard $board, TournamentPlayRepositoryInterface $play): JsonResponse
    {
        $role = TournamentAccess::role($tournament, $request->user());
        abort_if($role === null, 404);

        return response()->json(ManagedTournamentResource::make([
            'tournament' => $this->tournaments->summary($tournament),
            'role' => $role,
            'entries' => $play->entries($tournament),
            'board' => $board->of($tournament),
            'allocations' => $this->tournaments->allocations($tournament),
            'rentals' => $tournament->isPublic() ? collect() : $this->tournaments->rentals($tournament),
            'grants' => $this->tournaments->grants($tournament),
            'events' => $this->tournaments->events($tournament),
            'courts' => $this->tournaments->courts($tournament),
        ])->resolve($request));
    }

    public function store(SaveTournamentRequest $request, CreatePublicTournament $action): JsonResponse
    {
        $tournament = $action->execute($request->user(), (int) $request->validated('venue_id'), TournamentSetupData::from($request->validated(), true));

        return $this->respond($tournament, 201);
    }

    public function storeInternal(CreateInternalTournamentRequest $request, CreateInternalTournament $action): JsonResponse
    {
        $tournament = $action->execute(
            $request->user(),
            TournamentSetupData::from($request->validated(), false),
            array_values($request->validated('reservation_codes')),
            $request->validated('representative_email'),
        );

        return $this->respond($tournament, 201);
    }

    public function update(SaveTournamentRequest $request, Tournament $tournament, UpdateTournament $action): JsonResponse
    {
        return $this->respond($action->execute($tournament, $request->user(), TournamentSetupData::from($request->validated(), $request->isPublic())));
    }

    public function publish(Request $request, Tournament $tournament, PublishTournament $action): JsonResponse
    {
        return $this->respond($action->execute($tournament, $request->user()));
    }

    public function stop(ReasonRequest $request, Tournament $tournament, StopTournament $action): JsonResponse
    {
        return $this->respond($action->execute($tournament, $request->user(), $request->validated('reason')));
    }

    private function respond(Tournament $tournament, int $status = 200): JsonResponse
    {
        return response()->json(['tournament' => TournamentDetailResource::make($this->tournaments->summary($tournament))], $status);
    }
}
