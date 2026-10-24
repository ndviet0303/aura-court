<?php

namespace App\Http\Controllers\Tournament;

use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Domain\Tournament\Rules\TournamentAccess;
use App\Domain\Tournament\Support\TournamentBoard;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tournament\TournamentBoardResource;
use App\Http\Resources\Tournament\TournamentDetailResource;
use App\Http\Resources\Tournament\TournamentSummaryResource;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** FR-121, FR-122: published public tournaments for everyone; an internal one for those who take part in it. */
class PublicTournamentController extends Controller
{
    public function __construct(private readonly TournamentRepositoryInterface $tournaments) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->tournaments->paginatePublished(
            in_array($request->query('sport'), Tournament::SPORTS, true) ? (string) $request->query('sport') : null,
            is_string($request->query('status')) && $request->query('status') !== '' ? (string) $request->query('status') : null,
            12,
        );

        return response()->json([
            'data' => TournamentSummaryResource::collection($page->items()),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Request $request, Tournament $tournament, TournamentBoard $board): JsonResponse
    {
        abort_unless(TournamentAccess::canView($tournament, $request->user('sanctum')), 404);

        return response()->json([
            'tournament' => TournamentDetailResource::make($this->tournaments->summary($tournament)),
            ...TournamentBoardResource::make($board->of($tournament))->resolve($request),
        ]);
    }
}
