<?php

namespace App\Domain\Tournament\Support;

use App\Contracts\Repositories\TournamentPlayRepositoryInterface;
use App\Contracts\Repositories\TournamentRepositoryInterface;
use App\Models\Tournament;
use App\Models\TournamentFixture;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * What follows a saved result (FR-129): the knockout winner moves to the next round; once every group fixture is
 * done, the group qualifiers fill the knockout (winner of A against runner-up of B); the tournament is in progress
 * from its first result and finished when nothing is left to play. Call inside the result's transaction.
 */
class TournamentProgress
{
    public function __construct(
        private readonly TournamentRepositoryInterface $tournaments,
        private readonly TournamentPlayRepositoryInterface $play,
    ) {}

    /**
     * @param  array{score: array<string, mixed>|null, winner_entry_id: int|null, forfeit_entry_id: int|null}  $outcome
     */
    public function apply(Tournament $tournament, TournamentFixture $fixture, array $outcome, ?User $actor): TournamentFixture
    {
        $fixture = $this->play->updateFixture($fixture, $outcome + ['status' => TournamentFixture::STATUS_COMPLETED]);
        if ($fixture->stage === TournamentFixture::STAGE_KNOCKOUT) {
            [$round, $position, $column] = Bracket::nextSlot($fixture->round, $fixture->position);
            $next = $this->play->findFixture($tournament, TournamentFixture::STAGE_KNOCKOUT, $round, $position);
            if ($next !== null) {
                $this->play->updateFixture($next, [$column => $outcome['winner_entry_id']]);
            }
        }
        $fixtures = $this->play->fixtures($tournament);
        $groups = $fixtures->where('stage', TournamentFixture::STAGE_GROUP);
        $knockout = $fixtures->where('stage', TournamentFixture::STAGE_KNOCKOUT);
        if ($fixture->stage === TournamentFixture::STAGE_GROUP && (int) $tournament->advance_per_group > 0
            && $groups->every(fn (TournamentFixture $row): bool => $row->status === TournamentFixture::STATUS_COMPLETED)
            && $knockout->where('status', TournamentFixture::STATUS_COMPLETED)->isEmpty()) {
            $this->play->placeKnockout($tournament, Bracket::seeded($this->qualifiers($tournament, $fixtures, $actor)));
            $fixtures = $this->play->fixtures($tournament);
        }

        $open = $fixtures->contains(fn (TournamentFixture $row): bool => $row->status === TournamentFixture::STATUS_PENDING);
        $awaitingKnockout = (int) $tournament->advance_per_group > 0 && $fixtures->where('stage', TournamentFixture::STAGE_KNOCKOUT)->isEmpty();
        $this->tournaments->update($tournament, ['status' => $open || $awaitingKnockout ? Tournament::STATUS_IN_PROGRESS : Tournament::STATUS_FINISHED]);

        return $fixture;
    }

    /**
     * Each group's top `advance_per_group`, best places first. Entries level on the cut line are drawn by lot and
     * the draw is recorded (BR-155: the organizer does not pick a side).
     *
     * @param  Collection<int, TournamentFixture>  $fixtures
     * @return list<array{0: int, 1: string}> [entry id, group]
     */
    private function qualifiers(Tournament $tournament, $fixtures, ?User $actor): array
    {
        $advance = (int) $tournament->advance_per_group;
        $places = [];
        $standings = Standings::compute($tournament, $this->play->entries($tournament, [$tournament->acceptedStatus()]), $fixtures);
        foreach ($standings as $group => $rows) {
            $cut = $rows[min($advance, count($rows)) - 1]['rank'];
            $sure = array_values(array_filter($rows, fn (array $row): bool => $row['rank'] < $cut));
            $tier = array_values(array_filter($rows, fn (array $row): bool => $row['rank'] === $cut));
            $need = min($advance, count($rows)) - count($sure);
            if (count($tier) > $need) {
                $chosen = array_slice(Bracket::shuffle($tier), 0, $need);
                $this->tournaments->recordEvent($tournament, 'knockout.lot_drawn', $actor, null, [
                    'group' => $group,
                    'tied' => array_map(fn (array $row): int => $row['entry']['id'], $tier),
                    'chosen' => array_map(fn (array $row): int => $row['entry']['id'], $chosen),
                ]);
                $tier = $chosen;
            }
            foreach ([...$sure, ...array_slice($tier, 0, $need)] as $place => $row) {
                $places[$place][] = [$row['entry']['id'], (string) $group];
            }
        }
        ksort($places);

        return array_merge(...array_values($places));
    }
}
