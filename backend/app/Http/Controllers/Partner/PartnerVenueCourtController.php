<?php

namespace App\Http\Controllers\Partner;

use App\Contracts\Repositories\VenueLifecycleRepositoryInterface;
use App\Http\Controllers\Concerns\AuthorizesVenueRead;
use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Services\Reservation\VenueOperatingCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Read-only court detail for the owner and the staff assigned to its venue (BR-31, organization-scoped reads). */
class PartnerVenueCourtController extends Controller
{
    use AuthorizesVenueRead;

    public function __construct(
        private readonly VenueLifecycleRepositoryInterface $venueRepo,
        private readonly VenueOperatingCalendar $calendar,
    ) {}

    public function show(Request $request, int $organizationId, int $venueId, int $courtId): JsonResponse
    {
        $court = $this->authorizeVenueRead($request, $organizationId, $venueId)->courts->firstWhere('id', $courtId);
        abort_unless($court instanceof Court, 404, 'Sân không thuộc cơ sở này.');
        $service = $this->calendar->serviceSchedule($court);

        return response()->json(['data' => [
            ...$court->toArray(),
            'service_schedule' => $service['schedule'],
            'service_schedule_inherited' => $service['inherited'],
        ]]);
    }
}
