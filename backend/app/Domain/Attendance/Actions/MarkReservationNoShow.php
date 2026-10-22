<?php

namespace App\Domain\Attendance\Actions;

use App\Contracts\Repositories\AffectedReservationCaseRepositoryInterface;
use App\Contracts\Repositories\BookingAttendanceRepositoryInterface;
use App\Domain\Attendance\DTOs\AttendanceCommandData;
use App\Domain\Attendance\DTOs\AttendanceOutcome;
use App\Domain\Attendance\Rules\AttendanceGuard;
use App\Models\BookingAttendance;
use Illuminate\Support\Facades\DB;

/**
 * FR-107: once its booked time has ended, a confirmed booking never checked in is recorded as a no-show with its own
 * time and actor and no check-in time (BR-133, BR-135). Nothing is refunded. An open affected-booking case is handled
 * first (module 2 D8).
 */
class MarkReservationNoShow
{
    public function __construct(
        private readonly BookingAttendanceRepositoryInterface $attendances,
        private readonly AffectedReservationCaseRepositoryInterface $cases,
    ) {}

    public function execute(AttendanceCommandData $data): AttendanceOutcome
    {
        return DB::transaction(function () use ($data): AttendanceOutcome {
            $reservation = AttendanceGuard::confirmed($this->attendances->findByCode($data->code, $data->organizationId, true), $data->actor);
            $status = $reservation->attendance?->status;
            if ($status === BookingAttendance::STATUS_NO_SHOW) {
                return new AttendanceOutcome($reservation, true);
            }
            if ($status !== null) {
                AttendanceGuard::fail('ALREADY_CHECKED_IN', 'Đơn đã check-in, không ghi nhận Không đến.');
            }
            if ($this->cases->hasOpen((int) $reservation->id)) {
                AttendanceGuard::fail('AFFECTED_CASE_OPEN', 'Đơn đang có case ảnh hưởng chưa xử lý xong, không thể ghi vắng mặt.', 409);
            }
            $now = now();
            if ($now->lt($reservation->ends_at)) {
                AttendanceGuard::fail('NO_SHOW_TOO_EARLY', 'Chỉ ghi nhận Không đến sau khi hết thời gian đặt.');
            }

            $this->attendances->record($reservation, [
                'status' => BookingAttendance::STATUS_NO_SHOW,
                'no_show_at' => $now,
                'no_show_by' => $data->actor->id,
            ]);

            return new AttendanceOutcome($reservation, false);
        });
    }
}
