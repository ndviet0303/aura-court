<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

/** BR-44: content 10–5000 characters after trimming, 1–10 PDF/JPG/JPEG/PNG files of at most 10 MB each. */
class SubmitRemediationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ! $user->isPlatformAdmin() && $user->isOrganizationOwner((int) $this->route('organizationId'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['content' => is_string($this->input('content')) ? trim($this->input('content')) : $this->input('content')]);
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:10', 'max:5000'],
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['required', 'file', 'extensions:pdf,jpg,jpeg,png', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Vui lòng nhập nội dung khắc phục.',
            'content.min' => 'Nội dung khắc phục phải dài 10–5000 ký tự.',
            'content.max' => 'Nội dung khắc phục phải dài 10–5000 ký tự.',
            'files.required' => 'Cần đính kèm 1–10 tệp minh chứng.',
            'files.min' => 'Cần đính kèm 1–10 tệp minh chứng.',
            'files.max' => 'Cần đính kèm 1–10 tệp minh chứng.',
            'files.*.extensions' => 'Tệp minh chứng phải là PDF, JPG, JPEG hoặc PNG.',
            'files.*.mimes' => 'Tệp minh chứng phải là PDF, JPG, JPEG hoặc PNG.',
            'files.*.max' => 'Mỗi tệp minh chứng tối đa 10 MB.',
        ];
    }
}
