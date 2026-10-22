<?php

namespace App\Http\Controllers\Attendance;

use App\Contracts\Repositories\BookingAttendanceRepositoryInterface;
use App\Domain\Attendance\Rules\AttendanceGuard;
use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\ShowVenueDayRequest;
use App\Http\Resources\Attendance\OperationalReservationResource;
use App\Models\Court;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Module 06: the venue's day schedule by court (FR-101, D12) and its bookings in use (FR-104), for owners and staff. */
class OperationsController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private readonly BookingAttendanceRepositoryInterface $attendances) {}

    public function day(ShowVenueDayRequest $request, Venue $venue): JsonResponse
    {
        $this->operable($request, $venue);
        $from = Carbon::parse((string) $request->validated('date'), 'Asia/Ho_Chi_Minh')->startOfDay();

        return response()->json([
            'date' => $from->toDateString(),
            'venue' => ['id' => $venue->id, 'name' => $venue->name],
            'courts' => $venue->courts->sortBy('code')->values()->map(fn (Court $court) => [
                'id' => $court->id, 'code' => $court->code, 'name' => $court->name, 'status' => $court->status,
            ]),
            'reservations' => OperationalReservationResource::collection($this->attendances->daySchedule($venue->id, $from, $from->copy()->addDay())),
        ]);
    }

    public function inUse(Request $request, Venue $venue): JsonResponse
    {
        $this->operable($request, $venue);

        return response()->json([
            'reservations' => OperationalReservationResource::collection($this->attendances->inUse($venue->id)),
        ]);
    }

    private function operable(Request $request, Venue $venue): void
    {
        AttendanceGuard::venue($venue, (int) $this->organization($request)->id, $request->user());
    }
}
