<?php

namespace App\Domain\Tournament\Actions;

use App\Contracts\Repositories\TournamentPlayRepositoryInterface;
use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Models\TournamentEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** UC-7.27: a unit leaves an internal roster before it is locked. */
class RemoveRosterEntry
{
    public function __construct(
        private readonly TournamentRepositoryInterface $tournaments,
        private readonly TournamentPlayRepositoryInterface $play,
    ) {}

    public function execute(TournamentEntry $entry, User $actor): void
    {
        $tournament = $entry->tournament;
        ImportRoster::assertOpen($tournament, $actor);
        DB::transaction(function () use ($tournament, $entry): void {
            ImportRoster::assertDraft($this->tournaments->lock($tournament));
            $this->play->deleteEntry($entry);
        });
    }
}
