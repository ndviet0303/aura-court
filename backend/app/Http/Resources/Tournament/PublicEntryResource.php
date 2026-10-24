<?php

namespace App\Http\Resources\Tournament;

use App\Models\TournamentEntry;
use App\Models\TournamentEntryMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * FR-122: on the public page a unit shows only its name and its athletes' names and roles, never the account.
 *
 * @mixin TournamentEntry
 */
class PublicEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'group' => $this->group,
            'seed' => $this->seed,
            'members' => $this->members->map(fn (TournamentEntryMember $member): array => ['full_name' => $member->full_name, 'role' => $member->role])->all(),
        ];
    }
}
