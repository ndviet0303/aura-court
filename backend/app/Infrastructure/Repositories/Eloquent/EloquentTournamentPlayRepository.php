<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\TournamentPlayRepositoryInterface;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\TournamentFixture;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EloquentTournamentPlayRepository implements TournamentPlayRepositoryInterface
{
    private const ENTRY = ['members', 'user:id,name,email,phone'];

    private const FIXTURE = ['entryOne:id,name', 'entryTwo:id,name', 'court:id,code'];

    public function entries(Tournament $tournament, ?array $statuses = null): Collection
    {
        return $tournament->entries()
            ->when($statuses !== null, fn ($query) => $query->whereIn('status', $statuses))
            ->with(self::ENTRY)
            ->orderBy('id')
            ->get();
    }

    public function acceptedCount(Tournament $tournament): int
    {
        return $tournament->entries()->where('status', $tournament->acceptedStatus())->count();
    }

    public function hasLiveEntry(Tournament $tournament, int $userId): bool
    {
        return $tournament->entries()->where('user_id', $userId)
            ->whereNotIn('status', [TournamentEntry::STATUS_REJECTED, TournamentEntry::STATUS_WITHDRAWN])
            ->exists();
    }

    public function createEntry(Tournament $tournament, array $attributes, array $members): TournamentEntry
    {
        $entry = $tournament->entries()->create($attributes);
        foreach ($members as $index => $member) {
            $entry->members()->create($member + ['position' => $index + 1]);
        }

        return $entry->load(self::ENTRY);
    }

    public function updateEntry(TournamentEntry $entry, array $attributes, ?array $members = null): TournamentEntry
    {
        $entry->update($attributes);
        if ($members !== null) {
            $entry->members()->delete();
            foreach ($members as $index => $member) {
                $entry->members()->create($member + ['position' => $index + 1]);
            }
        }

        return $entry->load(self::ENTRY);
    }

    public function deleteEntry(TournamentEntry $entry): void
    {
        $entry->delete();
    }

    public function deleteEntries(Tournament $tournament): void
    {
        $tournament->entries()->delete();
    }

    public function lockEntry(int $id): ?TournamentEntry
    {
        return TournamentEntry::query()->whereKey($id)->lockForUpdate()->first();
    }

    public function entriesOf(User $user): Collection
    {
        return TournamentEntry::query()->where('user_id', $user->id)
            ->with(['members', 'user:id,name,email,phone', 'tournament.venue:id,organization_id,name,address',
                'tournament' => fn ($tournament) => $tournament->withCount(['entries as accepted_count' => fn ($entries) => $entries->whereIn('status', [TournamentEntry::STATUS_PAID, TournamentEntry::STATUS_CONFIRMED])])])
            ->orderByDesc('id')
            ->get();
    }

    public function fixtures(Tournament $tournament): Collection
    {
        return $tournament->fixtures()->with(self::FIXTURE)
            ->orderByRaw("case when stage = 'group' then 0 else 1 end")
            ->orderBy('group')->orderBy('round')->orderBy('position')
            ->get();
    }

    public function replaceFixtures(Tournament $tournament, array $rows): void
    {
        $tournament->fixtures()->delete();
        foreach ($rows as $row) {
            $tournament->fixtures()->create($row);
        }
    }

    public function placeKnockout(Tournament $tournament, array $rows): void
    {
        foreach ($rows as $row) {
            $tournament->fixtures()->updateOrCreate(
                ['stage' => $row['stage'], 'group' => $row['group'], 'round' => $row['round'], 'position' => $row['position']],
                ['entry_one_id' => $row['entry_one_id'], 'entry_two_id' => $row['entry_two_id'], 'winner_entry_id' => $row['winner_entry_id'], 'status' => $row['status'], 'score' => null, 'forfeit_entry_id' => null],
            );
        }
    }

    public function lockFixture(TournamentFixture $fixture): TournamentFixture
    {
        return TournamentFixture::query()->whereKey($fixture->id)->lockForUpdate()->firstOrFail();
    }

    public function findFixture(Tournament $tournament, string $stage, int $round, int $position): ?TournamentFixture
    {
        return $tournament->fixtures()->where('stage', $stage)->where('round', $round)->where('position', $position)->first();
    }

    public function updateFixture(TournamentFixture $fixture, array $attributes): TournamentFixture
    {
        $fixture->update($attributes);

        return $fixture->load(self::FIXTURE);
    }

    public function clashes(TournamentFixture $fixture, int $courtId, CarbonInterface $start, CarbonInterface $end): bool
    {
        return TournamentFixture::query()
            ->whereKeyNot($fixture->id)
            ->where('court_id', $courtId)
            ->where('starts_at', '<', $end->copy()->utc())
            ->where('ends_at', '>', $start->copy()->utc())
            ->whereHas('tournament', fn ($tournament) => $tournament->where('status', '!=', Tournament::STATUS_STOPPED))
            ->exists();
    }

    public function hasResults(Tournament $tournament): bool
    {
        return $tournament->fixtures()->where('status', TournamentFixture::STATUS_COMPLETED)->exists();
    }

    public function hasFixturesIn(Tournament $tournament, int $courtId, CarbonInterface $start, CarbonInterface $end): bool
    {
        return $tournament->fixtures()->where('court_id', $courtId)
            ->where('starts_at', '<', $end->copy()->utc())
            ->where('ends_at', '>', $start->copy()->utc())
            ->exists();
    }
}
