<?php

namespace App\Http\Resources\Package;

use App\Models\VenuePackage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A venue package row with its status derived at the time of reading (D1). */
class VenuePackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var VenuePackage $package */
        $package = $this->resource;

        return [
            'id' => $package->id,
            'venue_id' => $package->venue_id,
            'term_days' => $package->term_days->value,
            'starts_at' => $package->starts_at->toISOString(),
            'ends_at' => $package->ends_at->toISOString(),
            'status' => $package->statusAt(now())->value,
            'terminated_at' => $package->terminated_at?->toISOString(),
        ];
    }
}
