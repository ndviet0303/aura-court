<?php

namespace App\Domain\Attendance\Actions;

use App\Contracts\Repositories\BookingAttendanceRepositoryInterface;
use App\Domain\Attendance\DTOs\AttendanceCommandData;
use App\Domain\Attendance\DTOs\AttendanceOutcome;
use App\Domain\Attendance\Rules\AttendanceGuard;
use App\Models\BookingAttendance;
use Illuminate\Support\Facades\DB;

/**
 * FR-106: a booking in use is checked out at the time of the action, early or late, and becomes completed. Neither
 * its price nor its end time changes (BR-128, BR-129); a completed booking is not checked out again (BR-130).
 */
class CheckOutReservation
{
    public function __construct(private readonly BookingAttendanceRepositoryInterface $attendances) {}

    public function execute(AttendanceCommandData $data): AttendanceOutcome
    {
        return DB::transaction(function () use ($data): AttendanceOutcome {
            $reservation = AttendanceGuard::confirmed($this->attendances->findByCode($data->code, $data->organizationId, true), $data->actor);
            $status = $reservation->attendance?->status;
            if ($status === BookingAttendance::STATUS_CHECKED_OUT) {
                return new AttendanceOutcome($reservation, true);
            }
            if ($status === BookingAttendance::STATUS_NO_SHOW) {
                AttendanceGuard::fail('ALREADY_NO_SHOW', 'Đơn đã được ghi nhận Không đến.');
            }
            if ($status !== BookingAttendance::STATUS_CHECKED_IN) {
                AttendanceGuard::fail('NOT_CHECKED_IN', 'Đơn chưa check-in nên chưa thể check-out.');
            }

            $this->attendances->record($reservation, [
                'status' => BookingAttendance::STATUS_CHECKED_OUT,
                'checked_out_at' => now(),
                'checked_out_by' => $data->actor->id,
            ]);

            return new AttendanceOutcome($reservation, false);
        });
    }
}
