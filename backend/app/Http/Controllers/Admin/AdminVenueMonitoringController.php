<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Actions\ListMonitoredVenues;
use App\Domain\Admin\Actions\ShowMonitoredVenue;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListMonitoredVenuesRequest;
use App\Http\Resources\Admin\MonitoredVenueResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** D13: read-only; there is deliberately no write route here. Venue edits stay with the owner (403 for admins). */
class AdminVenueMonitoringController extends Controller
{
    public function index(ListMonitoredVenuesRequest $request, ListMonitoredVenues $action): AnonymousResourceCollection
    {
        return MonitoredVenueResource::collection($action->execute($request->user(), $request->filters()));
    }

    public function show(Request $request, int $venueId, ShowMonitoredVenue $action): JsonResponse
    {
        return response()->json(['data' => new MonitoredVenueResource($action->execute($request->user(), $venueId))]);
    }
}
