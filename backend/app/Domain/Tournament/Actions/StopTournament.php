<?php

namespace App\Domain\Tournament\Actions;

use App\Contracts\Repositories\TournamentPlayRepositoryInterface;
use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Domain\Tournament\Rules\TournamentAccess;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use App\Services\Payment\OwnerPaymentAttemptService;
use Illuminate\Support\Facades\DB;

/**
 * FR-131, BR-156: the owner (or an internal tournament's representative) stops the tournament. Saved results stay;
 * every later write is refused, open payment requests expire, and the reserved court time is free again.
 */
class StopTournament
{
    public function __construct(
        private readonly TournamentRepositoryInterface $tournaments,
        private readonly TournamentPlayRepositoryInterface $play,
        private readonly OwnerPaymentAttemptService $payments,
    ) {}

    public function execute(Tournament $tournament, User $actor, ?string $reason): Tournament
    {
        TournamentAccess::authorize($tournament, $actor, 'stop');

        return DB::transaction(function () use ($tournament, $actor, $reason): Tournament {
            $locked = $this->tournaments->lock($tournament);
            if ($locked->status === Tournament::STATUS_FINISHED) {
                TournamentAccess::fail('TOURNAMENT_FINISHED', 'Giải đã kết thúc.', 409);
            }
            foreach ($this->play->entries($locked, [TournamentEntry::STATUS_APPROVED]) as $entry) {
                $this->payments->expirePendingForPurpose(ConfirmEntryPayment::PURPOSE, (string) $entry->id);
            }
            $this->tournaments->recordEvent($locked, 'tournament.stopped', $actor, $reason, ['from' => $locked->status]);

            return $this->tournaments->update($locked, [
                'status' => Tournament::STATUS_STOPPED,
                'stopped_at' => now(),
                'stopped_by' => $actor->id,
                'stop_reason' => $reason,
            ]);
        });
    }
}
