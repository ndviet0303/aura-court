<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Concerns\ResolvesV2Organization;
use App\Http\Controllers\Controller;
use App\Services\Attendance\AttendanceService;
use App\Services\Infrastructure\IdempotencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    use ResolvesV2Organization;

    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function checkIn(Request $request): JsonResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'code' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        return $this->idempotency->run($request, function () use ($request, $organization, $data) {
            $result = $this->attendance->checkIn(
                staff: $request->user(),
                organizationId: $organization->id,
                codeOrId: $data['code'],
                notes: $data['notes'] ?? null,
            );

            return response()->json([
                'attendance' => $result['attendance'],
                'reservation' => $result['reservation'],
                'duplicate' => $result['duplicate'],
                'message' => $result['duplicate'] ? 'Check-in already recorded.' : 'Check-in successful.',
            ], $result['duplicate'] ? 200 : 201);
        });
    }

    public function checkOut(Request $request): JsonResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'code' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        return $this->idempotency->run($request, function () use ($request, $organization, $data) {
            $result = $this->attendance->checkOut(
                staff: $request->user(),
                organizationId: $organization->id,
                codeOrId: $data['code'],
                notes: $data['notes'] ?? null,
            );

            return response()->json([
                'attendance' => $result['attendance'],
                'reservation' => $result['reservation'],
                'duplicate' => $result['duplicate'],
                'message' => $result['duplicate'] ? 'Check-out already recorded.' : 'Check-out successful.',
            ], 200);
        });
    }

    public function show(Request $request, string $code): JsonResponse
    {
        $organization = $this->organization($request);
        $result = $this->attendance->getAttendance($organization->id, $code);

        return response()->json($result);
    }
}
