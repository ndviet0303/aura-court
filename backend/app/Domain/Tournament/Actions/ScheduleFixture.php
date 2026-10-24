<?php

namespace App\Domain\Tournament\Actions;

use App\Contracts\Repositories\TournamentPlayRepositoryInterface;
use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Domain\Tournament\Rules\TournamentAccess;
use App\Models\Tournament;
use App\Models\TournamentFixture;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-7.31, FR-128, BR-152: a fixture gets a court and a time inside the court time the tournament holds (its
 * allocations, or for an internal tournament its confirmed rentals) and never overlaps another fixture of that court.
 */
class ScheduleFixture
{
    public function __construct(
        private readonly TournamentRepositoryInterface $tournaments,
        private readonly TournamentPlayRepositoryInterface $play,
    ) {}

    public function execute(TournamentFixture $fixture, User $actor, int $courtId, CarbonInterface $start, CarbonInterface $end): TournamentFixture
    {
        $tournament = $fixture->tournament;
        TournamentAccess::authorize($tournament, $actor, 'schedule');

        return DB::transaction(function () use ($tournament, $fixture, $courtId, $start, $end): TournamentFixture {
            $locked = $this->tournaments->lock($tournament);
            if (! in_array($locked->status, [Tournament::STATUS_DRAWN, Tournament::STATUS_IN_PROGRESS], true) || $fixture->status !== TournamentFixture::STATUS_PENDING) {
                TournamentAccess::fail('FIXTURE_NOT_READY', 'Chỉ xếp lịch cho trận chưa có kết quả sau khi bốc thăm.', 409);
            }
            if ($this->tournaments->lockCourt($locked, $courtId) === null) {
                throw ValidationException::withMessages(['court_id' => 'Sân không thuộc cơ sở hoặc môn của giải.']);
            }
            if (! $this->tournaments->covers($locked, $courtId, $start, $end)) {
                TournamentAccess::fail('FIXTURE_OUTSIDE_COURTS', 'Giờ thi đấu phải nằm trong một khoảng sân đã dành (hoặc một đơn thuê) cho giải.');
            }
            if ($this->play->clashes($fixture, $courtId, $start, $end)) {
                TournamentAccess::fail('FIXTURE_CLASH', 'Sân đã có trận khác trong khoảng giờ này.', 409);
            }

            return $this->play->updateFixture($this->play->lockFixture($fixture), ['court_id' => $courtId, 'starts_at' => $start, 'ends_at' => $end]);
        });
    }
}
