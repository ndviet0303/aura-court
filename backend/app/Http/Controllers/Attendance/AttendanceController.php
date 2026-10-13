<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Infrastructure\IdempotencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    use ResolvesOrganization;

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
            'customer_id' => 'nullable|integer',
            'venue_id' => 'nullable|integer',
        ]);

        $customer = ! empty($data['customer_id']) ? User::find($data['customer_id']) : null;
        $venueId = ! empty($data['venue_id']) ? (int) $data['venue_id'] : null;

        return $this->idempotency->run($request, function () use ($request, $organization, $data, $customer, $venueId) {
            $result = $this->attendance->checkIn(
                staff: $request->user(),
                organizationId: $organization->id,
                codeOrId: $data['code'],
                notes: $data['notes'] ?? null,
                customer: $customer,
                venueId: $venueId,
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
                'unpaid_receivable' => $result['unpaid_receivable'] ?? 0,
                'message' => $result['duplicate'] ? 'Check-out already recorded.' : 'Check-out successful.',
            ], 200);
        });
    }

    public function confirmReady(Request $request, Court $court): JsonResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $result = $this->attendance->confirmCourtReady(
            staff: $request->user(),
            organizationId: $organization->id,
            courtId: (int) $court->id,
            notes: $data['notes'] ?? null,
        );

        return response()->json($result);
    }

    public function markNoShow(Request $request): JsonResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'code' => 'required|string|max:255',
            'reason' => 'nullable|string|max:1000',
        ]);

        $result = $this->attendance->markNoShow(
            staff: $request->user(),
            organizationId: $organization->id,
            codeOrId: $data['code'],
            reason: $data['reason'] ?? null,
        );

        return response()->json($result);
    }

    public function reassignCourt(Request $request): JsonResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'code' => 'required|string|max:255',
            'new_court_id' => 'required|integer',
            'reason' => 'required|string|max:1000',
        ]);

        $result = $this->attendance->reassignCourt(
            staff: $request->user(),
            organizationId: $organization->id,
            codeOrId: $data['code'],
            newCourtId: (int) $data['new_court_id'],
            reason: $data['reason'],
        );

        return response()->json($result);
    }

    public function show(Request $request, string $code): JsonResponse
    {
        $organization = $this->organization($request);
        $result = $this->attendance->getAttendance($organization->id, $code);

        return response()->json($result);
    }
}
