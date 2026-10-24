<?php

namespace App\Domain\Tournament\Actions;

use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Domain\Tournament\DTOs\TournamentSetupData;
use App\Domain\Tournament\Rules\TournamentAccess;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** FR-120, D21: the setup changes only while the tournament is a draft (owner; the representative for internal ones). */
class UpdateTournament
{
    public function __construct(private readonly TournamentRepositoryInterface $tournaments) {}

    public function execute(Tournament $tournament, User $actor, TournamentSetupData $setup): Tournament
    {
        TournamentAccess::authorize($tournament, $actor, 'edit');

        return DB::transaction(function () use ($tournament, $setup): Tournament {
            $locked = $this->tournaments->lock($tournament);
            if ($locked->status !== Tournament::STATUS_DRAFT) {
                TournamentAccess::fail('TOURNAMENT_NOT_DRAFT', 'Chỉ sửa được thông tin khi giải còn là bản nháp.', 409);
            }

            return $this->tournaments->update($locked, $setup->attributes);
        });
    }
}
