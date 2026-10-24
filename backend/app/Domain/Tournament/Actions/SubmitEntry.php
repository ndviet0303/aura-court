<?php

namespace App\Domain\Tournament\Actions;

use App\Contracts\Repositories\TournamentPlayRepositoryInterface;
use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Domain\Tournament\DTOs\EntryData;
use App\Domain\Tournament\Rules\EntryShape;
use App\Domain\Tournament\Rules\TournamentAccess;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * FR-123, BR-150, UC-7.21: a signed-in account applies for a unit before the registration deadline. The
 * athletes need no account; the application waits for the owner's review and takes no place yet (BR-151).
 */
class SubmitEntry
{
    public function __construct(
        private readonly TournamentRepositoryInterface $tournaments,
        private readonly TournamentPlayRepositoryInterface $play,
    ) {}

    public function execute(Tournament $tournament, User $representative, EntryData $entry): TournamentEntry
    {
        return DB::transaction(function () use ($tournament, $representative, $entry): TournamentEntry {
            $locked = $this->tournaments->lock($tournament);
            if (! $locked->acceptsEntries()) {
                TournamentAccess::fail('ENTRY_CLOSED', 'Giải không nhận đăng ký hoặc đã hết hạn đăng ký.', 409);
            }
            if ($this->play->hasLiveEntry($locked, (int) $representative->id)) {
                TournamentAccess::fail('ENTRY_EXISTS', 'Bạn đã có hồ sơ đang được xử lý cho giải này.', 409);
            }
            EntryShape::assert($locked, $entry);

            return $this->play->createEntry($locked, ['user_id' => $representative->id, 'name' => $entry->name, 'status' => TournamentEntry::STATUS_SUBMITTED], $entry->members);
        });
    }
}
