<?php

namespace App\Http\Controllers\Reservation;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\AffectedBookingCommandRequest;
use App\Services\Reservation\AffectedBookingCommands;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

/** Design D8 operator commands on a booking with an open affected-booking case. */
class AffectedBookingCommandController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private readonly AffectedBookingCommands $commands) {}

    public function moveCourt(AffectedBookingCommandRequest $request, string $reservation): JsonResponse
    {
        $record = $this->commands->find($request->user(), $this->organization($request), $reservation);

        return response()->json(['reservation' => $this->commands->move($request->user(), $record, (int) $request->validated('court_id'), $request->validated())]);
    }

    public function changeTime(AffectedBookingCommandRequest $request, string $reservation): JsonResponse
    {
        $record = $this->commands->find($request->user(), $this->organization($request), $reservation);

        return response()->json(['reservation' => $this->commands->changeTime(
            $request->user(),
            $record,
            Carbon::parse($request->validated('starts_at')),
            Carbon::parse($request->validated('ends_at')),
            $request->validated(),
        )]);
    }

    public function cancel(AffectedBookingCommandRequest $request, string $reservation): JsonResponse
    {
        $record = $this->commands->find($request->user(), $this->organization($request), $reservation);

        return response()->json(['reservation' => $this->commands->cancel($request->user(), $record, $request->validated())]);
    }
}
