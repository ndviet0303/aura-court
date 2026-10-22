<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Venue\Actions\PreviewVenueSuspension;
use App\Domain\Venue\Actions\SuspendVenue;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SuspendVenueRequest;
use App\Http\Resources\Venue\VenueSuspensionResource;
use Illuminate\Http\JsonResponse;

/** D13: the only way to suspend a venue; lifting happens only through an approved remediation review. */
class AdminVenueSuspensionController extends Controller
{
    public function preview(int $venueId, PreviewVenueSuspension $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($venueId)]);
    }

    public function store(SuspendVenueRequest $request, int $venueId, SuspendVenue $action): JsonResponse
    {
        $suspension = $action->execute($request->user(), $venueId, $request->validated('reason'), $request->boolean('acknowledge_affected'));

        return response()->json([
            'message' => 'Đã đình chỉ cơ sở. Các lượt đặt bị ảnh hưởng đã được chuyển cho chủ cơ sở xử lý.',
            'data' => new VenueSuspensionResource($suspension),
        ], 201);
    }
}
