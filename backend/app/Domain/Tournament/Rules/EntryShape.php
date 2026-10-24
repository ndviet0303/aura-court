<?php

namespace App\Domain\Tournament\Rules;

use App\Domain\Tournament\DTOs\EntryData;
use App\Models\Tournament;
use App\Models\TournamentEntryMember;
use Illuminate\Validation\ValidationException;

/**
 * FR-124, BR-150: an entry's athletes fit the event. Single: one athlete; double: two; team: `team_main` main
 * athletes and at most `team_reserve` reserves. Each athlete carries every extra field the tournament asks for.
 * Used by public registration, direct roster entry and the roster spreadsheet alike.
 */
final class EntryShape
{
    /** @return list<array{field: string, message: string}> */
    public static function errors(Tournament $tournament, EntryData $entry): array
    {
        $errors = [];
        $main = count(array_filter($entry->members, fn (array $member): bool => $member['role'] === TournamentEntryMember::ROLE_MAIN));
        $reserve = count($entry->members) - $main;
        [$needMain, $maxReserve] = match ($tournament->entry_type) {
            'single' => [1, 0],
            'double' => [2, 0],
            default => [(int) $tournament->team_main, (int) $tournament->team_reserve],
        };
        if ($main !== $needMain || $reserve > $maxReserve) {
            $errors[] = ['field' => 'members', 'message' => $maxReserve > 0
                ? "Cần đúng {$needMain} vận động viên chính thức và tối đa {$maxReserve} dự bị."
                : "Cần đúng {$needMain} vận động viên."];
        }
        foreach ($entry->members as $index => $member) {
            $missing = array_values(array_filter((array) $tournament->athlete_fields, fn (string $field): bool => trim((string) ($member['extra'][$field] ?? '')) === ''));
            if ($missing !== []) {
                $errors[] = ['field' => "members.{$index}.extra", 'message' => 'Thiếu thông tin: '.implode(', ', $missing).'.'];
            }
        }

        return $errors;
    }

    public static function assert(Tournament $tournament, EntryData $entry): void
    {
        $errors = self::errors($tournament, $entry);
        if ($errors !== []) {
            throw ValidationException::withMessages(collect($errors)->groupBy('field')->map->pluck('message')->all());
        }
    }
}
