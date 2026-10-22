<?php

namespace App\Http\Controllers\Reservation;

use App\Domain\Reservation\Actions\ListAffectedReservations;
use App\Domain\Reservation\Actions\RecordAffectedCaseOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\ListAffectedReservationsRequest;
use App\Http\Requests\Reservation\RecordAffectedCaseOutcomeRequest;
use App\Http\Resources\Reservation\AffectedReservationCaseResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Design D7 handling cases for bookings affected by operational changes. */
class AffectedReservationCaseController extends Controller
{
    public function index(ListAffectedReservationsRequest $request, ListAffectedReservations $action): AnonymousResourceCollection
    {
        return AffectedReservationCaseResource::collection($action->execute($request->user(), $request->validated()));
    }

    public function update(RecordAffectedCaseOutcomeRequest $request, int $case, RecordAffectedCaseOutcome $action): AffectedReservationCaseResource
    {
        $record = $action->execute($request->user(), $case, $request->validated());

        return new AffectedReservationCaseResource($record->load(['reservation' => fn ($q) => $q->withoutGlobalScope('tenant_isolation'), 'venue:id,name', 'handledBy:id,name']));
    }
}
