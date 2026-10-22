<?php

namespace App\Http\Controllers\Court;

use App\Domain\Venue\Actions\OpenCourt;
use App\Domain\Venue\Actions\ResumeCourt;
use App\Domain\Venue\Actions\StopCourt;
use App\Domain\Venue\DTOs\OperationalStopData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Venue\LifecycleCommandRequest;
use App\Http\Requests\Venue\PauseRequest;
use App\Models\Court;
use Illuminate\Http\JsonResponse;

/** D3, D4, D5: status commands of a court (BR-34 in the requests and ResumeCourt). */
class CourtLifecycleController extends Controller
{
    public function open(LifecycleCommandRequest $request, Court $court, OpenCourt $action): JsonResponse
    {
        return response()->json(['data' => $action->execute((int) $court->id), 'message' => 'Đã mở nhận đặt sân.']);
    }

    public function pause(PauseRequest $request, Court $court, StopCourt $action): JsonResponse
    {
        return $this->stopped($action, $request, $court, 'Đã ghi nhận tạm ngừng sân.');
    }

    public function resume(LifecycleCommandRequest $request, Court $court, ResumeCourt $action): JsonResponse
    {
        return response()->json(['data' => $action->execute((int) $court->id, $request->user()), 'message' => 'Đã mở lại sân.']);
    }

    public function close(PauseRequest $request, Court $court, StopCourt $action): JsonResponse
    {
        return $this->stopped($action, $request, $court, 'Đã ghi nhận ngừng kinh doanh sân.');
    }

    private function stopped(StopCourt $action, PauseRequest $request, Court $court, string $message): JsonResponse
    {
        $stop = $action->execute((int) $court->id, OperationalStopData::fromRequest($request));

        return response()->json(['data' => $stop->court, 'stop' => $stop->withoutRelations(), 'message' => $message]);
    }
}
