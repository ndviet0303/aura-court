<?php

namespace App\Domain\Attendance\Actions;

use App\Contracts\Repositories\AffectedReservationCaseRepositoryInterface;
use App\Contracts\Repositories\BookingAttendanceAdjustmentRepositoryInterface;
use App\Contracts\Repositories\BookingAttendanceRepositoryInterface;
use App\Domain\Attendance\DTOs\AttendanceAdjustmentData;
use App\Domain\Attendance\Rules\AttendanceGuard;
use App\Models\BookingAttendance;
use App\Models\BookingAttendanceAdjustment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * FR-108, FR-109: the owner sets a confirmed booking to confirmed, in use, completed or no-show with the times that
 * status has (BR-135), and the change is kept with its before and after values, reason, owner and time (BR-136).
 * A time kept as it was keeps who recorded it; a new or corrected time is the owner's.
 */
class AdjustReservationAttendance
{
    public function __construct(
        private readonly BookingAttendanceRepositoryInterface $attendances,
        private readonly BookingAttendanceAdjustmentRepositoryInterface $adjustments,
        private readonly AffectedReservationCaseRepositoryInterface $cases,
    ) {}

    public function execute(AttendanceAdjustmentData $data): BookingAttendanceAdjustment
    {
        return DB::transaction(function () use ($data): BookingAttendanceAdjustment {
            $reservation = AttendanceGuard::confirmed($this->attendances->findByCode($data->code, $data->organizationId, true), $data->owner);
            $attendance = $reservation->attendance;
            $before = $this->snapshot($attendance);
            if ($before !== $data->expected) {
                AttendanceGuard::fail('ATTENDANCE_CHANGED', 'Dữ liệu vận hành của đơn đã thay đổi. Vui lòng tải lại và kiểm tra trước khi điều chỉnh.', 409);
            }
            $now = now();
            $noShowAt = $data->status !== 'no_show' ? null : ($attendance?->status === BookingAttendance::STATUS_NO_SHOW ? $attendance->no_show_at : $now);
            $after = [
                'status' => $data->status,
                'checked_in_at' => $data->checkedInAt?->toIso8601String(),
                'checked_out_at' => $data->checkedOutAt?->toIso8601String(),
                'no_show_at' => $noShowAt?->toIso8601String(),
            ];
            if ($after === $before) {
                AttendanceGuard::fail('ADJUSTMENT_NO_CHANGE', 'Trạng thái và thời điểm không thay đổi.');
            }
            if ($data->status === 'no_show' && $before['status'] !== 'no_show') {
                // Module 2 D8 and BR-133 hold for an adjustment as for the command.
                if ($this->cases->hasOpen((int) $reservation->id)) {
                    AttendanceGuard::fail('AFFECTED_CASE_OPEN', 'Đơn đang có case ảnh hưởng chưa xử lý xong, không thể ghi vắng mặt.', 409);
                }
                if ($now->lt($reservation->ends_at)) {
                    AttendanceGuard::fail('NO_SHOW_TOO_EARLY', 'Chỉ ghi nhận Không đến sau khi hết thời gian đặt.');
                }
            }

            if ($data->status === 'confirmed') {
                $this->attendances->clear($reservation);
            } else {
                $by = fn (string $event, ?CarbonInterface $at) => $at === null ? null
                    : ($attendance?->{"{$event}_at"}?->equalTo($at) ? ($attendance->{"{$event}_by"} ?? $data->owner->id) : $data->owner->id);
                $this->attendances->record($reservation, [
                    'status' => array_search($data->status, BookingAttendance::OPERATIONAL_STATUS, true),
                    'checked_in_at' => $data->checkedInAt,
                    'checked_in_by' => $by('checked_in', $data->checkedInAt),
                    'checked_out_at' => $data->checkedOutAt,
                    'checked_out_by' => $by('checked_out', $data->checkedOutAt),
                    'no_show_at' => $noShowAt,
                    'no_show_by' => $by('no_show', $noShowAt),
                ]);
            }

            return $this->adjustments->record([
                'organization_id' => $reservation->organization_id,
                'reservation_id' => $reservation->id,
                'adjusted_by' => $data->owner->id,
                'reason' => $data->reason,
                'before' => $before,
                'after' => $after,
                'created_at' => $now,
            ])->setRelation('reservation', $reservation);
        });
    }

    /** @return array{status: string, checked_in_at: ?string, checked_out_at: ?string, no_show_at: ?string} */
    private function snapshot(?BookingAttendance $attendance): array
    {
        return [
            'status' => $attendance === null ? 'confirmed' : BookingAttendance::OPERATIONAL_STATUS[$attendance->status],
            'checked_in_at' => $attendance?->checked_in_at?->toIso8601String(),
            'checked_out_at' => $attendance?->checked_out_at?->toIso8601String(),
            'no_show_at' => $attendance?->no_show_at?->toIso8601String(),
        ];
    }
}
