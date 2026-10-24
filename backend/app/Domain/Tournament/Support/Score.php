<?php

namespace App\Domain\Tournament\Support;

use App\Domain\Tournament\Rules\TournamentAccess;
use App\Models\Tournament;
use App\Models\TournamentFixture;

/**
 * FR-129, BR-155: a result by sport, the winner derived by the server. Badminton: games, first to `games_to_win`,
 * no draw. Football: goals; a draw stands in the group stage, and a knockout draw needs a deciding shoot-out.
 */
final class Score
{
    /**
     * @param  array<string, mixed>  $input  `games` for badminton, `goals` and `shootout` for football
     * @return array{score: array<string, mixed>, winner: int} winner 1 or 2 for a side, 0 for a draw
     */
    public static function judge(Tournament $tournament, string $stage, array $input): array
    {
        return $tournament->sport === 'badminton'
            ? self::badminton(max(1, (int) $tournament->games_to_win), $input['games'] ?? null)
            : self::football($stage, $input['goals'] ?? null, $input['shootout'] ?? null);
    }

    /**
     * A fixture's outcome from a score, or a walkover when `absent_entry_id` names the side that did not play (BR-156).
     *
     * @param  array<string, mixed>  $input
     * @return array{score: array<string, mixed>|null, winner_entry_id: int|null, forfeit_entry_id: int|null}
     */
    public static function outcome(Tournament $tournament, TournamentFixture $fixture, array $input): array
    {
        $sides = [1 => (int) $fixture->entry_one_id, 2 => (int) $fixture->entry_two_id];
        if (isset($input['absent_entry_id'])) {
            $absent = array_search((int) $input['absent_entry_id'], $sides, true);
            if ($absent === false) {
                self::invalid('Bên vắng mặt phải là một trong hai đơn vị của trận.');
            }

            return ['score' => null, 'winner_entry_id' => $sides[3 - $absent], 'forfeit_entry_id' => $sides[$absent]];
        }
        $judged = self::judge($tournament, $fixture->stage, $input);

        return ['score' => $judged['score'], 'winner_entry_id' => $sides[$judged['winner']] ?? null, 'forfeit_entry_id' => null];
    }

    /** @return array{0: int, 1: int} what each side scored for the standings: games won, or goals (shoot-out left out) */
    public static function totals(TournamentFixture $fixture): array
    {
        $score = (array) $fixture->score;
        if (isset($score['goals'])) {
            return [(int) $score['goals'][0], (int) $score['goals'][1]];
        }
        $games = collect($score['games'] ?? []);

        return [$games->filter(fn (array $game): bool => $game[0] > $game[1])->count(), $games->filter(fn (array $game): bool => $game[1] > $game[0])->count()];
    }

    /** @return array{score: array<string, mixed>, winner: int} */
    private static function badminton(int $toWin, mixed $games): array
    {
        if (! is_array($games) || $games === [] || count($games) > 2 * $toWin - 1) {
            self::invalid("Nhập tỷ số từng ván; trận thắng khi đủ {$toWin} ván.");
        }
        $won = [0, 0];
        $clean = [];
        foreach (array_values($games) as $game) {
            if (max($won) === $toWin) {
                self::invalid('Trận đã có bên thắng; không nhập thêm ván sau đó.');
            }
            [$one, $two] = self::pair($game);
            if ($one === $two) {
                self::invalid('Một ván cầu lông không thể hoà.');
            }
            $won[$one > $two ? 0 : 1]++;
            $clean[] = [$one, $two];
        }
        if (max($won) !== $toWin) {
            self::invalid("Trận chưa có bên thắng; cần đủ {$toWin} ván thắng.");
        }

        return ['score' => ['games' => $clean], 'winner' => $won[0] === $toWin ? 1 : 2];
    }

    /** @return array{score: array<string, mixed>, winner: int} */
    private static function football(string $stage, mixed $goals, mixed $shootout): array
    {
        [$one, $two] = self::pair($goals);
        $knockout = $stage === TournamentFixture::STAGE_KNOCKOUT;
        if ($shootout !== null && ! ($knockout && $one === $two)) {
            self::invalid('Luân lưu chỉ dùng khi trận loại trực tiếp hoà.');
        }
        if ($one !== $two) {
            return ['score' => ['goals' => [$one, $two], 'shootout' => null], 'winner' => $one > $two ? 1 : 2];
        }
        if (! $knockout) {
            return ['score' => ['goals' => [$one, $two], 'shootout' => null], 'winner' => 0];
        }
        if ($shootout === null) {
            self::invalid('Trận loại trực tiếp hoà cần tỷ số luân lưu.');
        }
        [$penOne, $penTwo] = self::pair($shootout);
        if ($penOne === $penTwo) {
            self::invalid('Luân lưu phải phân định bên thắng.');
        }

        return ['score' => ['goals' => [$one, $two], 'shootout' => [$penOne, $penTwo]], 'winner' => $penOne > $penTwo ? 1 : 2];
    }

    /** @return array{0: int, 1: int} */
    private static function pair(mixed $value): array
    {
        if (! is_array($value) || count($value) !== 2 || ! array_is_list($value)) {
            self::invalid('Tỷ số phải gồm hai số.');
        }
        foreach ($value as $number) {
            if (! is_int($number) && ! (is_string($number) && ctype_digit($number))) {
                self::invalid('Tỷ số phải là số nguyên không âm.');
            }
            if ((int) $number > 99 || (int) $number < 0) {
                self::invalid('Tỷ số không hợp lệ.');
            }
        }

        return [(int) $value[0], (int) $value[1]];
    }

    private static function invalid(string $message): never
    {
        TournamentAccess::fail('RESULT_INVALID', $message);
    }
}
