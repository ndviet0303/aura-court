<?php

namespace Database\Seeders\Concerns;

use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Domain\Tournament\Actions\AllocateTournamentCourt;
use App\Domain\Tournament\Actions\ConfirmEntryPayment;
use App\Domain\Tournament\Actions\DrawTournament;
use App\Domain\Tournament\Actions\LockRoster;
use App\Domain\Tournament\Actions\RecordFixtureResult;
use App\Domain\Tournament\Actions\ReviewEntry;
use App\Domain\Tournament\Actions\ScheduleFixture;
use App\Domain\Tournament\Actions\SubmitEntry;
use App\Domain\Tournament\Actions\WithdrawEntry;
use App\Domain\Tournament\DTOs\EntryData;
use App\Models\Court;
use App\Models\OwnerPaymentAllocation;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\TournamentEntryMember;
use App\Models\TournamentFixture;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Drives demo tournaments through the real actions with the clock moved to each step, so entries, payments, draws,
 * standings, brackets and history are what the app itself would have written. Needs FindsFreeCourtTime and
 * PaysVenueOwner (its clock and owner payments) on the same seeder.
 */
trait PlaysTournaments
{
    /** Badminton games from the winner's side, cycled by fixture. */
    private const GAMES = [[[21, 15], [21, 18]], [[21, 19], [17, 21], [21, 16]], [[23, 21], [21, 14]], [[18, 21], [21, 19], [22, 20]], [[21, 12], [21, 17]]];

    /** Football [goals, shoot-out] from the winner's side; the level one is for knockout fixtures only. */
    private const GOALS = [[[2, 1], null], [[1, 1], [4, 3]], [[3, 2], null], [[2, 0], null]];

    private const FAMILY = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Vũ', 'Đặng', 'Bùi', 'Đỗ', 'Ngô', 'Dương', 'Lý'];

    private const MIDDLE = ['Văn', 'Minh', 'Quốc', 'Đức', 'Thành', 'Hữu', 'Anh', 'Gia', 'Ngọc', 'Thanh'];

    private const GIVEN = ['An', 'Bình', 'Cường', 'Dũng', 'Hải', 'Hùng', 'Khoa', 'Long', 'Nam', 'Phong', 'Quân', 'Sơn', 'Tâm', 'Thắng', 'Trung', 'Tuấn', 'Việt', 'Vinh', 'Khánh', 'Lộc'];

    /** Reserves [start, end) on the first $count courts of the tournament's sport that nothing else holds. */
    protected function allocate(Tournament $tournament, User $owner, Carbon $start, Carbon $end, int $count): void
    {
        $repository = app(TournamentRepositoryInterface::class);
        $courts = $repository->courts($tournament)
            ->filter(fn (Court $court): bool => $this->isFree($court->id, $start, $end) && ! $repository->courtTimeTaken($court->id, $start, $end))
            ->take($count);
        if ($courts->count() < $count) {
            throw new RuntimeException("No {$count} free courts for {$tournament->name} at {$start}.");
        }
        foreach ($courts as $court) {
            app(AllocateTournamentCourt::class)->execute($tournament, $owner, $court->id, $start, $end);
        }
    }

    /** An application submitted at $at and taken to $status: reviewed the next morning, then paid or withdrawn. */
    protected function enter(Tournament $tournament, User $owner, User $user, string $name, int $index, Carbon $at, string $status, ?string $reason = null): TournamentEntry
    {
        $entry = $this->atClock($at, fn () => app(SubmitEntry::class)->execute($tournament, $user, $this->entryData($tournament, $name, $index, $user)));
        if ($status === TournamentEntry::STATUS_SUBMITTED) {
            return $entry;
        }
        $reviewedAt = $at->copy()->addDay()->setTime(9, 30);
        $approve = $status !== TournamentEntry::STATUS_REJECTED;
        $entry = $this->atClock($reviewedAt, fn () => app(ReviewEntry::class)->execute($entry, $owner, $approve, $approve ? null : $reason));
        if ($status === TournamentEntry::STATUS_PAID) {
            $paidAt = $reviewedAt->copy()->addHour();
            $attempt = $this->payOwner(Venue::findOrFail($tournament->venue_id), $user, ConfirmEntryPayment::PURPOSE, (string) $entry->id, (int) $tournament->fee, $paidAt->copy()->subMinutes(4), $paidAt);
            $allocation = OwnerPaymentAllocation::where('payment_attempt_id', $attempt->id)->firstOrFail();
            $this->atClock($paidAt, fn () => app(ConfirmEntryPayment::class)->confirmPaidAttempt($allocation, $paidAt));
        }
        if ($status === TournamentEntry::STATUS_WITHDRAWN) {
            $this->atClock($reviewedAt->copy()->addHours(5), fn () => app(WithdrawEntry::class)->execute($entry, $user));
        }

        return $entry->refresh();
    }

