<?php

namespace App\Domain\Tournament\Actions;

use App\Contracts\Repositories\TournamentPlayRepositoryInterface;
use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Domain\Tournament\Rules\TournamentAccess;
use App\Domain\Tournament\Support\Bracket;
use App\Domain\Tournament\Support\Score;
use App\Domain\Tournament\Support\TournamentProgress;
use App\Models\Tournament;
use App\Models\TournamentFixture;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * UC-7.33, FR-130, BR-157: the owner of a public tournament corrects a saved result with a reason; the before and
 * after are kept in the history. A correction that would change a later fixture already played is refused and those
 * fixtures are listed (E-1).
 */
class CorrectFixtureResult
{
    public function __construct(
        private readonly TournamentRepositoryInterface $tournaments,
        private readonly TournamentPlayRepositoryInterface $play,
        private readonly TournamentProgress $progress,
    ) {}

    /** @param  array<string, mixed>  $input  a score, or `absent_entry_id` */
    public function execute(TournamentFixture $fixture, User $owner, string $reason, array $input): Tournament
    {
        $tournament = $fixture->tournament;
        TournamentAccess::authorize($tournament, $owner, 'correct');

        return DB::transaction(function () use ($tournament, $fixture, $owner, $reason, $input): Tournament {
            $locked = $this->tournaments->lock($tournament);
            $row = $this->play->lockFixture($fixture);
            if ($row->status !== TournamentFixture::STATUS_COMPLETED) {
                TournamentAccess::fail('FIXTURE_NOT_READY', 'Chỉ sửa được trận đã có kết quả.', 409);
            }
            $played = $this->play->fixtures($locked)
                ->filter(fn (TournamentFixture $later): bool => $later->status === TournamentFixture::STATUS_COMPLETED && Bracket::feeds($row, $later));
            if ($played->isNotEmpty()) {
                TournamentAccess::fail('CORRECTION_BLOCKED', 'Các trận vòng sau phụ thuộc kết quả này đã có kết quả; cần xử lý các trận đó trước khi sửa.', 409, [
                    'fixture_ids' => $played->pluck('id')->values()->all(),
                ]);
            }
            $before = $row->only(['score', 'winner_entry_id', 'forfeit_entry_id']);
            $after = Score::outcome($locked, $row, $input);
            $this->progress->apply($locked, $row, $after, $owner);
            $this->tournaments->recordEvent($locked, 'result.corrected', $owner, $reason, ['fixture_id' => $row->id, 'before' => $before, 'after' => $after]);

            return $locked->refresh();
        });
    }
}
