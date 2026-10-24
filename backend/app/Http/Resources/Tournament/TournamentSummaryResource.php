<?php

namespace App\Http\Resources\Tournament;

use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * FR-121: a tournament in a list. `accepted_count` is the entries holding a place (paid, or the internal roster).
 *
 * @mixin Tournament
 */
class TournamentSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'type' => $this->type,
            'name' => $this->name,
            'sport' => $this->sport,
            'entry_type' => $this->entry_type,
            'format' => $this->format,
            'status' => $this->status,
            'venue' => ['id' => $this->venue->id, 'name' => $this->venue->name, 'address' => $this->venue->address],
            'fee' => (int) $this->fee,
            'max_entries' => $this->max_entries,
            'accepted_count' => (int) $this->resource->getAttribute('accepted_count'),
            'registration_deadline' => $this->registration_deadline?->toIso8601String(),
            'payment_deadline' => $this->payment_deadline?->toIso8601String(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
        ];
    }
}
