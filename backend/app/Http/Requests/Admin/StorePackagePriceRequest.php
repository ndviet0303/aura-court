<?php

namespace App\Http\Requests\Admin;

use App\Domain\Package\Enums\PackageTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePackagePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'term_days' => ['required', 'integer', Rule::enum(PackageTerm::class)],
            'amount' => ['required', 'integer', 'min:1'],
            'effective_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'term_days.required' => 'Vui lòng chọn kỳ hạn gói.',
            'term_days.integer' => 'Kỳ hạn gói phải là 30, 90, 180 hoặc 360 ngày.',
            'term_days.enum' => 'Kỳ hạn gói phải là 30, 90, 180 hoặc 360 ngày.',
            'amount.required' => 'Vui lòng nhập giá gói.',
            'amount.integer' => 'Giá gói phải là số nguyên VND lớn hơn 0.',
            'amount.min' => 'Giá gói phải là số nguyên VND lớn hơn 0.',
            'effective_at.date' => 'Thời điểm áp dụng không hợp lệ.',
        ];
    }
}
