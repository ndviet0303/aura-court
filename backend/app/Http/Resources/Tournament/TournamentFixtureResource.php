<?php

namespace App\Http\Resources\Tournament;

use App\Models\TournamentFixture;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * FR-128, FR-129: a fixture with its sides, court time and result. `forfeit_entry_id` is the side that lost by walkover.
 *
 * @mixin TournamentFixture
 */
class TournamentFixtureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stage' => $this->stage,
            'group' => $this->group === '' ? null : $this->group,
            'round' => $this->round,
            'position' => $this->position,
            'entry_one' => $this->entryOne?->only(['id', 'name']),
            'entry_two' => $this->entryTwo?->only(['id', 'name']),
            'winner_entry_id' => $this->winner_entry_id,
            'forfeit_entry_id' => $this->forfeit_entry_id,
            'court' => $this->court?->only(['id', 'code']),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'status' => $this->status,
            'score' => $this->score,
        ];
    }
}
