<?php

namespace App\Domain\Tournament\Support;

use App\Contracts\Repositories\TournamentPlayRepositoryInterface;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\TournamentFixture;
use Illuminate\Support\Collection;

/** FR-122: what the tournament page and the management screen show of the play: entries, fixtures and group tables. */
class TournamentBoard
{
    public function __construct(private readonly TournamentPlayRepositoryInterface $play) {}

    /**
     * @return array{entries: Collection<int, TournamentEntry>, fixtures: Collection<int, TournamentFixture>, standings: array<string, list<array<string, mixed>>>}
     */
    public function of(Tournament $tournament): array
    {
        $entries = $this->play->entries($tournament, [$tournament->acceptedStatus()]);
        $fixtures = $this->play->fixtures($tournament);

        return ['entries' => $entries, 'fixtures' => $fixtures, 'standings' => Standings::compute($tournament, $entries, $fixtures)];
    }
}
