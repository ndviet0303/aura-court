<?php

namespace App\Http\Resources\Package;

use App\Domain\Package\Enums\PackageStatus;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A venue's package status at the time of viewing for the platform admin (D15); the detail adds its package and request history. */
class PlatformFeeVenueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Venue $venue */
        $venue = $this->resource;
        $package = $venue->currentPackage;
        $owner = $venue->organization?->owner;

        return [
            'venue_id' => $venue->id,
            'venue_name' => $venue->name,
            'venue_status' => $venue->status,
            'organization' => ['id' => $venue->organization_id, 'name' => $venue->organization?->name],
            'owner' => $owner ? ['id' => $owner->id, 'name' => $owner->name, 'email' => $owner->email] : null,
            'package_status' => ($package?->statusAt(now()) ?? PackageStatus::None)->value,
            'package' => $package ? VenuePackageResource::make($package) : null,
            'packages' => $this->whenLoaded('packages', fn () => VenuePackageResource::collection($venue->packages)),
            'requests' => $this->whenLoaded('packagePaymentRequests', fn () => PlatformFeeRequestResource::collection($venue->packagePaymentRequests)),
        ];
    }
}
