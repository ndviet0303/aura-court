<?php

namespace App\Http\Resources\Tournament;

use App\Models\TournamentEntry;
use App\Models\TournamentEntryMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An application or roster entry as its representative and the tournament's managers see it: athletes in full and
 * the representative's contact.
 *
 * @mixin TournamentEntry
 */
class TournamentEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'reject_reason' => $this->reject_reason,
            'group' => $this->group,
            'seed' => $this->seed,
            'representative' => $this->user ? $this->user->only(['id', 'name', 'email', 'phone']) : null,
            'members' => $this->members->map(fn (TournamentEntryMember $member): array => [
                'full_name' => $member->full_name,
                'birth_date' => $member->birth_date->format('Y-m-d'),
                'extra' => (object) ($member->extra ?? []),
                'role' => $member->role,
            ])->all(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
