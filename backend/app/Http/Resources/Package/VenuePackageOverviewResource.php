<?php

namespace App\Http\Resources\Package;

use App\Domain\Package\Enums\PackageStatus;
use App\Models\PackagePaymentRequest;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One venue on the owner's package screen: its current package, its live pending request and its request history. */
class VenuePackageOverviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Venue $venue */
        $venue = $this->resource;
        $now = now();
        $package = $venue->currentPackage;
        $requests = $venue->packagePaymentRequests;
        $pending = $requests->first(fn (PackagePaymentRequest $item) => $item->statusAt($now) === PackagePaymentRequest::STATUS_PENDING);

        return [
            'venue_id' => $venue->id,
            'venue_name' => $venue->name,
            'venue_status' => $venue->status,
            'package_status' => ($package?->statusAt($now) ?? PackageStatus::None)->value,
            'package' => $package ? VenuePackageResource::make($package) : null,
            'pending_request' => $pending ? PackagePaymentRequestResource::make($pending) : null,
            'requests' => PackagePaymentRequestResource::collection($requests),
        ];
    }
}
