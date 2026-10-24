<?php

namespace App\Domain\Tournament\DTOs;

use App\Models\TournamentEntryMember;

/** BR-150: an entry's name and its athletes; athletes need no account. */
readonly class EntryData
{
    /** @param  list<array{full_name: string, birth_date: string, extra: array<string, string>, role: string}>  $members */
    public function __construct(public string $name, public array $members) {}

    /** @param  array<string, mixed>  $validated  `name` and `members` as the entry request validated them */
    public static function from(array $validated): self
    {
        return new self(
            trim((string) $validated['name']),
            array_values(array_map(fn (array $member): array => [
                'full_name' => trim((string) $member['full_name']),
                'birth_date' => (string) $member['birth_date'],
                'extra' => array_map(fn ($value): string => trim((string) $value), (array) ($member['extra'] ?? [])),
                'role' => (string) ($member['role'] ?? TournamentEntryMember::ROLE_MAIN),
            ], (array) $validated['members'])),
        );
    }
}
