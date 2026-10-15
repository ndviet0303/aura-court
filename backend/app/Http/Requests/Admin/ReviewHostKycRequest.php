<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReviewHostKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:approve,reject,request_supplement'],
            'rejection_reason' => ['required_if:decision,reject,request_supplement', 'nullable', 'string', 'min:10', 'max:1000'],
            'reviewer_notes' => ['nullable', 'string', 'max:1000'],
            'checklist_state' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required_if' => 'Vui lòng nhập lý do khi yêu cầu bổ sung hoặc từ chối hồ sơ.',
            'rejection_reason.min' => 'Lý do phải dài 10–1.000 ký tự.',
            'rejection_reason.max' => 'Lý do phải dài 10–1.000 ký tự.',
        ];
    }
}
