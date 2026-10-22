<?php

namespace App\Http\Controllers\Partner;

use App\Domain\Venue\Actions\OpenVenue;
use App\Domain\Venue\Actions\ResumeVenue;
use App\Domain\Venue\Actions\StopVenue;
use App\Domain\Venue\DTOs\OperationalStopData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Venue\LifecycleCommandRequest;
use App\Http\Requests\Venue\PauseRequest;
use Illuminate\Http\JsonResponse;

/** D1, D4, D5: the owner's status commands of a venue. The requests authorize; the actions lock and check. */
class VenueLifecycleController extends Controller
{
    public function open(LifecycleCommandRequest $request, int $organizationId, int $venueId, OpenVenue $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($venueId), 'message' => 'Đã mở hoạt động cơ sở.']);
    }

    public function pause(PauseRequest $request, int $organizationId, int $venueId, StopVenue $action): JsonResponse
    {
        return $this->stopped($action, $request, $venueId, 'Đã ghi nhận tạm ngừng cơ sở.');
    }

    public function resume(LifecycleCommandRequest $request, int $organizationId, int $venueId, ResumeVenue $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($venueId, (int) $request->user()->id), 'message' => 'Đã mở lại cơ sở.']);
    }

    public function close(PauseRequest $request, int $organizationId, int $venueId, StopVenue $action): JsonResponse
    {
        return $this->stopped($action, $request, $venueId, 'Đã ghi nhận ngừng kinh doanh cơ sở.');
    }

    private function stopped(StopVenue $action, PauseRequest $request, int $venueId, string $message): JsonResponse
    {
        $stop = $action->execute($venueId, OperationalStopData::fromRequest($request));

        return response()->json(['data' => $stop->venue, 'stop' => $stop->withoutRelations(), 'message' => $message]);
    }
}
