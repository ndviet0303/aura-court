<?php

namespace App\Http\Requests\Admin;

use App\Domain\Package\Enums\PackageTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowPlatformFeeReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'venue_id' => ['nullable', 'integer'],
            'term' => ['nullable', 'integer', Rule::enum(PackageTerm::class)],
            // PostgreSQL has no year 0, so a far-past date must fail here rather than in the query.
            'from' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01'],
            // after_or_equal reads the raw from value, and an array from would throw a TypeError.
            'to' => ['required', 'date_format:Y-m-d', Rule::when(is_string($this->input('from')), 'after_or_equal:from')],
        ];
    }

    public function messages(): array
    {
        return [
            'venue_id.integer' => 'Cơ sở được chọn không hợp lệ.',
            'term.integer' => 'Kỳ hạn gói phải là 30, 90, 180 hoặc 360 ngày.',
            'term.enum' => 'Kỳ hạn gói phải là 30, 90, 180 hoặc 360 ngày.',
            'from.required' => 'Vui lòng chọn ngày bắt đầu của kỳ.',
            'from.date_format' => 'Ngày bắt đầu phải có dạng YYYY-MM-DD.',
            'from.after_or_equal' => 'Ngày bắt đầu không được trước ngày 01/01/2000.',
            'to.required' => 'Vui lòng chọn ngày kết thúc của kỳ.',
            'to.date_format' => 'Ngày kết thúc phải có dạng YYYY-MM-DD.',
            'to.after_or_equal' => 'Ngày kết thúc không được trước ngày bắt đầu.',
        ];
    }
}
