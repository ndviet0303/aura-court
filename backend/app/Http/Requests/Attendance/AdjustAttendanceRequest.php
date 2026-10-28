<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FR-108, D10: an owner correction of a booking's status and check-in/out times, with a reason. Only these fields are
 * read, so amount, payment, court and booked time cannot change here (BR-137). The route admits owners only (BR-134).
 */
class AdjustAttendanceRequest extends FormRequest
{
    public const STATUSES = ['confirmed', 'in_use', 'completed', 'no_show'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $status = $this->input('status');
        $checkedIn = in_array($status, ['in_use', 'completed'], true);

        // BR-135: each status carries exactly the times it has; times are never in the future.
        return [
            'code' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
            'checked_in_at' => ['bail', Rule::prohibitedIf(! $checkedIn), Rule::requiredIf($checkedIn), 'nullable', 'date', 'before_or_equal:now'],
            'checked_out_at' => ['bail', Rule::prohibitedIf($status !== 'completed'), Rule::requiredIf($status === 'completed'), 'nullable', 'date',
                'before_or_equal:now', Rule::when($checkedIn && is_string($this->input('checked_in_at')), 'after_or_equal:checked_in_at')],
            'reason' => ['required', 'string', 'max:1000'],
            // UC-6.7 E-3: the operational data the owner loaded; a change since then is not overwritten.
            'expected' => ['required', 'array'],
            'expected.status' => ['required', 'string', Rule::in(self::STATUSES)],
            'expected.checked_in_at' => ['nullable', 'date'],
            'expected.checked_out_at' => ['nullable', 'date'],
            'expected.no_show_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Trạng thái điều chỉnh không hợp lệ.',
            'checked_in_at.required' => 'Trạng thái này cần thời điểm check-in.',
            'checked_in_at.prohibited' => 'Trạng thái này không có thời điểm check-in.',
            'checked_in_at.before_or_equal' => 'Thời điểm check-in không được sau hiện tại.',
            'checked_out_at.required' => 'Hoàn thành cần thời điểm check-out.',
            'checked_out_at.prohibited' => 'Chỉ đơn Hoàn thành có thời điểm check-out.',
            'checked_out_at.before_or_equal' => 'Thời điểm check-out không được sau hiện tại.',
            'checked_out_at.after_or_equal' => 'Thời điểm check-out không được trước check-in.',
            'reason.required' => 'Vui lòng nhập lý do điều chỉnh.',
            'expected.required' => 'Thiếu dữ liệu vận hành đã tải; vui lòng tải lại đơn.',
            'expected.status.required' => 'Thiếu dữ liệu vận hành đã tải; vui lòng tải lại đơn.',
        ];
    }
}
