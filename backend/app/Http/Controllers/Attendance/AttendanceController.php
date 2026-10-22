<?php

namespace App\Http\Controllers\Attendance;

use App\Contracts\Repositories\BookingAttendanceRepositoryInterface;
use App\Domain\Attendance\Actions\CheckInReservation;
use App\Domain\Attendance\Actions\CheckOutReservation;
use App\Domain\Attendance\Actions\MarkReservationNoShow;
use App\Domain\Attendance\DTOs\AttendanceCommandData;
use App\Domain\Attendance\DTOs\AttendanceOutcome;
use App\Domain\Attendance\Rules\AttendanceGuard;
use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceCommandRequest;
use App\Http\Resources\Attendance\OperationalReservationResource;
use App\Services\Infrastructure\IdempotencyService;
use App\Services\Payment\ReservationPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Module 06: one endpoint per on-site command (D8) and the booking lookup by code behind them (FR-102). */
class AttendanceController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private readonly IdempotencyService $idempotency) {}

    public function checkIn(AttendanceCommandRequest $request, CheckInReservation $action): Response
    {
        return $this->idempotency->run($request, fn () => $this->respond($action->execute($this->command($request)), 'Check-in thành công.', 201));
    }

    public function checkOut(AttendanceCommandRequest $request, CheckOutReservation $action): Response
    {
        return $this->idempotency->run($request, fn () => $this->respond($action->execute($this->command($request)), 'Check-out thành công.'));
    }

    public function markNoShow(AttendanceCommandRequest $request, MarkReservationNoShow $action): JsonResponse
    {
        return $this->respond($action->execute($this->command($request)), 'Đã ghi nhận Không đến.');
    }

    /** BR-122: staff see the amount and the payment result of a booking at their venue, without receiver details. */
    public function show(Request $request, string $code, BookingAttendanceRepositoryInterface $attendances, ReservationPaymentService $payments): JsonResponse
    {
        $reservation = AttendanceGuard::operable($attendances->findByCode($code, (int) $this->organization($request)->id), $request->user());

        return response()->json([
            'reservation' => new OperationalReservationResource($reservation),
            'payment' => $payments->result($reservation),
        ]);
    }

    private function command(AttendanceCommandRequest $request): AttendanceCommandData
    {
        return AttendanceCommandData::fromRequest($request, (int) $this->organization($request)->id);
    }

    private function respond(AttendanceOutcome $outcome, string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'reservation' => new OperationalReservationResource($outcome->reservation),
            'duplicate' => $outcome->duplicate,
            'message' => $outcome->duplicate ? 'Thao tác này đã được ghi nhận trước đó.' : $message,
        ], $outcome->duplicate ? 200 : $status);
    }
}
