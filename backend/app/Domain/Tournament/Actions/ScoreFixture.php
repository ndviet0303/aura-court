<?php

namespace App\Domain\Tournament\Actions;

use App\Models\Tournament;
use App\Models\TournamentFixture;
use App\Services\Tournament\TournamentService;

class ScoreFixture
{
    public function __construct(
        private readonly TournamentService $tournaments,
    ) {}

    public function execute(Tournament $tournament, TournamentFixture $fixture, array $scoreData): TournamentFixture
    {
        return $this->tournaments->scoreFixture($tournament, $fixture, $scoreData);
    }
}
