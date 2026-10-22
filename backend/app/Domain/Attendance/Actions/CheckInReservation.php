<?php

namespace App\Domain\Attendance\Actions;

use App\Contracts\Repositories\BookingAttendanceRepositoryInterface;
use App\Domain\Attendance\DTOs\AttendanceCommandData;
use App\Domain\Attendance\DTOs\AttendanceOutcome;
use App\Domain\Attendance\Rules\AttendanceGuard;
use App\Models\BookingAttendance;
use Illuminate\Support\Facades\DB;

/**
 * FR-103: a confirmed booking not yet checked in is checked in from 10 minutes before its start until its end, at
 * the time of the scan (BR-125..BR-127, D5). Another booking still in use on the court does not block it (BR-132,
 * D6); a stopped court does.
 */
class CheckInReservation
{
    public const EARLY_MINUTES = 10;

    public function __construct(private readonly BookingAttendanceRepositoryInterface $attendances) {}

    public function execute(AttendanceCommandData $data): AttendanceOutcome
    {
        return DB::transaction(function () use ($data): AttendanceOutcome {
            $reservation = AttendanceGuard::confirmed($this->attendances->findByCode($data->code, $data->organizationId, true), $data->actor);
            $status = $reservation->attendance?->status;
            if ($status === BookingAttendance::STATUS_CHECKED_IN) {
                return new AttendanceOutcome($reservation, true);
            }
            if ($status === BookingAttendance::STATUS_CHECKED_OUT) {
                AttendanceGuard::fail('ALREADY_COMPLETED', 'Đơn đã hoàn thành, không check-in lại.');
            }
            if ($status === BookingAttendance::STATUS_NO_SHOW) {
                AttendanceGuard::fail('ALREADY_NO_SHOW', 'Đơn đã được ghi nhận Không đến.');
            }

            $now = now();
            if ($now->lt($reservation->starts_at->copy()->subMinutes(self::EARLY_MINUTES))) {
                AttendanceGuard::fail('CHECK_IN_TOO_EARLY', 'Chỉ được check-in sớm tối đa 10 phút trước giờ bắt đầu.');
            }
            if ($now->gte($reservation->ends_at)) {
                AttendanceGuard::fail('CHECK_IN_WINDOW_CLOSED', 'Đơn đã hết thời gian sử dụng; chủ cơ sở điều chỉnh nếu khách đã nhận sân.');
            }
            if (! $this->attendances->courtInService($reservation->court, $now)) {
                AttendanceGuard::fail('COURT_OUT_OF_SERVICE', 'Sân đang ngừng phục vụ, không bàn giao được.');
            }

            $this->attendances->record($reservation, [
                'status' => BookingAttendance::STATUS_CHECKED_IN,
                'checked_in_at' => $now,
                'checked_in_by' => $data->actor->id,
            ]);

            return new AttendanceOutcome($reservation, false);
        });
    }
}
