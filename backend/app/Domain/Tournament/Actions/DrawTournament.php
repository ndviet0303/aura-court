<?php

namespace App\Domain\Tournament\Actions;

use App\Contracts\Repositories\TournamentPlayRepositoryInterface;
use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Domain\Tournament\Rules\TournamentAccess;
use App\Domain\Tournament\Support\Bracket;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * UC-7.29, FR-127, BR-153, BR-154: a random draw of the locked roster into groups or a knockout bracket, only before the
 * start (PRE-3). It may be redrawn while no result is saved; each draw is recorded.
 */
class DrawTournament
{
    public function __construct(
        private readonly TournamentRepositoryInterface $tournaments,
        private readonly TournamentPlayRepositoryInterface $play,
    ) {}

    public function execute(Tournament $tournament, User $actor): Tournament
    {
        TournamentAccess::authorize($tournament, $actor, 'draw');

        return DB::transaction(function () use ($tournament, $actor): Tournament {
            $locked = $this->tournaments->lock($tournament);
            $redraw = $locked->status === Tournament::STATUS_DRAWN;
            if (! in_array($locked->status, [Tournament::STATUS_ROSTER_LOCKED, Tournament::STATUS_DRAWN], true) || $locked->hasStarted() || ($redraw && $this->play->hasResults($locked))) {
                TournamentAccess::fail('DRAW_NOT_ALLOWED', 'Chỉ bốc thăm sau khi chốt danh sách và trước khi giải bắt đầu; bốc lại khi chưa có kết quả trận.', 409);
            }
            $entries = $this->play->entries($locked, [$locked->acceptedStatus()]);
            $ids = Bracket::shuffle($entries->pluck('id')->map(fn ($id): int => (int) $id)->all());
            $groups = [];
            if ($locked->format === Tournament::FORMAT_GROUP) {
                [$groupCount, $advance] = [(int) $locked->group_count, (int) $locked->advance_per_group];
                if (count($ids) < 2 * $groupCount || $advance > intdiv(count($ids), $groupCount) || ($advance > 0 && $advance * $groupCount < 2)) {
                    TournamentAccess::fail('DRAW_NOT_ALLOWED', "{$groupCount} bảng cần ít nhất 2 đơn vị mỗi bảng, số đội đi tiếp không vượt số đội của bảng, và vòng loại trực tiếp cần ít nhất 2 đội.", 409);
                }
                ['groups' => $groups, 'fixtures' => $fixtures] = Bracket::groups($ids, $groupCount);
            } else {
                $fixtures = Bracket::knockout($ids);
            }
            foreach ($entries as $entry) {
                $this->play->updateEntry($entry, ['group' => $groups[$entry->id] ?? null, 'seed' => array_search((int) $entry->id, $ids, true) + 1]);
            }
            $this->play->replaceFixtures($locked, $fixtures);
            $this->tournaments->recordEvent($locked, 'draw', $actor, null, ['order' => $ids, 'groups' => $groups, 'redraw' => $redraw]);

            return $this->tournaments->update($locked, ['status' => Tournament::STATUS_DRAWN]);
        });
    }
}