    /** The unit's athletes: the representative first when given, then generated names; a team brings two reserves. */
    protected function entryData(Tournament $tournament, string $name, int $index, ?User $user = null): EntryData
    {
        [$main, $reserve] = match ($tournament->entry_type) {
            'single' => [1, 0],
            'double' => [2, 0],
            default => [(int) $tournament->team_main, min(2, (int) $tournament->team_reserve)],
        };
        $members = [];
        for ($p = 0; $p < $main + $reserve; $p++) {
            $members[] = [
                'full_name' => $p === 0 && $user ? $user->name : $this->person($index * 10 + $p),
                'birth_date' => sprintf('%d-%02d-%02d', 1984 + ($index * 3 + $p) % 22, 1 + ($index + $p * 5) % 12, 1 + ($index * 7 + $p) % 28),
                'extra' => array_fill_keys((array) $tournament->athlete_fields, (string) ($p + 1)),
                'role' => $p < $main ? TournamentEntryMember::ROLE_MAIN : TournamentEntryMember::ROLE_RESERVE,
            ];
        }

        return new EntryData($name, $members);
    }

    protected function person(int $i): string
    {
        return self::FAMILY[$i % count(self::FAMILY)].' '.self::MIDDLE[intdiv($i, 3) % count(self::MIDDLE)].' '.self::GIVEN[($i * 7) % count(self::GIVEN)];
    }

    protected function lockAndDraw(Tournament $tournament, User $locker, User $drawer, Carbon $lockAt, Carbon $drawAt): void
    {
        $this->atClock($lockAt, fn () => app(LockRoster::class)->execute($tournament, $locker));
        $this->atClock($drawAt, fn () => app(DrawTournament::class)->execute($tournament->refresh(), $drawer));
    }

    /**
     * Gives the fixtures consecutive $minutes slots inside the court time (allocations or rental slots), earliest
     * first with parallel courts side by side; fixtures past the last slot stay unscheduled.
     *
     * @param  Collection<int, TournamentFixture>  $fixtures
     * @param  Collection<int, object>  $windows  rows with court_id, starts_at and ends_at
     */
    protected function schedule(User $actor, Collection $fixtures, Collection $windows, int $minutes, Carbon $at): void
    {
        $slots = [];
        foreach ($windows as $window) {
            for ($start = $window->starts_at->copy(); $start->copy()->addMinutes($minutes)->lte($window->ends_at); $start->addMinutes($minutes)) {
                $slots[] = [(int) $window->court_id, $start->copy()];
            }
        }
        usort($slots, fn (array $a, array $b): int => [$a[1]->getTimestamp(), $a[0]] <=> [$b[1]->getTimestamp(), $b[0]]);
        foreach (array_slice($fixtures->values()->all(), 0, count($slots)) as $i => $fixture) {
            [$court, $start] = $slots[$i];
            $this->atClock($at, fn () => app(ScheduleFixture::class)->execute($fixture, $actor, $court, $start, $start->copy()->addMinutes($minutes)));
        }
    }

    /** Records the fixture's result when it ends; the earlier-registered entry wins. */
    protected function play(User $actor, TournamentFixture $fixture, int $variant): void
    {
        $fixture->refresh();
        $input = $this->score($fixture->tournament->sport, (int) $fixture->entry_one_id < (int) $fixture->entry_two_id, $variant);
        $this->atClock($fixture->ends_at->copy(), fn () => app(RecordFixtureResult::class)->execute($fixture, $actor, $input));
    }

    /** @return array<string, mixed> the result input from entry one's side */
    protected function score(string $sport, bool $oneWins, int $variant): array
    {
        if ($sport === 'badminton') {
            return ['games' => $this->side($oneWins, self::GAMES[$variant % count(self::GAMES)])];
        }
        [$goals, $shootout] = self::GOALS[$variant % count(self::GOALS)];

        return ['goals' => $this->side($oneWins, [$goals])[0], 'shootout' => $shootout ? $this->side($oneWins, [$shootout])[0] : null];
    }

    /**
     * @param  list<array{0: int, 1: int}>  $pairs  from the winner's side
     * @return list<array{0: int, 1: int}> from entry one's side
     */
    protected function side(bool $oneWins, array $pairs): array
    {
        return array_map(fn (array $pair): array => $oneWins ? $pair : array_reverse($pair), $pairs);
    }
}
