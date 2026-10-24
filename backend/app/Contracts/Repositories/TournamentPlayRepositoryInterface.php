<?php

namespace App\Contracts\Repositories;

use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\TournamentFixture;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Module 07 part C: a tournament's entries with their athletes, and its fixtures. */
interface TournamentPlayRepositoryInterface
{
    /**
     * @param  list<string>|null  $statuses  null for every status
     * @return Collection<int, TournamentEntry> oldest first, with members and representative
     */
    public function entries(Tournament $tournament, ?array $statuses = null): Collection;

    public function acceptedCount(Tournament $tournament): int;

    /** Whether the account has an application still in play (not rejected or withdrawn). */
    public function hasLiveEntry(Tournament $tournament, int $userId): bool;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $members
     */
    public function createEntry(Tournament $tournament, array $attributes, array $members): TournamentEntry;

    /** @param  array<string, mixed>  $attributes */
    public function updateEntry(TournamentEntry $entry, array $attributes): TournamentEntry;

    public function deleteEntry(TournamentEntry $entry): void;

    public function deleteEntries(Tournament $tournament): void;

    /** The entry re-read FOR UPDATE; call inside a transaction. */
    public function lockEntry(int $id): ?TournamentEntry;

    /** @return Collection<int, TournamentEntry> the account's applications, newest first, with tournament, venue and members */
    public function entriesOf(User $user): Collection;

    /** @return Collection<int, TournamentFixture> by stage, group, round and position, with entries and court */
    public function fixtures(Tournament $tournament): Collection;

    /** @param  list<array<string, mixed>>  $rows */
    public function replaceFixtures(Tournament $tournament, array $rows): void;

    /**
     * Writes the knockout rows in place: entries, winner and status change, an existing schedule stays.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function placeKnockout(Tournament $tournament, array $rows): void;

    public function lockFixture(TournamentFixture $fixture): TournamentFixture;

    public function findFixture(Tournament $tournament, string $stage, int $round, int $position): ?TournamentFixture;

    /** @param  array<string, mixed>  $attributes */
    public function updateFixture(TournamentFixture $fixture, array $attributes): TournamentFixture;

    /** Whether another fixture is scheduled on the court at some moment of [start, end). */
    public function clashes(TournamentFixture $fixture, int $courtId, CarbonInterface $start, CarbonInterface $end): bool;

    /** Whether any fixture has a result entered (byes are drawn, not entered). */
    public function hasResults(Tournament $tournament): bool;

    /** Whether a fixture of the court is scheduled within [start, end). */
    public function hasFixturesIn(Tournament $tournament, int $courtId, CarbonInterface $start, CarbonInterface $end): bool;
}
