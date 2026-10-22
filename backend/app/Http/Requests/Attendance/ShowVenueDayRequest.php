<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

/** FR-101, D12: the venue day to show, a date in venue time. The route admits owners and staff; the controller checks the venue. */
class ShowVenueDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // PostgreSQL has no year 0, so a far-past date must fail here rather than in the query.
            'date' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Vui lòng chọn ngày cần xem.',
            'date.date_format' => 'Ngày phải có dạng YYYY-MM-DD.',
            'date.after_or_equal' => 'Ngày không được trước 01/01/2000.',
        ];
    }
}
