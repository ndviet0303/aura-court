<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** D13: the `admin` route middleware already limits this to unlocked platform admins. */
class SuspendVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => is_string($this->input('reason')) ? trim($this->input('reason')) : $this->input('reason')]);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'acknowledge_affected' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Vui lòng nhập lý do đình chỉ.',
            'reason.min' => 'Lý do đình chỉ phải dài 10–1000 ký tự.',
            'reason.max' => 'Lý do đình chỉ phải dài 10–1000 ký tự.',
        ];
    }
}
