<?php

namespace App\Http\Resources\Venue;

use App\Models\VenueSuspension;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VenueSuspension */
class VenueSuspensionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'venue_id' => $this->venue_id,
            'venue' => $this->whenLoaded('venue', fn () => [
                'id' => $this->venue->id,
                'name' => $this->venue->name,
                'organization_id' => $this->venue->organization_id,
                'organization' => $this->venue->relationLoaded('organization') ? $this->venue->organization?->only(['id', 'name']) : null,
            ]),
            'reason' => $this->reason,
            'is_active' => $this->lifted_at === null,
            'suspended_at' => $this->suspended_at->toIso8601String(),
            'suspended_by' => $this->whenLoaded('suspendedBy', fn () => $this->suspendedBy?->only(['id', 'name'])),
            'lifted_at' => $this->lifted_at?->toIso8601String(),
            'lifted_by' => $this->whenLoaded('liftedBy', fn () => $this->liftedBy?->only(['id', 'name'])),
            // The conclusion is the review note of the approved submission that lifted the decision (D14).
            'conclusion' => $this->relationLoaded('remediation') ? $this->remediation?->review_note : null,
            // The request's state is its latest submission's; null until the first submission.
            'remediation_status' => $this->relationLoaded('remediations')
                ? $this->remediations->last()?->getAttribute('status')
                : $this->getAttribute('remediation_status'),
            'remediations' => SuspensionRemediationResource::collection($this->whenLoaded('remediations')),
        ];
    }
}
