<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentEvent;
use App\Models\TournamentFixture;
use App\Models\TournamentParticipant;
use App\Models\TournamentStanding;
use App\Models\User;
use App\Services\OutboxService;
use Illuminate\Support\Facades\DB;

class TournamentService
{
    public function __construct(private readonly OutboxService $outbox) {}

    public function createAndGenerate(User $creator, int $organizationId, string $name, string $format, array $seedUserIds, array $settings = []): Tournament
    {
        abort_unless(in_array($format, ['single_elimination', 'round_robin'], true), 422, 'Only single-elimination and round-robin tournaments are supported.');
        abort_if(count($seedUserIds) < 2, 422, 'At least two participants are required.');
        return DB::transaction(function () use ($creator, $organizationId, $name, $format, $seedUserIds, $settings): Tournament {
            $tournament = Tournament::create(['organization_id' => $organizationId, 'created_by' => $creator->id, 'name' => $name, 'format' => $format, 'status' => 'ready', 'settings' => $settings]);
            foreach (array_values($seedUserIds) as $seed => $userId) {
                TournamentParticipant::create(['organization_id' => $organizationId, 'tournament_id' => $tournament->id, 'user_id' => $userId, 'seed' => $seed + 1, 'status' => 'registered']);
                TournamentStanding::create(['organization_id' => $organizationId, 'tournament_id' => $tournament->id, 'participant_id' => $tournament->participants()->latest('id')->value('id')]);
            }
            $tournament = $tournament->fresh('participants');
            if ($format === 'single_elimination') $this->generateSingleElimination($tournament);
            else $this->generateRoundRobin($tournament);
            $this->emit($tournament, $creator, 'tournament.generated', ['format' => $format, 'participant_count' => count($seedUserIds)]);
            return $tournament->fresh(['participants.user', 'fixtures', 'standings']);
        });
    }

    public function checkIn(TournamentParticipant $participant, User $user): TournamentParticipant
    {
        abort_unless($participant->user_id === $user->id, 403);
        abort_unless($participant->status === 'registered', 409);
        $participant->update(['status' => 'checked_in', 'checked_in_at' => now()]);
        return $participant->fresh();
    }

    public function submitScore(Tournament $tournament, TournamentFixture $fixture, User $official, int $expectedVersion, array $score, int $winnerId): TournamentFixture
    {
        return DB::transaction(function () use ($tournament, $fixture, $official, $expectedVersion, $score, $winnerId): TournamentFixture {
            $tournament = Tournament::query()->whereKey($tournament->id)->lockForUpdate()->firstOrFail();
            abort_unless($tournament->version === $expectedVersion, 409, json_encode(['message' => 'Tournament version conflict.', 'currentVersion' => $tournament->version, 'tournament' => $tournament->load('fixtures')]));
            $fixture = TournamentFixture::query()->whereKey($fixture->id)->lockForUpdate()->firstOrFail();
            abort_unless($fixture->tournament_id === $tournament->id && $fixture->status === 'pending', 409, 'Fixture is not awaiting a score.');
            abort_unless(in_array($winnerId, array_filter([$fixture->participant_one_id, $fixture->participant_two_id]), true), 422, 'Winner must be a fixture participant.');
            $fixture->update(['score' => $score, 'winner_id' => $winnerId, 'status' => 'completed', 'version' => $fixture->version + 1]);
            $tournament->increment('version');
            $this->updateStandings($tournament, $fixture, $score, $winnerId);
            $this->emit($tournament->fresh(), $official, 'tournament.score_updated', ['fixture_id' => $fixture->id, 'score' => $score, 'winner_id' => $winnerId]);
            return $fixture->fresh(['participantOne.user', 'participantTwo.user', 'winner.user']);
        });
    }

    public function events(Tournament $tournament, int $afterVersion = 0)
    {
        return $tournament->events()->where('aggregate_version', '>', $afterVersion)->orderBy('aggregate_version')->get();
    }

    private function generateSingleElimination(Tournament $tournament): void
    {
        $participants = $tournament->participants->sortBy('seed')->values();
        $size = 1;
        while ($size < $participants->count()) $size *= 2;
        $rounds = (int) log($size, 2);
        for ($round = 1; $round <= $rounds; $round++) {
            $fixtures = intdiv($size, 2 ** $round);
            for ($position = 1; $position <= $fixtures; $position++) {
                $one = $two = null;
                if ($round === 1) {
                    $one = $participants->get(($position - 1) * 2)?->id;
                    $two = $participants->get(($position - 1) * 2 + 1)?->id;
                }
                $fixture = TournamentFixture::create(['organization_id' => $tournament->organization_id, 'tournament_id' => $tournament->id, 'round' => $round, 'position' => $position, 'participant_one_id' => $one, 'participant_two_id' => $two, 'status' => $one && $two ? 'pending' : ($one || $two ? 'bye' : 'pending')]);
                if ($fixture->status === 'bye') $fixture->update(['winner_id' => $one ?: $two]);
            }
        }
    }

    private function generateRoundRobin(Tournament $tournament): void
    {
        $participants = $tournament->participants->sortBy('seed')->values();
        $position = 1;
        foreach ($participants as $index => $one) {
            for ($other = $index + 1; $other < $participants->count(); $other++) {
                TournamentFixture::create(['organization_id' => $tournament->organization_id, 'tournament_id' => $tournament->id, 'round' => $position, 'position' => 1, 'participant_one_id' => $one->id, 'participant_two_id' => $participants[$other]->id]);
                $position++;
            }
        }
    }

    private function updateStandings(Tournament $tournament, TournamentFixture $fixture, array $score, int $winnerId): void
    {
        if ($tournament->format !== 'round_robin' || ! $fixture->participant_one_id || ! $fixture->participant_two_id) return;
        $loserId = $winnerId === $fixture->participant_one_id ? $fixture->participant_two_id : $fixture->participant_one_id;
        TournamentStanding::where('participant_id', $winnerId)->update(['played' => DB::raw('played + 1'), 'wins' => DB::raw('wins + 1'), 'points' => DB::raw('points + 3')]);
        TournamentStanding::where('participant_id', $loserId)->update(['played' => DB::raw('played + 1'), 'losses' => DB::raw('losses + 1')]);
        $tournament->standings()->orderByDesc('points')->orderBy('participant_id')->get()->each(function (TournamentStanding $standing, int $index): void { $standing->update(['rank' => $index + 1, 'version' => $standing->version + 1]); });
    }

    private function emit(Tournament $tournament, User $actor, string $type, array $payload): void
    {
        $event = TournamentEvent::create(['organization_id' => $tournament->organization_id, 'tournament_id' => $tournament->id, 'aggregate_version' => $tournament->version, 'event_type' => $type, 'created_by' => $actor->id, 'payload' => $payload]);
        $this->outbox->enqueue(['organization_id' => $tournament->organization_id, 'message_key' => "tournament:{$tournament->id}:version:{$tournament->version}", 'channel' => 'realtime', 'event_type' => $type, 'aggregate_type' => Tournament::class, 'aggregate_id' => $tournament->id], ['organization_id' => $tournament->organization_id, 'tournament_id' => $tournament->id, 'version' => $event->aggregate_version, 'payload' => $payload]);
    }
}
