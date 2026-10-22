<?php

namespace App\Http\Controllers\Partner;

use App\Domain\Venue\Actions\SaveVenueOperatingSchedule;
use App\Domain\Venue\Actions\SaveVenueScheduleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\DeleteVenueScheduleExceptionRequest;
use App\Http\Requests\Partner\SaveVenueOperatingScheduleRequest;
use App\Http\Requests\Partner\SaveVenueScheduleExceptionRequest;
use Illuminate\Http\JsonResponse;

/** Design D7 previews: the bookings and owner-hosted sessions a schedule change would affect. Nothing is saved. */
class VenueScheduleImpactController extends Controller
{
    public function operatingSchedule(SaveVenueOperatingScheduleRequest $request, int $organizationId, int $venueId, SaveVenueOperatingSchedule $action): JsonResponse
    {
        return response()->json(['data' => $action->preview($venueId, (int) $request->user()->id, $request->validated())]);
    }

    public function scheduleException(SaveVenueScheduleExceptionRequest $request, int $organizationId, int $venueId, SaveVenueScheduleException $action): JsonResponse
    {
        return response()->json(['data' => $action->preview($venueId, (int) $request->user()->id, $request->validated())]);
    }

    public function deleteScheduleException(DeleteVenueScheduleExceptionRequest $request, int $organizationId, int $venueId, int $exceptionId, SaveVenueScheduleException $action): JsonResponse
    {
        return response()->json(['data' => $action->previewDelete($venueId, $exceptionId)]);
    }
}
