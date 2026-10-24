<?php

namespace App\Domain\Tournament\DTOs;

use App\Models\Tournament;
use Illuminate\Support\Carbon;

/**
 * FR-120: a validated setup as tournament columns. Fields that do not apply are cleared: team sizes outside team
 * play, group settings outside the group format, games to win outside badminton.
 */
readonly class TournamentSetupData
{
    /** @param  array<string, mixed>  $attributes */
    public function __construct(public array $attributes) {}

    /** @param  array<string, mixed>  $validated */
    public static function from(array $validated, bool $public): self
    {
        $team = $validated['entry_type'] === 'team';
        $group = $validated['format'] === Tournament::FORMAT_GROUP;
        $date = fn (string $key): ?Carbon => isset($validated[$key]) ? Carbon::parse($validated[$key])->setTimezone(config('app.timezone')) : null;

        return new self([
            'name' => trim((string) $validated['name']),
            'sport' => $validated['sport'],
            'entry_type' => $validated['entry_type'],
            'team_main' => $team ? (int) $validated['team_main'] : null,
            'team_reserve' => $team ? (int) $validated['team_reserve'] : null,
            'format' => $validated['format'],
            'group_count' => $group ? (int) $validated['group_count'] : null,
            'advance_per_group' => $group ? (int) $validated['advance_per_group'] : null,
            'games_to_win' => $validated['sport'] === 'badminton' ? (int) $validated['games_to_win'] : null,
            'points' => array_map('intval', $validated['points']),
            'tiebreakers' => array_values($validated['tiebreakers']),
            'rules' => trim((string) $validated['rules']),
            'eligibility' => $validated['eligibility'] ?? null,
            'athlete_fields' => array_values(array_map(fn ($field): string => trim((string) $field), $validated['athlete_fields'])),
            'forfeit_rule' => trim((string) $validated['forfeit_rule']),
            'prizes' => $validated['prizes'] ?? null,
        ] + ($public ? [
            'max_entries' => (int) $validated['max_entries'],
            'fee' => (int) $validated['fee'],
            'registration_deadline' => $date('registration_deadline'),
            'payment_deadline' => $date('payment_deadline'),
            'starts_at' => $date('starts_at'),
            'ends_at' => $date('ends_at'),
        ] : []));
    }
}
