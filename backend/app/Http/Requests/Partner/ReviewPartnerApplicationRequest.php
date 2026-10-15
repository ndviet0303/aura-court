<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class ReviewPartnerApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isAdmin() || $user->isPlatformAdmin());
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:approve,revision_required,reject'],
            'reason' => ['required_if:decision,revision_required,reject', 'nullable', 'string', 'min:10', 'max:1000'],
            'reviewer_notes' => ['nullable', 'string', 'max:1000'],
            'feedback_fields' => ['nullable', 'array'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required_if' => 'Vui lòng nhập lý do khi yêu cầu bổ sung hoặc từ chối hồ sơ.',
            'reason.min' => 'Lý do phải dài 10–1.000 ký tự.',
            'reason.max' => 'Lý do phải dài 10–1.000 ký tự.',
        ];
    }
}
