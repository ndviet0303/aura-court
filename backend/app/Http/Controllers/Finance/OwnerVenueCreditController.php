<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ListOwnerVenueCreditsRequest;
use App\Http\Resources\Finance\VenueCreditEntryResource;
use App\Services\Finance\VenueCreditService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** The owner's outstanding venue credit and its entries, for this organization only (design D8). */
class OwnerVenueCreditController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private readonly VenueCreditService $credits) {}

    public function index(ListOwnerVenueCreditsRequest $request): AnonymousResourceCollection
    {
        $organizationId = $this->organization($request)->id;

        return VenueCreditEntryResource::collection($this->credits->entriesOf($organizationId, (int) $request->validated('per_page', 30)))
            ->additional(['outstanding' => $this->credits->outstanding($organizationId)]);
    }
}
