<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\PlatformFeeVenueRepositoryInterface;
use App\Domain\Package\DTOs\PlatformFeeVenueFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListPlatformFeeVenuesRequest;
use App\Http\Resources\Package\PlatformFeeVenueResource;
use App\Services\Package\PlatformFeeExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Venue package status at the time of viewing for the platform admin (D15, BR-116). */
class PlatformFeeVenueController extends Controller
{
    public function __construct(private readonly PlatformFeeVenueRepositoryInterface $venues) {}

    public function index(ListPlatformFeeVenuesRequest $request): AnonymousResourceCollection
    {
        $now = now();

        return PlatformFeeVenueResource::collection($this->venues->paginate(PlatformFeeVenueFilterData::fromRequest($request), $now))
            ->additional(['expiring_counts' => $this->venues->expiringCounts($now)]);
    }

    public function export(ListPlatformFeeVenuesRequest $request, PlatformFeeExport $export): StreamedResponse
    {
        return $export->venues($request);
    }

    public function show(int $venueId): JsonResponse
    {
        $venue = $this->venues->findWithHistory($venueId);
        abort_if($venue === null, 404, 'Cơ sở không tồn tại.');

        return response()->json(['data' => PlatformFeeVenueResource::make($venue)]);
    }
}
