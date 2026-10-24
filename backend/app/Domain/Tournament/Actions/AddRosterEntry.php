<?php

namespace App\Domain\Tournament\Actions;

use App\Contracts\Repositories\TournamentPlayRepositoryInterface;
use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Domain\Tournament\DTOs\EntryData;
use App\Domain\Tournament\Rules\EntryShape;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** UC-7.27: the owner or representative types one unit of an internal roster, checked as the spreadsheet is. */
class AddRosterEntry
{
    public function __construct(
        private readonly TournamentRepositoryInterface $tournaments,
        private readonly TournamentPlayRepositoryInterface $play,
    ) {}

    public function execute(Tournament $tournament, User $actor, EntryData $entry): TournamentEntry
    {
        ImportRoster::assertOpen($tournament, $actor);
        EntryShape::assert($tournament, $entry);

        return DB::transaction(function () use ($tournament, $entry): TournamentEntry {
            $locked = $this->tournaments->lock($tournament);
            ImportRoster::assertDraft($locked);

            return $this->play->createEntry($locked, ['name' => $entry->name, 'status' => TournamentEntry::STATUS_CONFIRMED], $entry->members);
        });
    }
}
