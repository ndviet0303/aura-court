<?php

namespace App\Domain\Tournament\Actions;

use App\Contracts\Repositories\TournamentPlayRepositoryInterface;
use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Domain\Tournament\DTOs\EntryData;
use App\Domain\Tournament\Rules\TournamentAccess;
use App\Domain\Tournament\Support\RosterSheet;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * UC-7.27, FR-126, BR-150: an internal tournament's roster from a spreadsheet. The preview writes nothing; confirming a file
 * without errors replaces the whole roster, until the roster is locked.
 */
class ImportRoster
{
    public function __construct(
        private readonly TournamentRepositoryInterface $tournaments,
        private readonly TournamentPlayRepositoryInterface $play,
    ) {}

    /** @return array{entries: list<EntryData>, errors: list<array{row: int, field: string, message: string}>} */
    public function preview(Tournament $tournament, User $actor, string $path): array
    {
        self::assertOpen($tournament, $actor);

        return RosterSheet::read($tournament, $path);
    }

    /** @return Collection<int, TournamentEntry> */
    public function confirm(Tournament $tournament, User $actor, string $path): Collection
    {
        self::assertOpen($tournament, $actor);
        $sheet = RosterSheet::read($tournament, $path);
        if ($sheet['errors'] !== []) {
            abort(response()->json(['message' => 'Tệp danh sách còn lỗi; chưa có gì được lưu.', 'code' => 'ROSTER_INVALID', 'errors' => $sheet['errors']], 422));
        }

        return DB::transaction(function () use ($tournament, $sheet): Collection {
            $locked = $this->tournaments->lock($tournament);
            self::assertDraft($locked);
            $this->play->deleteEntries($locked);
            foreach ($sheet['entries'] as $entry) {
                $this->play->createEntry($locked, ['name' => $entry->name, 'status' => TournamentEntry::STATUS_CONFIRMED], $entry->members);
            }

            return $this->play->entries($locked);
        });
    }

    public static function assertOpen(Tournament $tournament, User $actor): void
    {
        TournamentAccess::authorize($tournament, $actor, 'roster');
        self::assertDraft($tournament);
    }

    public static function assertDraft(Tournament $tournament): void
    {
        if ($tournament->status !== Tournament::STATUS_DRAFT) {
            TournamentAccess::fail('ROSTER_LOCKED', 'Danh sách đã chốt nên không thể thay đổi.', 409);
        }
    }
}
