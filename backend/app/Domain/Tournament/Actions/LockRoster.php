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
 * UC-7.28, FR-127, BR-153: the roster is locked before the draw. A public tournament plays its paid entries, and the payment
 * requests still open expire; an internal one plays its roster. At least two entries are needed, before the start.
 */
class LockRoster
{
    public function __construct(
        private readonly TournamentRepositoryInterface $tournaments,
        private readonly TournamentPlayRepositoryInterface $play,
        private readonly OwnerPaymentAttemptService $payments,
    ) {}

    public function execute(Tournament $tournament, User $actor): Tournament
    {
        TournamentAccess::authorize($tournament, $actor, 'lock');

        return DB::transaction(function () use ($tournament, $actor): Tournament {
            // Attempts before the tournament row: receipt processing locks the attempt, then the entry and the tournament.
            foreach ($tournament->isPublic() ? $this->play->entries($tournament, [TournamentEntry::STATUS_APPROVED]) : [] as $entry) {
                $this->payments->expirePendingForPurpose(ConfirmEntryPayment::PURPOSE, (string) $entry->id);
            }
            $locked = $this->tournaments->lock($tournament);
            if ($locked->status !== ($locked->isPublic() ? Tournament::STATUS_OPEN : Tournament::STATUS_DRAFT)) {
                TournamentAccess::fail('ROSTER_LOCKED', 'Danh sách đã chốt.', 409);
            }
            if ($locked->hasStarted()) {
                TournamentAccess::fail('TOURNAMENT_STARTED', 'Giải đã bắt đầu nên không thể chốt danh sách.', 409);
            }
            $count = $this->play->acceptedCount($locked);
            if ($count < 2) {
                TournamentAccess::fail('ROSTER_TOO_SMALL', 'Cần ít nhất 2 đơn vị để chốt danh sách.');
            }
            $this->tournaments->recordEvent($locked, 'roster.locked', $actor, null, ['entries' => $count]);

            return $this->tournaments->update($locked, ['status' => Tournament::STATUS_ROSTER_LOCKED]);
        });
    }
}
