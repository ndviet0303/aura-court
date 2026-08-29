<?php

namespace App\Domain\Tournament\Actions;

use App\Models\Tournament;
use App\Services\Tournament\TournamentService;

class CreateTournament
{
    public function __construct(
        private readonly TournamentService $tournaments,
    ) {}

    public function execute(int $organizationId, array $data, ?int $userId = null): Tournament
    {
        return $this->tournaments->createTournament($organizationId, $data, $userId);
    }
}
