<?php

namespace App\Domain\Tournament\Support;

use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\TournamentFixture;
use Illuminate\Support\Collection;

/**
 * BR-155, D19: group tables computed from completed group fixtures. Points by the tournament's `points`, then its
 * ordered tiebreakers (head_to_head, score_diff, score_for); entries still level share a rank, marked `tied`
 * ("chưa phân định"). A walkover counts as a win and a loss with no score (BR-156).
 */
final class Standings
{
    /**
     * @param  Collection<int, TournamentEntry>  $entries
     * @param  Collection<int, TournamentFixture>  $fixtures
     * @return array<string, list<array<string, mixed>>> keyed by group
     */
    public static function compute(Tournament $tournament, Collection $entries, Collection $fixtures): array
    {
        $tables = [];
        foreach ($entries->whereNotNull('group')->groupBy('group')->sortKeys() as $group => $members) {
            $played = $fixtures->filter(fn (TournamentFixture $fixture): bool => $fixture->stage === TournamentFixture::STAGE_GROUP
                && $fixture->group === $group && $fixture->status === TournamentFixture::STATUS_COMPLETED);
            $tables[(string) $group] = self::table($tournament, $members->values(), $played->values());
        }

        return $tables;
    }

    /**
     * @param  Collection<int, TournamentEntry>  $members
     * @param  Collection<int, TournamentFixture>  $played
     * @return list<array<string, mixed>>
     */
    private static function table(Tournament $tournament, Collection $members, Collection $played): array
    {
        $points = (array) $tournament->points;
        $rows = [];
        foreach ($members as $entry) {
            $rows[$entry->id] = ['entry' => ['id' => (int) $entry->id, 'name' => $entry->name], 'played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0, 'points' => 0, 'score_for' => 0, 'score_against' => 0];
        }
        foreach ($played as $fixture) {
            foreach (self::outcomes($fixture, $points) as $id => [$result, $earned, $for, $against]) {
                if (isset($rows[$id])) {
                    $rows[$id]['played']++;
                    $rows[$id][$result]++;
                    $rows[$id]['points'] += $earned;
                    $rows[$id]['score_for'] += $for;
                    $rows[$id]['score_against'] += $against;
                }
            }
        }
        foreach ($rows as &$row) {
            $row['score_diff'] = $row['score_for'] - $row['score_against'];
        }
        unset($row);

        $criteria = ['points', ...array_values(array_intersect((array) $tournament->tiebreakers, Tournament::TIEBREAKERS))];
        $ordered = [];
        $rank = 1;
        foreach (self::tiers(array_keys($rows), $criteria, $rows, $played, $points) as $tier) {
            usort($tier, fn (int $a, int $b): int => strcmp($rows[$a]['entry']['name'], $rows[$b]['entry']['name']));
            foreach ($tier as $id) {
                $ordered[] = $rows[$id] + ['rank' => $rank, 'tied' => count($tier) > 1];
            }
            $rank += count($tier);
        }

        return $ordered;
    }

    /**
     * Splits entries into ordered tiers by the first criterion, then each tier by the rest.
     *
     * @param  list<int>  $ids
     * @param  list<string>  $criteria
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, TournamentFixture>  $played
     * @param  array<string, int>  $points
     * @return list<list<int>>
     */
    private static function tiers(array $ids, array $criteria, array $rows, Collection $played, array $points): array
    {
        if (count($ids) < 2 || $criteria === []) {
            return [$ids];
        }
        $criterion = array_shift($criteria);
        $values = $criterion === 'head_to_head' ? self::headToHead($ids, $played, $points) : array_map(fn (int $id): int => $rows[$id][$criterion], array_combine($ids, $ids));
        $groups = [];
        foreach ($ids as $id) {
            $groups[$values[$id]][] = $id;
        }
        krsort($groups);
        $tiers = [];
        foreach ($groups as $group) {
            array_push($tiers, ...self::tiers($group, $criteria, $rows, $played, $points));
        }

        return $tiers;
    }

    /**
     * Points earned only in the fixtures between the given entries.
     *
     * @param  list<int>  $ids
     * @param  Collection<int, TournamentFixture>  $played
     * @param  array<string, int>  $points
     * @return array<int, int>
     */
    private static function headToHead(array $ids, Collection $played, array $points): array
    {
        $earned = array_fill_keys($ids, 0);
        foreach ($played as $fixture) {
            if (in_array((int) $fixture->entry_one_id, $ids, true) && in_array((int) $fixture->entry_two_id, $ids, true)) {
                foreach (self::outcomes($fixture, $points) as $id => [, $gained]) {
                    $earned[$id] += $gained;
                }
            }
        }

        return $earned;
    }

    /**
     * @param  array<string, int>  $points
     * @return array<int, array{0: string, 1: int, 2: int, 3: int}> entry id => [result column, points, scored, conceded]
     */
    private static function outcomes(TournamentFixture $fixture, array $points): array
    {
        $one = (int) $fixture->entry_one_id;
        $two = (int) $fixture->entry_two_id;
        [$forOne, $forTwo] = $fixture->forfeit_entry_id ? [0, 0] : Score::totals($fixture);
        $win = (int) ($points['win'] ?? 0);
        $loss = (int) ($points['loss'] ?? 0);
        if ($fixture->winner_entry_id === null) {
            $draw = (int) ($points['draw'] ?? 0);

            return [$one => ['draws', $draw, $forOne, $forTwo], $two => ['draws', $draw, $forTwo, $forOne]];
        }
        $oneWon = (int) $fixture->winner_entry_id === $one;

        return [
            $one => [$oneWon ? 'wins' : 'losses', $oneWon ? $win : $loss, $forOne, $forTwo],
            $two => [$oneWon ? 'losses' : 'wins', $oneWon ? $loss : $win, $forTwo, $forOne],
        ];
    }
}
