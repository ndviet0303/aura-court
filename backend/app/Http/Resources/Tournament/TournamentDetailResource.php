<?php

namespace App\Http\Resources\Tournament;

use App\Domain\Tournament\Rules\TournamentAccess;
use App\Models\Tournament;
use Illuminate\Http\Request;

/**
 * FR-122: everything the tournament page shows of its setup, whether the viewer can apply now, and the viewer's role.
 *
 * @mixin Tournament
 */
class TournamentDetailResource extends TournamentSummaryResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'team_main' => $this->team_main,
            'team_reserve' => $this->team_reserve,
            'group_count' => $this->group_count,
            'advance_per_group' => $this->advance_per_group,
            'games_to_win' => $this->games_to_win,
            'points' => $this->points,
            'tiebreakers' => $this->tiebreakers,
            'rules' => $this->rules,
            'eligibility' => $this->eligibility,
            'athlete_fields' => $this->athlete_fields ?? [],
            'forfeit_rule' => $this->forfeit_rule,
            'prizes' => $this->prizes,
            'stopped_at' => $this->stopped_at?->toIso8601String(),
            'stop_reason' => $this->stop_reason,
            'can_register' => $this->acceptsEntries(),
            'my_role' => TournamentAccess::role($this->resource, $request->user('sanctum')),
        ];
    }
}
