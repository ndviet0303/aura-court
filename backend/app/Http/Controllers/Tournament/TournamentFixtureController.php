<?php

namespace App\Http\Controllers\Tournament;

use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Domain\Tournament\Actions\CorrectFixtureResult;
use App\Domain\Tournament\Actions\RecordFixtureResult;
use App\Domain\Tournament\Actions\ScheduleFixture;
use App\Domain\Tournament\Support\TournamentBoard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tournament\CourtTimeRequest;
use App\Http\Requests\Tournament\FixtureResultRequest;
use App\Http\Resources\Tournament\TournamentBoardResource;
use App\Http\Resources\Tournament\TournamentDetailResource;
use App\Http\Resources\Tournament\TournamentFixtureResource;
use App\Models\Tournament;
use App\Models\TournamentFixture;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** UC-7.31–UC-7.33, BR-156: a fixture's court time, its result or walkover, and the owner's correction. */
class TournamentFixtureController extends Controller
{
    public function __construct(
        private readonly TournamentBoard $board,
        private readonly TournamentRepositoryInterface $tournaments,
    ) {}

    public function schedule(CourtTimeRequest $request, TournamentFixture $fixture, ScheduleFixture $action): JsonResponse
    {
        $fixture = $action->execute($fixture, $request->user(), (int) $request->validated('court_id'), Carbon::parse($request->validated('starts_at')), Carbon::parse($request->validated('ends_at')));

        return response()->json(['fixture' => TournamentFixtureResource::make($fixture)]);
    }

    /** Also the walkover route: the request then carries `absent_entry_id`. */
    public function result(FixtureResultRequest $request, TournamentFixture $fixture, RecordFixtureResult $action): JsonResponse
    {
        return $this->respond($request, $fixture, $action->execute($fixture, $request->user(), $request->outcome()));
    }

    public function correct(FixtureResultRequest $request, TournamentFixture $fixture, CorrectFixtureResult $action): JsonResponse
    {
        return $this->respond($request, $fixture, $action->execute($fixture, $request->user(), (string) $request->validated('reason'), $request->outcome()));
    }

    private function respond(Request $request, TournamentFixture $fixture, Tournament $tournament): JsonResponse
    {
        return response()->json([
            'fixture' => TournamentFixtureResource::make($fixture->refresh()->load(['entryOne:id,name', 'entryTwo:id,name', 'court:id,code'])),
            'tournament' => TournamentDetailResource::make($this->tournaments->summary($tournament)),
            ...TournamentBoardResource::make($this->board->of($tournament), false)->resolve($request),
        ]);
    }
}
