<?php

namespace App\Services\Tournament;

use App\Models\Tournament;
use App\Models\TournamentEvent;
use App\Models\TournamentFixture;
use App\Models\TournamentParticipant;
use App\Models\TournamentStanding;
use App\Models\User;
use App\Services\Finance\FinancialPostingService;
use App\Services\Infrastructure\OutboxService;
use App\Services\Payment\WalletAvailabilityService;
use App\Services\Payment\WalletPaymentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TournamentService
{
    public function __construct(
        private readonly OutboxService $outbox,
        private readonly WalletPaymentService $walletPayment,
        private readonly WalletAvailabilityService $walletAvailability,
        private readonly FinancialPostingService $financialPosting
    ) {}

    public static function parseEntryFee(mixed $fee): int
    {
        if (is_int($fee)) {
            return $fee;
        }
        if (is_string($fee)) {
            $digits = preg_replace('/\D/', '', $fee); // not is_numeric(): it reads VND "300.000" as 300.0

            return $digits !== '' ? (int) $digits : 0;
        }

        return 0;
    }

    public function createAndGenerate(User $creator, int $organizationId, string $name, string $format, array $seedUserIds = [], array $settings = []): Tournament
    {
        abort_unless(in_array($format, ['single_elimination', 'round_robin'], true), 422, 'Only single-elimination and round-robin tournaments are supported.');

        return DB::transaction(function () use ($creator, $organizationId, $name, $format, $seedUserIds, $settings): Tournament {
            $hasParticipants = count($seedUserIds) >= 2;
            $status = $hasParticipants ? 'ready' : 'open';

            $tournament = Tournament::create([
                'organization_id' => $organizationId,
                'created_by' => $creator->id,
                'name' => $name,
                'format' => $format,
                'status' => $status,
                'settings' => $settings,
            ]);

            if ($hasParticipants) {
                foreach (array_values($seedUserIds) as $seed => $userId) {
                    $p = TournamentParticipant::create([
                        'organization_id' => $organizationId,
                        'tournament_id' => $tournament->id,
                        'user_id' => $userId,
                        'seed' => $seed + 1,
                        'status' => 'registered',
                    ]);
                    TournamentStanding::create([
                        'organization_id' => $organizationId,
                        'tournament_id' => $tournament->id,
                        'participant_id' => $p->id,
                    ]);
                }
                $tournament = $tournament->fresh('participants');
                if ($format === 'single_elimination') {
                    $this->generateSingleElimination($tournament);
                } else {
                    $this->generateRoundRobin($tournament);
                }
                $this->emit($tournament, $creator, 'tournament.generated', ['format' => $format, 'participant_count' => count($seedUserIds)]);
            }

            return $tournament->fresh(['participants.user', 'fixtures', 'standings']);
        });
    }

    /**
     * Common admission validation helper.
     */
    private function validateAdmission(Tournament $tournament): void
    {
        abort_if($tournament->status === 'finalized', 409, 'Tournament is finalized. Cannot add participants.');
        abort_unless(in_array($tournament->status, ['draft', 'open', 'ready'], true), 409, 'Tournament is not open for registration.');

        if ($tournament->starts_at && now()->gt($tournament->starts_at)) {
            abort(422, 'Tournament has already started.');
        }

        $deadline = $tournament->settings['registration_deadline'] ?? ($tournament->registration_deadline ?? null);
        if ($deadline && now()->gt(Carbon::parse($deadline))) {
            abort(422, 'Registration deadline has passed.');
        }

        $maxParticipants = (int) ($tournament->settings['max_participants'] ?? 16);
        $activeCount = $tournament->participants()->whereNotIn('status', ['withdrawn', 'rejected'])->count();
        abort_if($activeCount >= $maxParticipants, 409, 'Tournament has reached maximum participant capacity.');
    }

    public function register(Tournament $tournament, User $user, array $data = []): array
    {
        return DB::transaction(function () use ($tournament, $user): array {
            $tournament = Tournament::query()->whereKey($tournament->id)->lockForUpdate()->firstOrFail();

            $existing = TournamentParticipant::where('tournament_id', $tournament->id)
                ->where('user_id', $user->id)
                ->first();

            $entryFee = self::parseEntryFee($tournament->settings['entry_fee'] ?? 0);

            if ($existing && ! in_array($existing->status, ['withdrawn', 'rejected'], true)) {
                return [
                    'participant' => $existing->load('user'),
                    'already_registered' => true,
                    'receipt' => [
                        'receipt_code' => "RCP-TRN-{$tournament->id}-{$existing->id}",
                        'tournament_id' => $tournament->id,
                        'tournament_name' => $tournament->name,
                        'participant_id' => $existing->id,
                        'entry_fee' => $entryFee,
                        'currency' => 'VND',
                        'status' => 'confirmed',
                        'registered_at' => $existing->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    ],
                ];
            }

            // Common admission validation
            $this->validateAdmission($tournament);

            $wallet = null;
            if ($entryFee > 0) {
                $this->walletAvailability->assertAvailable((int) $tournament->organization_id);
                $wallet = $this->walletPayment->getOrCreateWallet($user->id, $tournament->organization_id);
                $this->walletPayment->debit($wallet, $entryFee, 'tournament_registration', $tournament->id, "Phí đăng ký giải đấu: {$tournament->name}");
            }

            $maxSeed = (int) $tournament->participants()->max('seed');
            $seed = $existing ? $existing->seed : ($maxSeed + 1);
            if ($existing) {
                $existing->update([
                    'status' => 'registered',
                    'seed' => $seed,
                    'checked_in_at' => null,
                ]);
                $participant = $existing->fresh();
            } else {
                $participant = TournamentParticipant::create([
                    'organization_id' => $tournament->organization_id,
                    'tournament_id' => $tournament->id,
                    'user_id' => $user->id,
                    'seed' => $seed,
                    'status' => 'registered',
                ]);
            }

            TournamentStanding::firstOrCreate([
                'organization_id' => $tournament->organization_id,
                'tournament_id' => $tournament->id,
                'participant_id' => $participant->id,
            ]);

            if ($entryFee > 0 && $wallet) {
                $this->financialPosting->postTournamentRegistrationPayment(
                    $tournament->organization_id,
                    $tournament->id,
                    $participant->id,
                    $entryFee,
                    $wallet->id
                );
            }

            DB::afterCommit(function () use ($tournament, $participant, $user) {
                $this->outbox->enqueue([
                    'organization_id' => $tournament->organization_id,
                    'message_key' => "tournament_participant:{$tournament->id}:{$participant->id}",
                    'channel' => 'notification',
                    'event_type' => 'tournament.participant_registered',
                    'aggregate_type' => Tournament::class,
                    'aggregate_id' => $tournament->id,
                ], [
                    'user_id' => $user->id,
                    'notification' => [
                        'title' => 'Đăng ký giải đấu thành công',
                        'body' => "Bạn đã được ghi danh tham gia giải đấu: {$tournament->name}",
                        'type' => 'tournament_update',
                        'source_id' => $tournament->id,
                        'tournament_id' => $tournament->id,
                    ],
                ]);
            });

            return [
                'participant' => $participant->load('user'),
                'already_registered' => false,
                'receipt' => [
                    'receipt_code' => "RCP-TRN-{$tournament->id}-{$participant->id}",
                    'tournament_id' => $tournament->id,
                    'tournament_name' => $tournament->name,
                    'participant_id' => $participant->id,
                    'entry_fee' => $entryFee,
                    'currency' => 'VND',
                    'status' => 'confirmed',
                    'registered_at' => $participant->created_at?->toIso8601String() ?? now()->toIso8601String(),
                ],
            ];
        });
    }

    public function addParticipant(Tournament $tournament, User $actor, array $data): TournamentParticipant
    {
        return DB::transaction(function () use ($tournament, $actor, $data): TournamentParticipant {
            $tournament = Tournament::query()->whereKey($tournament->id)->lockForUpdate()->firstOrFail();

            // Common admission validation
            $this->validateAdmission($tournament);

            $targetUserId = (int) ($data['user_id'] ?? $actor->id);

            $existing = TournamentParticipant::where('tournament_id', $tournament->id)
                ->where('user_id', $targetUserId)
                ->first();

            if ($existing && ! in_array($existing->status, ['withdrawn', 'rejected'], true)) {
                abort(422, 'Participant is already registered.');
            }

            $entryFee = self::parseEntryFee($tournament->settings['entry_fee'] ?? 0);
            $waiverReason = null;
            $wallet = null;

            if ($entryFee > 0) {
                $isWaived = ! empty($data['fee_waiver']);
                if ($isWaived) {
                    $waiverReason = $data['waiver_reason'] ?? null;
                    abort_if(empty($waiverReason) || ! is_string($waiverReason) || trim($waiverReason) === '', 422, 'Waiver reason is required when fee is waived.');
                } else {
                    $this->walletAvailability->assertAvailable((int) $tournament->organization_id);
                    $wallet = $this->walletPayment->getOrCreateWallet($targetUserId, $tournament->organization_id);
                    $this->walletPayment->debit($wallet, $entryFee, 'tournament_registration', $tournament->id, "Phí đăng ký giải đấu: {$tournament->name}");
                }
            }

            $maxSeed = (int) $tournament->participants()->max('seed');
            $seed = $data['seed'] ?? ($existing ? $existing->seed : ($maxSeed + 1));
            if ($existing) {
                $existing->update([
                    'status' => 'registered',
                    'seed' => $seed,
                    'checked_in_at' => null,
                    'waiver_reason' => $waiverReason,
                ]);
                $participant = $existing->fresh();
            } else {
                $participant = TournamentParticipant::create([
                    'organization_id' => $tournament->organization_id,
                    'tournament_id' => $tournament->id,
                    'user_id' => $targetUserId,
                    'seed' => $seed,
                    'status' => 'registered',
                    'waiver_reason' => $waiverReason,
                ]);
            }

            TournamentStanding::firstOrCreate([
                'organization_id' => $tournament->organization_id,
                'tournament_id' => $tournament->id,
                'participant_id' => $participant->id,
            ]);

            if ($entryFee > 0 && $wallet) {
                $this->financialPosting->postTournamentRegistrationPayment(
                    $tournament->organization_id,
                    $tournament->id,
                    $participant->id,
                    $entryFee,
                    $wallet->id
                );
            }

            DB::afterCommit(function () use ($tournament, $participant, $targetUserId) {
                $this->outbox->enqueue([
                    'organization_id' => $tournament->organization_id,
                    'message_key' => "tournament_participant:{$tournament->id}:{$participant->id}",
                    'channel' => 'notification',
                    'event_type' => 'tournament.participant_registered',
                    'aggregate_type' => Tournament::class,
                    'aggregate_id' => $tournament->id,
                ], [
                    'user_id' => $targetUserId,
                    'notification' => [
                        'title' => 'Đăng ký giải đấu thành công',
                        'body' => "Bạn đã được ghi danh tham gia giải đấu: {$tournament->name}",
                        'type' => 'tournament_update',
                        'source_id' => $tournament->id,
                        'tournament_id' => $tournament->id,
                    ],
                ]);
            });

            return $participant;
        });
    }

    public function checkIn(TournamentParticipant $participant, User $user, mixed $status = null, ?bool $checkedIn = null): TournamentParticipant
    {
        // Handle backwards-compatible arguments: checkIn($participant, $user, ?bool $checkedIn)
        if (is_bool($status)) {
            $checkedIn = $status;
            $status = null;
        }

        $tournament = $participant->tournament;
        $isSelf = ($participant->user_id === $user->id);
        $isOfficial = $tournament ? $tournament->isAuthorizedOfficial($user) : false;

        abort_unless($isSelf || $isOfficial, 403, 'Unauthorized.');

        if ($status === null) {
            if ($checkedIn !== null) {
                $status = $checkedIn ? 'checked_in' : 'registered';
            } else {
                $status = ($participant->status === 'checked_in') ? 'registered' : 'checked_in';
            }
        }
        abort_unless(in_array($status, ['registered', 'checked_in', 'present', 'absent', 'withdrawn'], true), 422, 'Invalid attendance status.');

        if ($isSelf && ! $isOfficial) {
            abort_unless(in_array($status, ['checked_in', 'registered', 'withdrawn'], true), 403, 'Participants can only check-in, uncheck, or withdraw.');
        }

        $participant->status = $status;
        if (in_array($status, ['checked_in', 'present'], true)) {
            $participant->checked_in_at = now();
        } elseif (in_array($status, ['registered', 'withdrawn', 'absent'], true)) {
            $participant->checked_in_at = null;
        }
        $participant->save();

        return $participant->fresh('user');
    }

    public function submitScore(Tournament $tournament, TournamentFixture $fixture, User $official, int $expectedVersion, array $score, int $winnerId, ?string $reason = null): TournamentFixture
    {
        return DB::transaction(function () use ($tournament, $fixture, $official, $expectedVersion, $score, $winnerId, $reason): TournamentFixture {
            $tournament = Tournament::query()->whereKey($tournament->id)->lockForUpdate()->firstOrFail();
            abort_if($tournament->status === 'finalized', 409, 'Tournament is finalized. Results are locked.');

            abort_unless($tournament->version === $expectedVersion, 409, json_encode(['message' => 'Tournament version conflict.', 'currentVersion' => $tournament->version, 'tournament' => $tournament->load('fixtures')]));

            $fixture = TournamentFixture::query()->whereKey($fixture->id)->lockForUpdate()->firstOrFail();
            abort_unless($fixture->tournament_id === $tournament->id && in_array($fixture->status, ['pending', 'scheduled', 'live', 'completed'], true), 409, 'Fixture is not awaiting a score.');
            abort_unless(in_array($winnerId, array_filter([$fixture->participant_one_id, $fixture->participant_two_id]), true), 422, 'Winner must be a fixture participant.');

            $isCorrection = ($fixture->status === 'completed');
            abort_if($isCorrection && (empty($reason) || ! is_string($reason) || trim($reason) === ''), 422, 'Lý do điều chỉnh kết quả là bắt buộc.');

            if ($tournament->format === 'single_elimination') {
                $nextRound = $fixture->round + 1;
                $nextPosition = (int) ceil($fixture->position / 2);
                $nextSlotIsOne = ($fixture->position % 2) === 1;
                $downstreamFixture = TournamentFixture::where('tournament_id', $tournament->id)
                    ->where('round', $nextRound)
                    ->where('position', $nextPosition)
                    ->first();

                if ($downstreamFixture) {
                    if ($isCorrection && $downstreamFixture->status === 'completed') {
                        abort(409, 'Cannot correct score: downstream round has already been completed. Requires full downstream resolution.');
                    }
                    $downstreamFixture->update([
                        $nextSlotIsOne ? 'participant_one_id' : 'participant_two_id' => $winnerId,
                    ]);
                }
            }

            $fixture->update([
                'score' => $score,
                'winner_id' => $winnerId,
                'status' => 'completed',
                'version' => $fixture->version + 1,
            ]);

            $tournament->increment('version');

            $this->recomputeStandings($tournament);

            $payload = [
                'fixture_id' => $fixture->id,
                'score' => $score,
                'winner_id' => $winnerId,
                'is_correction' => $isCorrection,
            ];
            if (! empty($reason)) {
                $payload['reason'] = $reason;
            }

            $this->emit($tournament->fresh(), $official, 'tournament.score_updated', $payload);

            return $fixture->fresh(['participantOne.user', 'participantTwo.user', 'winner.user']);
        });
    }

    public function regenerateBracket(Tournament $tournament, User $actor): Tournament
    {
        $tournament = Tournament::query()->whereKey($tournament->id)->lockForUpdate()->firstOrFail();
        abort_if($tournament->status === 'finalized', 409, 'Tournament is finalized. Cannot regenerate bracket.');

        $activeCount = $tournament->participants()->whereNotIn('status', ['withdrawn', 'rejected'])->count();
        abort_if($activeCount < 2, 422, 'Cần tối thiểu 2 vận động viên đăng ký để tạo sơ đồ phân nhánh.');

        return DB::transaction(function () use ($tournament, $actor): Tournament {
            TournamentFixture::where('tournament_id', $tournament->id)->delete();
            $tournament = $tournament->fresh('participants');
            if ($tournament->format === 'single_elimination') {
                $this->generateSingleElimination($tournament);
            } else {
                $this->generateRoundRobin($tournament);
            }
            $tournament->increment('version');
            $this->emit($tournament->fresh(), $actor, 'tournament.bracket_regenerated', []);

            return $tournament->fresh(['participants.user', 'fixtures', 'standings.participant.user']);
        });
    }

    public function finalize(Tournament $tournament, User $actor): Tournament
    {
        return DB::transaction(function () use ($tournament, $actor): Tournament {
            $tournament = Tournament::query()->whereKey($tournament->id)->lockForUpdate()->firstOrFail();
            abort_if($tournament->status === 'finalized', 409, 'Tournament is already finalized.');

            $incomplete = $tournament->fixtures()
                ->whereNotIn('status', ['completed', 'bye'])
                ->exists();
            abort_if($incomplete, 422, 'Cannot finalize tournament: some fixtures are not completed.');

            $settings = $tournament->settings ?? [];
            $settings['finalized_at'] = now()->toIso8601String();
            $tournament->settings = $settings;
            $tournament->status = 'finalized';
            $tournament->version = $tournament->version + 1;
            $tournament->save();

            $this->emit($tournament, $actor, 'tournament.finalized', [
                'finalized_at' => $settings['finalized_at'],
            ]);

            return $tournament->fresh(['participants.user', 'fixtures', 'standings.participant.user']);
        });
    }

    public function reopen(Tournament $tournament, User $actor, string $reason): Tournament
    {
        return DB::transaction(function () use ($tournament, $actor, $reason): Tournament {
            $tournament = Tournament::query()->whereKey($tournament->id)->lockForUpdate()->firstOrFail();

            $canReopen = $actor->isPlatformAdmin()
                || $actor->hasRoleInOrganization($tournament->organization_id, 'organization_owner');
            abort_unless($canReopen, 403, 'Only organization owner or platform admin can reopen.');

            abort_if(mb_strlen(trim($reason)) < 5, 422, 'Reopen reason must be at least 5 characters.');

            $hasCompleted = $tournament->fixtures()->where('status', 'completed')->exists();
            $newStatus = $hasCompleted ? 'in_progress' : 'ready';

            $settings = $tournament->settings ?? [];
            unset($settings['finalized_at']);
            $settings['reopened_at'] = now()->toIso8601String();
            $tournament->settings = $settings;
            $tournament->status = $newStatus;
            $tournament->version = $tournament->version + 1;
            $tournament->save();

            $this->emit($tournament, $actor, 'tournament.reopened', [
                'reason' => $reason,
                'previous_status' => 'finalized',
                'new_status' => $newStatus,
            ]);

            return $tournament->fresh(['participants.user', 'fixtures', 'standings.participant.user']);
        });
    }

    public function events(Tournament $tournament, int $afterVersion = 0)
    {
        return $tournament->events()->where('aggregate_version', '>', $afterVersion)->orderBy('aggregate_version')->get();
    }

    public function generateSingleElimination(Tournament $tournament): void
    {
        $participants = $tournament->participants()
            ->whereNotIn('status', ['withdrawn', 'rejected'])
            ->orderBy('seed')
            ->get()
            ->values();

        $count = $participants->count();
        abort_if($count < 2, 422, 'Cần tối thiểu 2 vận động viên đăng ký để tạo sơ đồ phân nhánh.');

        $size = 1;
        while ($size < $count) {
            $size *= 2;
        }
        $rounds = (int) log($size, 2);

        // 1. Create all fixtures for all rounds
        $fixtures = [];
        for ($round = 1; $round <= $rounds; $round++) {
            $numFixtures = intdiv($size, 2 ** $round);
            for ($position = 1; $position <= $numFixtures; $position++) {
                $fixtures[$round][$position] = TournamentFixture::create([
                    'organization_id' => $tournament->organization_id,
                    'tournament_id' => $tournament->id,
                    'round' => $round,
                    'position' => $position,
                    'participant_one_id' => null,
                    'participant_two_id' => null,
                    'status' => 'pending',
                    'version' => 1,
                ]);
            }
        }

        // 2. Bracket seed pairs for Round 1
        $seedPairs = $this->generateBracketSeedPairs($size);

        // Map seed to participant (seeds are 1-based)
        $participantBySeed = [];
        foreach ($participants as $idx => $p) {
            $s = $p->seed ?? ($idx + 1);
            $participantBySeed[$s] = $p;
        }

        for ($position = 1; $position <= count($seedPairs); $position++) {
            [$seedA, $seedB] = $seedPairs[$position - 1];
            $p1 = $participantBySeed[$seedA] ?? null;
            $p2 = $participantBySeed[$seedB] ?? null;

            $fixture = $fixtures[1][$position];

            if ($p1 && $p2) {
                $fixture->update([
                    'participant_one_id' => $p1->id,
                    'participant_two_id' => $p2->id,
                    'status' => 'pending',
                ]);
            } elseif ($p1 || $p2) {
                $winner = $p1 ?: $p2;
                $fixture->update([
                    'participant_one_id' => $p1?->id,
                    'participant_two_id' => $p2?->id,
                    'winner_id' => $winner->id,
                    'status' => 'bye',
                ]);

                // IMMEDIATELY advance winner_id into the corresponding Round 2 fixture!
                if ($rounds >= 2) {
                    $nextRound = 2;
                    $nextPosition = (int) ceil($position / 2);
                    $nextSlotIsOne = ($position % 2) === 1;
                    $nextFixture = $fixtures[$nextRound][$nextPosition];
                    $nextFixture->update([
                        $nextSlotIsOne ? 'participant_one_id' : 'participant_two_id' => $winner->id,
                    ]);
                }
            }
        }
    }

    private function generateBracketSeedPairs(int $size): array
    {
        $bracket = [1, 2];
        while (count($bracket) < $size) {
            $next = [];
            $sum = count($bracket) * 2 + 1;
            foreach ($bracket as $seed) {
                $next[] = $seed;
                $next[] = $sum - $seed;
            }
            $bracket = $next;
        }

        return array_chunk($bracket, 2);
    }

    private function generateRoundRobin(Tournament $tournament): void
    {
        $participants = $tournament->participants()
            ->whereNotIn('status', ['withdrawn', 'rejected'])
            ->orderBy('seed')
            ->get()
            ->values();

        $position = 1;
        foreach ($participants as $index => $one) {
            for ($other = $index + 1; $other < $participants->count(); $other++) {
                TournamentFixture::create([
                    'organization_id' => $tournament->organization_id,
                    'tournament_id' => $tournament->id,
                    'round' => $position,
                    'position' => 1,
                    'participant_one_id' => $one->id,
                    'participant_two_id' => $participants[$other]->id,
                    'status' => 'pending',
                    'version' => 1,
                ]);
                $position++;
            }
        }
    }

    public function recomputeStandings(Tournament $tournament): void
    {
        $standings = TournamentStanding::where('tournament_id', $tournament->id)->get()->keyBy('participant_id');
        if ($standings->isEmpty()) {
            return;
        }

        $stats = [];
        foreach ($standings as $participantId => $standing) {
            $stats[$participantId] = [
                'played' => 0,
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
                'points' => 0,
            ];
        }

        $fixtures = TournamentFixture::where('tournament_id', $tournament->id)
            ->where('status', 'completed')
            ->whereNotNull('winner_id')
            ->get();

        foreach ($fixtures as $fixture) {
            if (! $fixture->participant_one_id || ! $fixture->participant_two_id) {
                continue;
            }
            $p1 = $fixture->participant_one_id;
            $p2 = $fixture->participant_two_id;
            $winnerId = $fixture->winner_id;
            $loserId = ($winnerId === $p1) ? $p2 : $p1;

            if (isset($stats[$winnerId])) {
                $stats[$winnerId]['played']++;
                $stats[$winnerId]['wins']++;
                $stats[$winnerId]['points'] += 3;
            }
            if (isset($stats[$loserId])) {
                $stats[$loserId]['played']++;
                $stats[$loserId]['losses']++;
            }
        }

        uasort($stats, function ($a, $b) {
            if ($b['points'] !== $a['points']) {
                return $b['points'] <=> $a['points'];
            }
            if ($b['wins'] !== $a['wins']) {
                return $b['wins'] <=> $a['wins'];
            }

            return $a['losses'] <=> $b['losses'];
        });

        $rank = 1;
        foreach ($stats as $participantId => $data) {
            $standing = $standings->get($participantId);
            if ($standing) {
                $standing->update([
                    'played' => $data['played'],
                    'wins' => $data['wins'],
                    'draws' => $data['draws'],
                    'losses' => $data['losses'],
                    'points' => $data['points'],
                    'rank' => $rank,
                    'version' => $standing->version + 1,
                ]);
                $rank++;
            }
        }
    }

    private function emit(Tournament $tournament, User $actor, string $type, array $payload): void
    {
        $event = TournamentEvent::create([
            'organization_id' => $tournament->organization_id,
            'tournament_id' => $tournament->id,
            'aggregate_version' => $tournament->version,
            'event_type' => $type,
            'created_by' => $actor->id,
            'payload' => $payload,
        ]);

        $this->outbox->enqueue([
            'organization_id' => $tournament->organization_id,
            'message_key' => "tournament:{$tournament->id}:version:{$tournament->version}",
            'channel' => 'realtime',
            'event_type' => $type,
            'aggregate_type' => Tournament::class,
            'aggregate_id' => $tournament->id,
        ], [
            'organization_id' => $tournament->organization_id,
            'tournament_id' => $tournament->id,
            'version' => $event->aggregate_version,
            'payload' => $payload,
        ]);
    }
}
