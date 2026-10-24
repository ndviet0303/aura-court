<?php

namespace App\Domain\Tournament\Actions;

use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Domain\Tournament\Rules\TournamentAccess;
use App\Models\Tournament;
use App\Models\TournamentCourtAllocation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * FR-128, BR-152, UC-7.30, D20: the owner reserves court time for a public tournament. It never overrides a
 * confirmed booking, a live hold or another tournament; once reserved, ordinary booking cannot take it.
 */
class AllocateTournamentCourt
{
    public function __construct(private readonly TournamentRepositoryInterface $tournaments) {}

    public function execute(Tournament $tournament, User $owner, int $courtId, CarbonInterface $start, CarbonInterface $end): TournamentCourtAllocation
    {
        TournamentAccess::authorize($tournament, $owner, 'allocate');

        return DB::transaction(function () use ($tournament, $owner, $courtId, $start, $end): TournamentCourtAllocation {
            $locked = $this->tournaments->lock($tournament);
            if ($locked->isClosed()) {
                TournamentAccess::fail('TOURNAMENT_FINISHED', 'Giải đã kết thúc.', 409);
            }
            if ($this->tournaments->lockCourt($locked, $courtId) === null) {
                throw ValidationException::withMessages(['court_id' => 'Sân không thuộc cơ sở của giải hoặc khác môn thi đấu.']);
            }
            if ($this->tournaments->courtTimeTaken($courtId, $start, $end)) {
                TournamentAccess::fail('ALLOCATION_CONFLICT', 'Khoảng giờ này đã có đơn đặt sân, giữ chỗ hoặc giải khác.', 409);
            }

            return $this->tournaments->allocate($locked, ['court_id' => $courtId, 'starts_at' => $start, 'ends_at' => $end, 'created_by' => $owner->id]);
        });
    }
}
