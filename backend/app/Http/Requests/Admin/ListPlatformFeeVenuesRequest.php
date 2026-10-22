<?php

namespace App\Http\Requests\Admin;

use App\Domain\Package\Enums\PackageStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPlatformFeeVenuesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(PackageStatus::class)],
            'expiring_within' => ['nullable', 'integer', Rule::in([1, 3, 7])],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.enum' => 'Tình trạng gói phải là none, active, expired hoặc terminated.',
            'expiring_within.integer' => 'Nhóm sắp hết hạn phải là 1, 3 hoặc 7 ngày.',
            'expiring_within.in' => 'Nhóm sắp hết hạn phải là 1, 3 hoặc 7 ngày.',
            'search.string' => 'Từ khoá tìm kiếm không hợp lệ.',
            'search.max' => 'Từ khoá tìm kiếm tối đa 100 ký tự.',
        ];
    }
}
