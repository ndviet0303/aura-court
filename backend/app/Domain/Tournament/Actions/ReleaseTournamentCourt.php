<?php

namespace App\Domain\Tournament\Actions;

use App\Contracts\Repositories\TournamentPlayRepositoryInterface;
use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Domain\Tournament\Rules\TournamentAccess;
use App\Models\Tournament;
use App\Models\TournamentCourtAllocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** UC-7.30: reserved time goes back to ordinary booking unless a fixture is already scheduled in it. */
class ReleaseTournamentCourt
{
    public function __construct(
        private readonly TournamentRepositoryInterface $tournaments,
        private readonly TournamentPlayRepositoryInterface $play,
    ) {}

    public function execute(Tournament $tournament, User $owner, TournamentCourtAllocation $allocation): void
    {
        TournamentAccess::authorize($tournament, $owner, 'allocate');
        abort_unless((int) $allocation->tournament_id === (int) $tournament->id, 404);

        DB::transaction(function () use ($tournament, $allocation): void {
            $locked = $this->tournaments->lock($tournament);
            if ($this->play->hasFixturesIn($locked, (int) $allocation->court_id, $allocation->starts_at, $allocation->ends_at)) {
                TournamentAccess::fail('ALLOCATION_IN_USE', 'Đã có trận xếp vào khoảng giờ này; đổi lịch trận trước khi trả sân.', 409);
            }
            $this->tournaments->release($allocation);
        });
    }
}
