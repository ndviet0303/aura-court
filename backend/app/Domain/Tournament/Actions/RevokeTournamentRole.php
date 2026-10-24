<?php

namespace App\Domain\Tournament\Actions;

use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Domain\Tournament\Rules\TournamentAccess;
use App\Models\Tournament;
use App\Models\TournamentGrant;
use App\Models\User;

/** UC-7.18: the owner takes a grant back; the account loses the tournament at its next request. */
class RevokeTournamentRole
{
    public function __construct(private readonly TournamentRepositoryInterface $tournaments) {}

    public function execute(Tournament $tournament, User $owner, TournamentGrant $grant): void
    {
        TournamentAccess::authorize($tournament, $owner, 'grant');
        abort_unless((int) $grant->tournament_id === (int) $tournament->id && $grant->revoked_at === null, 404);
        $this->tournaments->revoke($grant);
    }
}
