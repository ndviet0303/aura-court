<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesV2Organization;
use App\Models\Tournament;
use App\Models\TournamentFixture;
use App\Models\TournamentParticipant;
use App\Services\IdempotencyService;
use App\Services\TournamentService;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    use ResolvesV2Organization;

    public function __construct(private readonly TournamentService $tournaments, private readonly IdempotencyService $idempotency) {}

    public function index(Request $request)
    {
        $organization = $this->organization($request);
        return Tournament::query()->where('organization_id', $organization->id)->withCount('participants')->latest()->paginate(20);
    }

    public function show(Request $request, Tournament $tournament)
    {
        $organization = $this->organization($request);
        abort_unless($tournament->organization_id === $organization->id, 404);
        return response()->json(['tournament' => $tournament->load(['participants.user', 'fixtures', 'standings.participant.user'])]);
    }

    public function create(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate(['name' => 'required|string|max:180', 'format' => 'required|in:single_elimination,round_robin', 'participant_ids' => 'required|array|min:2|max:128', 'participant_ids.*' => 'integer']);
        return $this->idempotency->run($request, fn () => response()->json(['tournament' => $this->tournaments->createAndGenerate($request->user(), $organization->id, $data['name'], $data['format'], $data['participant_ids'])], 201));
    }

    public function checkIn(Request $request, TournamentParticipant $participant)
    {
        $organization = $this->organization($request);
        abort_unless($participant->organization_id === $organization->id, 404);
        return $this->idempotency->run($request, fn () => response()->json(['participant' => $this->tournaments->checkIn($participant, $request->user())]));
    }

    public function score(Request $request, Tournament $tournament, TournamentFixture $fixture)
    {
        $organization = $this->organization($request);
        abort_unless($tournament->organization_id === $organization->id && $fixture->tournament_id === $tournament->id, 404);
        $data = $request->validate(['expected_version' => 'required|integer|min:0', 'winner_id' => 'required|integer', 'score' => 'required|array']);
        return $this->idempotency->run($request, fn () => response()->json(['fixture' => $this->tournaments->submitScore($tournament, $fixture, $request->user(), $data['expected_version'], $data['score'], $data['winner_id'])]));
    }

    public function events(Request $request, Tournament $tournament)
    {
        $organization = $this->organization($request);
        abort_unless($tournament->organization_id === $organization->id, 404);
        return response()->json(['events' => $this->tournaments->events($tournament, (int) $request->integer('after_version')), 'current_version' => $tournament->version]);
    }

    public function poll(Request $request, Tournament $tournament)
    {
        $organization = $this->organization($request);
        abort_unless($tournament->organization_id === $organization->id, 404);
        return response()->json(['mode' => 'polling', 'tournament' => $tournament->load(['fixtures', 'standings.participant.user']), 'current_version' => $tournament->version]);
    }
}
