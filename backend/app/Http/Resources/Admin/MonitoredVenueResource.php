<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\Venue\VenueSuspensionResource;
use App\Models\Court;
use App\Models\OrganizationMember;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** D13 read-only monitoring view of a venue; detail relations appear only when the repository loaded them. */
class MonitoredVenueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Venue $venue */
        $venue = $this->resource;
        $schedules = $venue->relationLoaded('operatingSchedules') ? $venue->operatingSchedules->keyBy('scope_key') : null;

        return [
            'id' => $venue->id,
            'name' => $venue->name,
            'organization' => $venue->organization?->only(['id', 'name']),
            'address' => $venue->address,
            'phone' => $venue->phone,
            'email' => $venue->email,
            'status' => $venue->status,
            'courts_count' => $this->when(isset($venue->courts_count), fn () => (int) $venue->courts_count),
            'active_suspension' => $venue->relationLoaded('activeSuspension') && $venue->activeSuspension
                ? $venue->activeSuspension->only(['id', 'reason', 'suspended_at'])
                : ($venue->relationLoaded('suspensions') ? $venue->suspensions->firstWhere('lifted_at', null)?->only(['id', 'reason', 'suspended_at']) : null),
            'owners' => $this->when($venue->organization?->relationLoaded('members') === true, fn () => $venue->organization->members
                ->map(fn ($member): array => $member instanceof OrganizationMember ? ($member->user?->only(['id', 'name', 'email', 'phone']) ?? []) : [])->values()),
            // A Collection keeps the weekday keys 1..7; JsonResource re-indexes nested numeric-keyed arrays.
            'operating_hours' => $this->when($schedules !== null, fn () => ($hours = $schedules->get('venue')?->weekly_hours) === null ? null : collect($hours)),
            'courts' => $this->whenLoaded('courts', fn () => $venue->courts->map(fn (Court $court): array => [
                'id' => $court->id,
                'code' => $court->code,
                'sport_type' => $court->sport_type,
                'description' => $court->description,
                'images' => $court->images ?? [],
                'status' => $court->status,
                // UC-2.7 A-1: the court's own schedule only, null when not set.
                'service_schedule' => $schedules?->get('court:'.$court->id)?->weekly_hours,
            ])->values()),
            'suspensions' => VenueSuspensionResource::collection($this->whenLoaded('suspensions')),
        ];
    }
}
