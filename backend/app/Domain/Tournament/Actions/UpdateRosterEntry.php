<?php

namespace App\Domain\Tournament\Actions;

use App\Contracts\Repositories\TournamentPlayRepositoryInterface;
use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Domain\Tournament\DTOs\EntryData;
use App\Domain\Tournament\Rules\EntryShape;
use App\Domain\Tournament\Rules\TournamentAccess;
use App\Models\TournamentEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** UC-7.27 A-2: a unit of an internal roster is corrected before the roster is locked, checked as when it was added. */
class UpdateRosterEntry
{
    public function __construct(
        private readonly TournamentRepositoryInterface $tournaments,
        private readonly TournamentPlayRepositoryInterface $play,
    ) {}

    public function execute(TournamentEntry $entry, User $actor, EntryData $data): TournamentEntry
    {
        $tournament = $entry->tournament;
        ImportRoster::assertOpen($tournament, $actor);
        EntryShape::assert($tournament, $data);

        return DB::transaction(function () use ($tournament, $entry, $data): TournamentEntry {
            ImportRoster::assertDraft($this->tournaments->lock($tournament));
            // The roster may have been re-imported or the unit removed since the request was routed.
            $locked = $this->play->lockEntry((int) $entry->id) ?? TournamentAccess::fail('ENTRY_NOT_FOUND', 'Đơn vị không còn trong danh sách.', 404);

            return $this->play->updateEntry($locked, ['name' => $data->name], $data->members);
        });
    }
}
