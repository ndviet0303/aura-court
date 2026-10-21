<?php

namespace App\Http\Controllers\Booking;

use App\Domain\Venue\Actions\VerifyEffectiveBookingEligibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\AvailabilityRequest;
use App\Services\Reservation\CourtAvailabilityService;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends Controller
{
    public function __invoke(AvailabilityRequest $request, VerifyEffectiveBookingEligibility $eligibility, CourtAvailabilityService $availability): JsonResponse
    {
        $court = $eligibility->assertEligible($request->integer('court_id'), publicFacing: true);

        return response()->json($availability->forDate($court, $request->string('date')->toString()));
    }
}
