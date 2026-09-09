<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;

use App\Http\Controllers\Concerns\ResolvesV2Organization;
use App\Models\Tournament;
use App\Models\TournamentFixture;
use App\Models\TournamentParticipant;
use App\Services\Infrastructure\IdempotencyService;
use App\Services\Tournament\TournamentService;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    use ResolvesV2Organization;

    public function __construct(private readonly TournamentService $tournaments, private readonly IdempotencyService $idempotency) {}

    public function index(Request $request)
    {
        $organization = null;
        try {
            $organization = $this->organization($request);
        } catch (\Throwable $e) {
            $organization = null;
        }

        $query = Tournament::query()->withCount('participants')->latest();

        if ($organization && $request->header('X-Organization-Id')) {
            $query->where('organization_id', $organization->id);
        } elseif ($organization) {
            $hasOrgTournaments = Tournament::where('organization_id', $organization->id)->exists();
            if ($hasOrgTournaments) {
                $query->where('organization_id', $organization->id);
            }
        }

        if ($request->filled('sport')) {
            $query->where('settings->sport', $request->input('sport'));
        }
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('settings->venue', 'like', $search)
                  ->orWhere('settings->city', 'like', $search);
            });
        }

        return $query->paginate(20);
    }

    public function show(Request $request, Tournament $tournament)
    {
        return response()->json([
            'tournament' => $tournament->load([
                'participants.user',
                'fixtures' => fn ($q) => $q->orderBy('round')->orderBy('position'),
                'fixtures.participantOne.user',
                'fixtures.participantTwo.user',
                'fixtures.winner.user',
                'standings.participant.user'
            ])
        ]);
    }

    public function create(Request $request)
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'name' => 'required|string|max:180',
            'format' => 'required|in:single_elimination,round_robin',
            'participant_ids' => 'nullable|array|min:2|max:128',
            'participant_ids.*' => 'integer',
            'sport' => 'nullable|string',
            'venue' => 'nullable|string',
            'city' => 'nullable|string',
            'prize_pool' => 'nullable|string',
            'entry_fee' => 'nullable|string',
            'max_participants' => 'nullable|integer',
            'start_date' => 'nullable|string',
            'end_date' => 'nullable|string',
            'category' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $participantIds = $data['participant_ids'] ?? [];

        $settings = [
            'sport' => $data['sport'] ?? 'badminton',
            'venue' => $data['venue'] ?? 'Aura Central Court',
            'city' => $data['city'] ?? 'Hồ Chí Minh',
            'prize_pool' => $data['prize_pool'] ?? '20.000.000 ₫',
            'entry_fee' => $data['entry_fee'] ?? '300.000 ₫ / VĐV',
            'max_participants' => (int) ($data['max_participants'] ?? 16),
            'start_date' => $data['start_date'] ?? now()->addDays(7)->toDateString(),
            'end_date' => $data['end_date'] ?? now()->addDays(10)->toDateString(),
            'category' => $data['category'] ?? 'Open',
            'description' => $data['description'] ?? '',
        ];

        return $this->idempotency->run($request, fn () => response()->json([
            'tournament' => $this->tournaments->createAndGenerate(
                $request->user(),
                $organization->id,
                $data['name'],
                $data['format'],
                $participantIds,
                $settings
            )
        ], 201));
    }

    public function checkIn(Request $request, TournamentParticipant $participant)
    {
        $organization = $this->organization($request);
        abort_unless($participant->organization_id === $organization->id, 404);
        $checkedIn = $request->has('checked_in') ? (bool) $request->input('checked_in') : null;
        return $this->idempotency->run($request, fn () => response()->json([
            'participant' => $this->tournaments->checkIn($participant, $request->user(), $checkedIn)
        ]));
    }

    public function score(Request $request, Tournament $tournament, TournamentFixture $fixture)
    {
        $organization = $this->organization($request);
        abort_unless($tournament->organization_id === $organization->id && $fixture->tournament_id === $tournament->id, 404);
        $data = $request->validate([
            'expected_version' => 'nullable|integer|min:0',
            'winner_id' => 'required|integer',
            'score' => 'required|array'
        ]);
        $expectedVersion = $data['expected_version'] ?? $tournament->version;
        return $this->idempotency->run($request, fn () => response()->json([
            'fixture' => $this->tournaments->submitScore($tournament, $fixture, $request->user(), $expectedVersion, $data['score'], $data['winner_id'])
        ]));
    }

    public function regenerateBracket(Request $request, Tournament $tournament)
    {
        $organization = $this->organization($request);
        abort_unless($tournament->organization_id === $organization->id, 404);
        return $this->idempotency->run($request, fn () => response()->json([
            'tournament' => $this->tournaments->regenerateBracket($tournament, $request->user())
        ]));
    }

    public function events(Request $request, Tournament $tournament)
    {
        $organization = $this->organization($request);
        abort_unless($tournament->organization_id === $organization->id, 404);
        return response()->json(['events' => $this->tournaments->events($tournament, (int) $request->integer('after_version')), 'current_version' => $tournament->version]);
    }

    public function addParticipant(Request $request, Tournament $tournament)
    {
        $organization = $this->organization($request);
        abort_unless($tournament->organization_id === $organization->id, 404);
        $data = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'seed' => 'nullable|integer|min:1|max:128',
        ]);

        $userId = $data['user_id'] ?? $request->user()->id;
        $participant = \App\Models\TournamentParticipant::create([
            'organization_id' => $organization->id,
            'tournament_id' => $tournament->id,
            'user_id' => $userId,
            'seed' => $data['seed'] ?? ($tournament->participants()->count() + 1),
            'status' => 'registered',
        ]);

        \App\Models\TournamentStanding::firstOrCreate([
            'organization_id' => $organization->id,
            'tournament_id' => $tournament->id,
            'participant_id' => $participant->id,
        ]);

        \Illuminate\Support\Facades\DB::afterCommit(function () use ($organization, $tournament, $participant, $userId) {
            app(\App\Services\Infrastructure\OutboxService::class)->enqueue([
                'organization_id' => $organization->id,
                'message_key' => "tournament_participant:{$tournament->id}:{$participant->id}",
                'channel' => 'notification',
                'event_type' => 'tournament.participant_registered',
                'aggregate_type' => \App\Models\Tournament::class,
                'aggregate_id' => $tournament->id,
            ], [
                'user_id' => $userId,
                'notification' => [
                    'title' => 'Đăng ký giải đấu thành công',
                    'body' => "Bạn đã được ghi danh tham gia giải đấu: {$tournament->name}",
                    'type' => 'tournament_update',
                    'source_id' => $tournament->id,
                    'tournament_id' => $tournament->id,
                ],
            ]);
        });

        return response()->json([
            'participant' => $participant->load('user'),
            'tournament' => $tournament->fresh(['participants.user', 'fixtures.participantOne.user', 'fixtures.participantTwo.user', 'fixtures.winner.user', 'standings.participant.user']),
        ], 201);
    }

    public function register(Request $request, Tournament $tournament)
    {
        $organization = $this->organization($request);
        abort_unless($tournament->organization_id === $organization->id, 404);

        return $this->idempotency->run($request, function () use ($tournament, $request) {
            $result = $this->tournaments->register($tournament, $request->user(), $request->all());

            return response()->json([
                'message' => $result['already_registered'] ? 'Bạn đã đăng ký giải đấu này.' : 'Đăng ký tham gia giải đấu thành công.',
                'participant' => $result['participant'],
                'receipt' => $result['receipt'],
                'already_registered' => $result['already_registered'],
            ], $result['already_registered'] ? 200 : 201);
        });
    }

    public function registration(Request $request, Tournament $tournament)
    {
        $organization = $this->organization($request);
        abort_unless($tournament->organization_id === $organization->id, 404);

        $participant = \App\Models\TournamentParticipant::where('tournament_id', $tournament->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$participant) {
            return response()->json([
                'registered' => false,
                'participant' => null,
                'receipt' => null,
            ]);
        }

        $entryFee = TournamentService::parseEntryFee($tournament->settings['entry_fee'] ?? 0);

        return response()->json([
            'registered' => true,
            'participant' => $participant->load('user'),
            'receipt' => [
                'receipt_code' => "RCP-TRN-{$tournament->id}-{$participant->id}",
                'tournament_id' => $tournament->id,
                'tournament_name' => $tournament->name,
                'participant_id' => $participant->id,
                'entry_fee' => $entryFee,
                'currency' => 'VND',
                'status' => 'confirmed',
                'registered_at' => $participant->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function poll(Request $request, Tournament $tournament)
    {
        $organization = $this->organization($request);
        abort_unless($tournament->organization_id === $organization->id, 404);
        return response()->json(['mode' => 'polling', 'tournament' => $tournament->load(['fixtures', 'standings.participant.user']), 'current_version' => $tournament->version]);
    }
}
