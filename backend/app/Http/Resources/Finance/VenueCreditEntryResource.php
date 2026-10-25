<?php

namespace App\Http\Resources\Finance;

use App\Models\VenueCreditEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One venue credit move with its source; the customer is named only when the owner's list loaded them. */
class VenueCreditEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var VenueCreditEntry $entry */
        $entry = $this->resource;

        return [
            'id' => $entry->id,
            'type' => $entry->type,
            // A reserve or release moves the same amount between the two columns.
            'amount' => max(abs($entry->balance_delta), abs($entry->reserved_delta)),
            'balance_delta' => $entry->balance_delta,
            'reserved_delta' => $entry->reserved_delta,
            'balance_after' => $entry->balance_after,
            'reserved_after' => $entry->reserved_after,
            'source_type' => $entry->source_type,
            'reference' => $entry->reference,
            'customer' => $this->whenLoaded('user', fn (): array => ['id' => $entry->user_id, 'name' => $entry->user?->name]),
            'created_at' => $entry->created_at?->toISOString(),
        ];
    }
}
