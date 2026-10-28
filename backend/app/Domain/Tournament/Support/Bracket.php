<?php

namespace App\Domain\Tournament\Support;

use App\Models\TournamentFixture;
use Random\Randomizer;

/**
 * FR-127, BR-154: the draw. Groups get the shuffled entries in turn and play a single round robin, n(n−1)/2 fixtures
 * each. A knockout uses the smallest bracket of 2^k slots; byes are drawn too, and a bye's entry is already in round 2.
 * The winner of round r position p plays in round r+1 position ceil(p/2).
 */
final class Bracket
{
    /**
     * A cryptographically random order (the default Randomizer engine is the CSPRNG behind random_int).
     *
     * @template T
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    public static function shuffle(array $items): array
    {
        return (new Randomizer)->shuffleArray($items);
    }

    /**
     * @param  list<int>  $entryIds  in drawn order
     * @return array{groups: array<int, string>, fixtures: list<array<string, mixed>>} entry id => group letter
     */
    public static function groups(array $entryIds, int $groupCount): array
    {
        $groups = [];
        $members = [];
        foreach ($entryIds as $index => $id) {
            $letter = chr(65 + $index % $groupCount);
            $groups[$id] = $letter;
            $members[$letter][] = $id;
        }
        $fixtures = [];
        foreach ($members as $letter => $ids) {
            array_push($fixtures, ...self::roundRobin((string) $letter, $ids));
        }

        return ['groups' => $groups, 'fixtures' => $fixtures];
    }

    /**
     * A knockout of drawn entries: the bye positions are drawn among the first-round matches.
     *
     * @param  list<int>  $entryIds  in drawn order
     * @return list<array<string, mixed>>
     */
    public static function knockout(array $entryIds): array
    {
        $size = self::size(count($entryIds));
        $byes = array_flip(array_slice(self::shuffle(range(0, intdiv($size, 2) - 1)), 0, $size - count($entryIds)));
        $pairs = [];
        $next = 0;
        for ($match = 0; $match < intdiv($size, 2); $match++) {
            $pairs[] = isset($byes[$match]) ? [$entryIds[$next++], null] : [$entryIds[$next++], $entryIds[$next++]];
        }

        return self::rounds($pairs);
    }

    /**
     * A knockout of group qualifiers, best seed first, placed so seed s meets seed N+1−s: with two groups, the winner
     * of A meets the runner-up of B. Missing seeds are byes for the best seeds. Two entries of one group are kept apart
     * in round 1 where swapping the lower seeds of two matches allows it.
     *
     * @param  list<array{0: int, 1: string}>  $seeds  [entry id, group]
     * @return list<array<string, mixed>>
     */
    public static function seeded(array $seeds): array
    {
        $size = self::size(count($seeds));
        $order = [1, 2];
        while (count($order) < $size) {
            $order = collect($order)->flatMap(fn (int $seed): array => [$seed, 2 * count($order) + 1 - $seed])->all();
        }
        $pairs = [];
        foreach (array_chunk($order, 2) as [$one, $two]) {
            $pairs[] = [$seeds[$one - 1], $seeds[$two - 1] ?? null];
        }
        $clash = fn (array $pair): bool => $pair[1] !== null && $pair[0][1] === $pair[1][1];
        foreach (array_keys($pairs) as $i) {
            foreach (array_keys($pairs) as $j) {
                if ($clash($pairs[$i]) && $i !== $j && $pairs[$j][1] !== null
                    && ! $clash([$pairs[$i][0], $pairs[$j][1]]) && ! $clash([$pairs[$j][0], $pairs[$i][1]])) {
                    [$pairs[$i][1], $pairs[$j][1]] = [$pairs[$j][1], $pairs[$i][1]];
                }
            }
        }

        return self::rounds(array_map(fn (array $pair): array => [$pair[0][0], $pair[1][0] ?? null], $pairs));
    }

    /**
     * UC-7.31, UC-7.33: whether $later takes the result of $earlier. A knockout fixture takes its feeders of every earlier
     * round; the knockout after the groups takes every group fixture.
     */
    public static function feeds(TournamentFixture $earlier, TournamentFixture $later): bool
    {
        if ($later->stage !== TournamentFixture::STAGE_KNOCKOUT) {
            return false;
        }
        if ($earlier->stage === TournamentFixture::STAGE_GROUP) {
            return true;
        }
        $gap = $later->round - $earlier->round;

        return $gap > 0 && (int) ceil($earlier->position / 2 ** $gap) === $later->position;
    }

    /** The next fixture's slot for the winner of round r position p: [round, position, column]. */
    public static function nextSlot(int $round, int $position): array
    {
        return [$round + 1, (int) ceil($position / 2), $position % 2 === 1 ? 'entry_one_id' : 'entry_two_id'];
    }

    private static function size(int $count): int
    {
        $size = 2;
        while ($size < $count) {
            $size *= 2;
        }

        return $size;
    }

    /**
     * @param  list<array{0: int, 1: int|null}>  $pairs  first-round pairs; null is a bye
     * @return list<array<string, mixed>>
     */
    private static function rounds(array $pairs): array
    {
        $rows = [];
        foreach ($pairs as $index => [$one, $two]) {
            $rows['1:'.($index + 1)] = array_merge(
                self::row(TournamentFixture::STAGE_KNOCKOUT, '', 1, $index + 1, $one, $two),
                $two === null ? ['winner_entry_id' => $one, 'status' => TournamentFixture::STATUS_BYE] : [],
            );
        }
        for ($round = 2, $count = intdiv(count($pairs), 2); $count >= 1; $round++, $count = intdiv($count, 2)) {
            for ($position = 1; $position <= $count; $position++) {
                $rows["{$round}:{$position}"] = self::row(TournamentFixture::STAGE_KNOCKOUT, '', $round, $position, null, null);
            }
        }
        foreach ($pairs as $index => [$one, $two]) {
            if ($two === null && count($pairs) > 1) {
                [$round, $position, $column] = self::nextSlot(1, $index + 1);
                $rows["{$round}:{$position}"][$column] = $one;
            }
        }

        return array_values($rows);
    }

    /**
     * Circle method: one entry stays, the others rotate; an odd group rests one entry each round.
     *
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    private static function roundRobin(string $group, array $ids): array
    {
        if (count($ids) % 2 === 1) {
            $ids[] = null;
        }
        $count = count($ids);
        $rows = [];
        for ($round = 1; $round < $count; $round++) {
            $position = 0;
            for ($i = 0; $i < intdiv($count, 2); $i++) {
                [$one, $two] = [$ids[$i], $ids[$count - 1 - $i]];
                if ($one !== null && $two !== null) {
                    $rows[] = self::row(TournamentFixture::STAGE_GROUP, $group, $round, ++$position, $one, $two);
                }
            }
            $ids = [$ids[0], $ids[$count - 1], ...array_slice($ids, 1, $count - 2)];
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private static function row(string $stage, string $group, int $round, int $position, ?int $one, ?int $two): array
    {
        return [
            'stage' => $stage, 'group' => $group, 'round' => $round, 'position' => $position,
            'entry_one_id' => $one, 'entry_two_id' => $two, 'winner_entry_id' => null, 'status' => TournamentFixture::STATUS_PENDING,
        ];
    }
}
