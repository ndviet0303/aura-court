<?php

namespace App\Domain\Tournament\Actions;

use App\Contracts\Repositories\TournamentPlayRepositoryInterface;
use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Domain\Tournament\Rules\TournamentAccess;
use App\Domain\Tournament\Support\Score;
use App\Domain\Tournament\Support\TournamentProgress;
use App\Models\Tournament;
use App\Models\TournamentFixture;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * UC-7.32, FR-129, BR-155, BR-156: the first result of a fixture, a score or a walkover against the absent side. The
 * server derives the winner and moves the tournament on; a saved result changes only through a correction.
 */
class RecordFixtureResult
{
    public function __construct(
        private readonly TournamentRepositoryInterface $tournaments,
        private readonly TournamentPlayRepositoryInterface $play,
        private readonly TournamentProgress $progress,
    ) {}

    /** @param  array<string, mixed>  $input  a score, or `absent_entry_id` */
    public function execute(TournamentFixture $fixture, User $actor, array $input): Tournament
    {
        $tournament = $fixture->tournament;
        TournamentAccess::authorize($tournament, $actor, 'result');

        return DB::transaction(function () use ($tournament, $fixture, $actor, $input): Tournament {
            $locked = $this->tournaments->lock($tournament);
            $row = $this->play->lockFixture($fixture);
            if ($row->status === TournamentFixture::STATUS_COMPLETED) {
                TournamentAccess::fail('RESULT_EXISTS', 'Trận đã có kết quả; dùng chức năng sửa kết quả.', 409);
            }
            $playing = in_array($locked->status, [Tournament::STATUS_DRAWN, Tournament::STATUS_IN_PROGRESS], true);
            if (! $playing || $row->status !== TournamentFixture::STATUS_PENDING || $row->entry_one_id === null || $row->entry_two_id === null) {
                TournamentAccess::fail('FIXTURE_NOT_READY', 'Trận chưa đủ hai đơn vị hoặc giải chưa ở giai đoạn thi đấu.', 409);
            }
            $this->progress->apply($locked, $row, Score::outcome($locked, $row, $input), $actor);

            return $locked->refresh();
        });
    }
}
